<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * EmailQueue Model – quản lý hàng đợi email thất bại.
 *
 * WHY lưu body_html thay vì lưu tham số:
 * Khi retry, chỉ cần đọc body_html và gửi lại mà không phải rebuild template.
 * Tránh trường hợp token/data đã thay đổi giữa lần gửi đầu và lần retry.
 *
 * WHY không có cronjob: InfinityFree không hỗ trợ. Admin retry thủ công
 * qua trang /admin/email-queue (EmailQueueController – D1-024).
 *
 * @author  Dev 1
 * @version 1.0.1 – bổ sung countFailed(), phân trang getFailedEmails(),
 *                  sửa deleteOldFailed() để nhận $limit từ controller.
 */
class EmailQueue
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    /**
     * Thêm một email vào hàng đợi (thường là khi SMTP fail).
     *
     * WHY lưu body_html sẵn: khi retry không cần rebuild template,
     * tránh trường hợp token/data đã thay đổi.
     *
     * @param  string $to         Email người nhận
     * @param  string $toName     Tên người nhận
     * @param  string $subject    Tiêu đề email
     * @param  string $bodyHtml   HTML đã render sẵn (đủ để retry mà không lookup thêm)
     * @param  string $status     'pending' | 'failed'
     * @param  string $lastError  Message lỗi từ PHPMailer exception (nếu có)
     * @return int    ID bản ghi mới tạo
     */
    public function insert(
        string $to,
        string $toName,
        string $subject,
        string $bodyHtml,
        string $status    = 'pending',
        string $lastError = ''
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO email_queue
             (to_email, to_name, subject, body_html, status, last_error, attempts, created_at)
             VALUES (:to_email, :to_name, :subject, :body_html, :status, :last_error, 0, NOW())'
        );

        $stmt->execute([
            ':to_email'   => $to,
            ':to_name'    => $toName,
            ':subject'    => $subject,
            ':body_html'  => $bodyHtml,
            ':status'     => $status,
            ':last_error' => $lastError,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Đánh dấu email đã gửi thành công.
     * Gọi bởi EmailQueueController::attemptSend() khi EmailService::send() không throw.
     *
     * @param  int  $id
     * @return void
     */
    public function markAsSent(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE email_queue
             SET status   = :status,
                 sent_at  = NOW(),
                 attempts = attempts + 1
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => 'sent',
            ':id'     => $id,
        ]);
    }

    /**
     * Đánh dấu email gửi lại vẫn thất bại, tăng attempts, lưu lý do lỗi.
     * Gọi bởi EmailQueueController::attemptSend() khi EmailService::send() throw Exception.
     *
     * @param  int    $id
     * @param  string $errorMessage  Message từ PHPMailer exception
     * @return void
     */
    public function markAsFailed(int $id, string $errorMessage): void
    {
        $stmt = $this->db->prepare(
            'UPDATE email_queue
             SET status     = :status,
                 last_error = :error,
                 attempts   = attempts + 1
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => 'failed',
            ':error'  => $errorMessage,
            ':id'     => $id,
        ]);
    }

    /**
     * Tăng attempts trước khi thử gửi lại (tuỳ chọn – có thể gọi thay vì
     * gọi markAsSent/markAsFailed nếu muốn tách bước "đang thử" riêng).
     *
     * Hiện tại EmailQueueController không dùng method này vì markAsSent()
     * và markAsFailed() đã tự tăng attempts. Giữ lại để API đầy đủ.
     *
     * @param  int  $id
     * @return void
     */
    public function incrementAttempts(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE email_queue SET attempts = attempts + 1 WHERE id = :id'
        );

        $stmt->execute([':id' => $id]);
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Lấy danh sách email thất bại có hỗ trợ phân trang.
     *
     * WHY thêm $limit + $offset: EmailQueueController::index() cần phân trang
     * (20 bản ghi/trang), EmailQueueController::retryAll() cần lấy đúng
     * BATCH_SIZE bản ghi để tránh timeout trên InfinityFree.
     *
     * @param  int   $limit   Số bản ghi tối đa trả về (mặc định 50 để backward-compat)
     * @param  int   $offset  Vị trí bắt đầu (mặc định 0)
     * @return array<int, array<string, mixed>>
     */
    public function getFailedEmails(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, to_email, to_name, subject, status, attempts,
                    last_error, created_at, sent_at
             FROM   email_queue
             WHERE  status = :status
             ORDER  BY created_at DESC
             LIMIT  :lim OFFSET :off'
        );

        // bindValue bắt buộc cho LIMIT/OFFSET vì PDO cần kiểu PARAM_INT
        $stmt->bindValue(':status', 'failed',  PDO::PARAM_STR);
        $stmt->bindValue(':lim',    $limit,    PDO::PARAM_INT);
        $stmt->bindValue(':off',    $offset,   PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Đếm tổng số email đang có status = 'failed'.
     *
     * WHY cần method này: EmailQueueController::index() cần tổng số bản ghi
     * để tính tổng trang phân trang (total / per_page).
     * getFailedEmails() chỉ trả về một trang, không biết tổng.
     *
     * @return int
     */
    public function countFailed(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM email_queue WHERE status = :status'
        );

        $stmt->execute([':status' => 'failed']);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Lấy một email cụ thể theo ID (dùng để retry đơn lẻ).
     *
     * @param  int $id
     * @return array<string, mixed>|null  Trả về null nếu không tìm thấy
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM email_queue WHERE id = :id LIMIT 1'
        );

        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    // =========================================================================
    // CLEANUP
    // =========================================================================

    /**
     * Dọn dẹp email queue cũ hơn $daysOld ngày (Admin manual trigger).
     *
     * WHY tách $limit thành tham số riêng thay vì hardcode:
     * EmailQueueController::cleanup() truyền vào 200 (theo TDD Phần 2.4),
     * nhưng trong tương lai có thể điều chỉnh không cần sửa Model.
     *
     * WHY dùng LIMIT: Tránh timeout trên InfinityFree khi queue lớn.
     * Xóa từng batch, Admin bấm lại nhiều lần nếu cần.
     *
     * @param  int $daysOld  Số ngày cũ hơn mức này thì xóa (mặc định 7)
     * @param  int $limit    Số bản ghi tối đa xóa trong một lần (mặc định 200)
     * @return int           Số bản ghi đã xóa thực tế
     */
    public function deleteOldFailed(int $daysOld = 7, int $limit = 200): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM email_queue
             WHERE  status     = :status
               AND  created_at < NOW() - INTERVAL :days DAY
             LIMIT  :lim'
        );

        $stmt->bindValue(':status', 'failed', PDO::PARAM_STR);
        $stmt->bindValue(':days',   $daysOld, PDO::PARAM_INT);
        $stmt->bindValue(':lim',    $limit,   PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}