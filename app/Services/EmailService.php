<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use App\Models\EmailQueue;
use App\Core\Logger;

/**
 * EmailService – PHPMailer wrapper với cơ chế fallback email_queue.
 *
 * Triết lý thiết kế (TDD Phần 1.3 – 3 lớp xử lý lỗi):
 *   Lớp 1: SMTPTimeout = 10s để tránh treo request trên InfinityFree.
 *   Lớp 2: try-catch – lỗi KHÔNG được phá vỡ luồng nghiệp vụ chính.
 *   Lớp 3: Ghi email_queue khi fail để Admin retry thủ công.
 *
 * WHY không dùng mail() built-in: InfinityFree block hàm này.
 * WHY X-Mailer để trống: Ẩn fingerprint PHPMailer, giảm spam score.
 */
class EmailService
{
    private PHPMailer $mailer;
    private EmailQueue $emailQueueModel;
    private Logger $logger;

    public function __construct()
    {
        $this->emailQueueModel = new EmailQueue();
        $this->logger = new Logger();
        $this->mailer = $this->buildMailer();
    }

    /**
     * Khởi tạo PHPMailer với config từ .env.
     * Tất cả thông số đều lấy từ $_ENV – không hardcode bất kỳ giá trị nào.
     */
    private function buildMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true); // true = throw exceptions

        // --- SMTP Config ---
        $mailer->isSMTP();
        $mailer->Host       = $_ENV['SMTP_HOST'];
        $mailer->SMTPAuth   = true;
        $mailer->Username   = $_ENV['SMTP_USER'];
        $mailer->Password   = $_ENV['SMTP_PASS'];
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Port 587
        $mailer->Port       = (int) $_ENV['SMTP_PORT'];

        // WHY SMTPTimeout = 10: InfinityFree có max_execution_time = 30s.
        // Nếu SMTP không phản hồi, phải throw exception trước khi PHP timeout.
        $mailer->SMTPOptions = [
            'socket' => [
                'connect_timeout' => 10,
            ],
        ];
        $mailer->Timeout = 10;

        // --- Sender ---
        $mailer->setFrom(
            $_ENV['SMTP_FROM'],
            $_ENV['SMTP_FROM_NAME']
        );

        // --- Security Headers chống Spam (TDD Phần 1.2) ---
        // WHY X-Mailer trống: Ẩn PHPMailer version fingerprint.
        $mailer->XMailer = ' ';

        // WHY Reply-To riêng: Gmail dùng để xếp hạng uy tín người gửi.
        $mailer->addReplyTo(
            $_ENV['SMTP_FROM'],
            $_ENV['SMTP_FROM_NAME']
        );

        // --- Encoding ---
        $mailer->CharSet  = PHPMailer::CHARSET_UTF8;
        $mailer->Encoding = PHPMailer::ENCODING_BASE64;
        $mailer->isHTML(true);

        return $mailer;
    }

    /**
     * Gửi một email.
     *
     * Khi SMTP fail: ghi system_logs (level=ERROR) + insert email_queue (status=failed).
     * Luồng nghiệp vụ chính (register, invite...) KHÔNG bị ngắt.
     *
     * @param  string $to       Địa chỉ email người nhận
     * @param  string $toName   Tên hiển thị người nhận
     * @param  string $subject  Tiêu đề email
     * @param  string $htmlBody Nội dung HTML đã render từ template
     * @return bool  true = gửi thành công, false = fail (đã vào queue)
     */
    public function send(
        string $to,
        string $toName,
        string $subject,
        string $htmlBody
    ): bool {
        try {
            // Reset trạng thái từ lần gửi trước (quan trọng khi gửi nhiều email)
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($to, $toName);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $htmlBody;

            // AltBody cho email client không render HTML
            $this->mailer->AltBody = strip_tags(
                str_replace(['<br>', '<br/>'], "\n", $htmlBody)
            );

            $this->mailer->send();

            $this->logger->info(
                "Email sent successfully to {$to}",
                'EmailService'
            );

            return true;

        } catch (PHPMailerException $e) {
            // --- Lớp 2: Graceful degradation ---
            $errorMessage = $e->getMessage();

            // Ghi system_logs level=ERROR
            $this->logger->error(
                "SMTP failed sending to {$to}: {$errorMessage}",
                'EmailService',
                $e->getTraceAsString()
            );

            // --- Lớp 3: Insert email_queue để Admin retry ---
            $this->emailQueueModel->insert(
                to: $to,
                toName: $toName,
                subject: $subject,
                bodyHtml: $htmlBody,
                status: 'failed',
                lastError: $errorMessage
            );

            // WHY return false thay vì throw: Không để lỗi email phá vỡ
            // luồng đăng ký/mời thành viên. Controller sẽ xử lý false này
            // bằng cách hiển thị cảnh báo nhẹ thay vì trang lỗi.
            return false;
        }
    }

    /**
     * Helper: render một email template PHP thành chuỗi HTML.
     * Dùng output buffering để capture output của file template.
     *
     * @param  string $templateFile Tên file template (VD: 'verify-email')
     * @param  array  $data         Dữ liệu truyền vào template (VD: ['user_name' => ...])
     * @return string HTML đã render
     * @throws \RuntimeException Nếu file template không tồn tại
     */
    public function renderTemplate(string $templateFile, array $data = []): string
    {
        // WHY dùng \PROJECT_ROOT thay vì PROJECT_ROOT:
        // File này nằm trong namespace App\Services.
        // PHP resolve constant theo thứ tự: namespace hiện tại → global scope.
        // Thêm backslash '\' buộc PHP tìm thẳng ở global scope,
        // tránh lỗi "Undefined constant 'App\Services\PROJECT_ROOT'" từ Intelephense
        // và tránh rủi ro runtime nếu constant chưa kịp load đúng thứ tự.
        $templatePath = \PROJECT_ROOT . "/app/Views/emails/{$templateFile}.php";

        if (!file_exists($templatePath)) {
            throw new \RuntimeException(
                "Email template not found: {$templateFile}"
            );
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    public function sendVerificationEmail(string $email, string $name, string $verifyUrl): bool
    {
        // 1. Render body HTML từ template (app/Views/emails/verify-email.php)
        // Lưu ý: Bạn cần tạo file template này nếu chưa có
        $htmlBody = $this->renderTemplate('verify-email', [
            'name'      => $name,
            'verifyUrl' => $verifyUrl
        ]);

        // 2. Định nghĩa Tiêu đề
        $subject = 'Xác thực tài khoản BugTracker của bạn';

        // 3. Sử dụng hàm send() đã viết sẵn trong class
        return $this->send(
            to: $email,
            toName: $name,
            subject: $subject,
            htmlBody: $htmlBody
        );
    }
}