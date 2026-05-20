<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Core\Database;
use App\Core\Session;
use App\Models\User;
use App\Models\LoginAttempt;
use App\Models\UserToken;
use App\Models\EmailQueue;

/**
 * AuthService
 *
 * Tầng Service chứa toàn bộ business logic xác thực.
 * Controller Auth chỉ được gọi Service này, KHÔNG viết logic trực tiếp.
 *
 * Trách nhiệm:
 *  - register()                 : validate + insert user + gửi email verify
 *  - verifyEmail()              : xác minh token từ link email
 *  - login()                    : kiểm tra credential + trạng thái tài khoản
 *  - logout()                   : hủy session + thu hồi remember me token
 *  - resendVerificationEmail()  : gửi lại email xác minh
 *
 * @author  Dev 1
 * @version 1.0.0
 * @see     SRS v1.0.0 – UC-001, UC-002, UC-003, UC-004, UC-042
 * @see     TDD Backend v1.0.0 – Phần 1.3 (Email graceful degradation)
 * @see     Task Assignment v1.0.0 – D1-025
 */
class AuthService
{
    private User         $user_model;
    private LoginAttempt $login_attempt;
    private UserToken    $user_token;
    private EmailService $email_service;
    private PDO          $db;

    public function __construct()
    {
        $this->user_model    = new User();
        $this->login_attempt = new LoginAttempt();
        $this->user_token    = new UserToken();
        $this->email_service = new EmailService();
        $this->db            = Database::getInstance();
    }

    // =========================================================================
    // REGISTER
    // =========================================================================

    /**
     * Đăng ký tài khoản mới.
     *
     * Luồng theo SRS UC-001:
     *   1. Validate input
     *   2. Kiểm tra email unique
     *   3. Hash password + insert users
     *   4. Sinh verify token + insert email_verifications
     *   5. Gửi email qua EmailService (graceful degradation nếu SMTP lỗi)
     *
     * @param  array<string, string> $data  ['name', 'email', 'password', 'password_confirm']
     * @return array{success: bool, errors: array, user_id: int|null}
     */
    public function register(array $data): array
    {
        // --- Validate ---
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'user_id' => null];
        }

        // --- Kiểm tra email unique ---
        if ($this->user_model->findByEmail($data['email']) !== null) {
            return [
                'success' => false,
                'errors'  => ['email' => 'Email này đã được đăng ký. Vui lòng đăng nhập hoặc dùng email khác.'],
                'user_id' => null,
            ];
        }

        // --- Insert user ---
        // bcrypt cost=12 theo TDD Phần 1.4 và SRS Phần 3.1.1
        $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $user_id = $this->user_model->create([
            'name'     => trim($data['name']),
            'email'    => strtolower(trim($data['email'])),
            'password' => $hashed_password,
        ]);

        // --- Sinh và lưu verification token ---
        // TTL lấy từ config constant (config.php D1-010), không hardcode
        $raw_token  = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + TOKEN_TTL_EMAIL_VERIFY);

        $stmt = $this->db->prepare(
            'INSERT INTO email_verifications (user_id, token, expires_at, created_at)
             VALUES (:uid, :token, :expires, NOW())'
        );
        $stmt->bindValue(':uid',     $user_id,    PDO::PARAM_INT);
        $stmt->bindValue(':token',   $raw_token,  PDO::PARAM_STR);
        $stmt->bindValue(':expires', $expires_at, PDO::PARAM_STR);
        $stmt->execute();

        // --- Gửi email xác minh (graceful degradation theo TDD Phần 1.3) ---
        $this->sendVerificationEmail($user_id, $data['email'], $data['name'], $raw_token);

        return ['success' => true, 'errors' => [], 'user_id' => $user_id];
    }

    // =========================================================================
    // VERIFY EMAIL
    // =========================================================================

    /**
     * Xác minh email bằng token từ link.
     *
     * Luồng theo SRS UC-002:
     *   1. Tìm token trong email_verifications
     *   2. Kiểm tra chưa hết hạn
     *   3. Dùng hash_equals() chống timing attack
     *   4. Cập nhật is_verified=1 cho user
     *   5. Xóa bản ghi token (single-use)
     *
     * @param  string $token  Raw token từ URL.
     * @return array{success: bool, message: string}
     */
    public function verifyEmail(string $token): array
    {
        $stmt = $this->db->prepare(
            'SELECT ev.*, u.email
             FROM email_verifications ev
             JOIN users u ON u.id = ev.user_id
             WHERE ev.token = :token
               AND ev.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        $record = $stmt->fetch();

        if ($record === false) {
            // Phân biệt token hết hạn và token không tồn tại
            $stmt2 = $this->db->prepare(
                'SELECT id FROM email_verifications WHERE token = :token LIMIT 1'
            );
            $stmt2->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt2->execute();

            return $stmt2->fetch() !== false
                ? ['success' => false, 'message' => 'link_expired']
                : ['success' => false, 'message' => 'link_invalid'];
        }

        // hash_equals() chống timing attack theo TDD Phần 1.4.3
        if (!hash_equals($record['token'], $token)) {
            return ['success' => false, 'message' => 'link_invalid'];
        }

        // Cập nhật is_verified = 1
        $this->user_model->updateVerified((int) $record['user_id']);

        // Xóa token (single-use theo TDD Phần 1.4.2)
        $del = $this->db->prepare(
            'DELETE FROM email_verifications WHERE id = :id'
        );
        $del->bindValue(':id', (int) $record['id'], PDO::PARAM_INT);
        $del->execute();

        return ['success' => true, 'message' => 'verified'];
    }

    // =========================================================================
    // LOGIN
    // =========================================================================

    /**
     * Xác thực credential đăng nhập.
     *
     * Chỉ kiểm tra credential và trạng thái tài khoản.
     * Rate limiting được xử lý ở LoginController (LoginAttempt model).
     *
     * @param  string     $email
     * @param  string     $password
     * @return array|null Bản ghi user nếu thành công.
     *                    ['__error' => 'not_verified'] nếu chưa xác minh.
     *                    null nếu sai credential hoặc tài khoản bị xóa.
     */
    public function login(string $email, string $password): ?array
    {
        $user = $this->user_model->findByEmail(strtolower(trim($email)));

        if ($user === null) {
            return null;
        }

        // Kiểm tra soft delete
        if ($user['deleted_at'] !== null) {
            return null;
        }

        // Kiểm tra email đã xác minh chưa
        // Trả về mảng đặc biệt để Controller phân biệt lý do thất bại
        if ((int) $user['is_verified'] === 0) {
            return ['__error' => 'not_verified', 'email' => $user['email'], 'id' => $user['id']];
        }

        // Verify password — KHÔNG dùng == hay ===
        if (!password_verify($password, $user['password'])) {
            return null;
        }

        return $user;
    }

    // =========================================================================
    // LOGOUT
    // =========================================================================

    /**
     * Đăng xuất: hủy session, thu hồi Remember Me token nếu có.
     *
     * @param  string|null $remember_token  Raw token từ cookie (có thể null).
     * @return void
     */
    public function logout(?string $remember_token = null): void
    {
        if ($remember_token !== null) {
            $token_record = $this->user_token->findByRawToken($remember_token);
            if ($token_record !== null) {
                $this->user_token->revoke((int) $token_record['id']);
            }
        }

        Session::destroy();
    }

    // =========================================================================
    // RESEND VERIFICATION EMAIL
    // =========================================================================

    /**
     * Gửi lại email xác minh cho user chưa xác minh.
     *
     * Lazy cleanup theo TDD Phần 2.4: xóa token hết hạn của user trước
     * khi tạo token mới — tránh tồn tại nhiều token cho cùng 1 user.
     *
     * @param  string $email
     * @return array{success: bool, message: string}
     */
    public function resendVerificationEmail(string $email): array
    {
        $user = $this->user_model->findByEmail(strtolower(trim($email)));

        if ($user === null) {
            // Không thông báo user không tồn tại (chống user enumeration)
            return ['success' => true, 'message' => 'sent_if_exists'];
        }

        if ((int) $user['is_verified'] === 1) {
            return ['success' => false, 'message' => 'already_verified'];
        }

        // Lazy cleanup: xóa token hết hạn của user này (TDD Phần 2.4)
        $del = $this->db->prepare(
            'DELETE FROM email_verifications
             WHERE user_id = :uid AND expires_at < NOW()'
        );
        $del->bindValue(':uid', (int) $user['id'], PDO::PARAM_INT);
        $del->execute();

        // Tạo token mới — TTL từ config constant
        $raw_token  = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + TOKEN_TTL_EMAIL_VERIFY);

        $stmt = $this->db->prepare(
            'INSERT INTO email_verifications (user_id, token, expires_at, created_at)
             VALUES (:uid, :token, :expires, NOW())'
        );
        $stmt->bindValue(':uid',     (int) $user['id'], PDO::PARAM_INT);
        $stmt->bindValue(':token',   $raw_token,        PDO::PARAM_STR);
        $stmt->bindValue(':expires', $expires_at,       PDO::PARAM_STR);
        $stmt->execute();

        $this->sendVerificationEmail((int) $user['id'], $user['email'], $user['name'], $raw_token);

        return ['success' => true, 'message' => 'sent_if_exists'];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Validate dữ liệu đăng ký theo SRS UC-001.
     *
     * @param  array<string, string> $data
     * @return array<string, string> Mảng lỗi keyed by field name, rỗng nếu hợp lệ.
     */
    private function validateRegistration(array $data): array
    {
        $errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = 'Họ tên không được để trống.';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Địa chỉ email không hợp lệ.';
        }

        $password = $data['password'] ?? '';
        if (strlen($password) < 8) {
            $errors['password'] = 'Mật khẩu phải có ít nhất 8 ký tự.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors['password'] = 'Mật khẩu phải có ít nhất 1 chữ hoa.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Mật khẩu phải có ít nhất 1 chữ số.';
        }

        if (($data['password'] ?? '') !== ($data['password_confirm'] ?? '')) {
            $errors['password_confirm'] = 'Xác nhận mật khẩu không khớp.';
        }

        return $errors;
    }

    /**
     * Gửi email xác minh với graceful degradation theo TDD Phần 1.3.
     *
     * Nếu SMTP lỗi: ghi log + insert email_queue.
     * KHÔNG throw exception lên caller — luồng đăng ký không bị ảnh hưởng.
     *
     * @param  int    $user_id
     * @param  string $email
     * @param  string $name
     * @param  string $raw_token
     * @return void
     */
    private function sendVerificationEmail(
        int    $user_id,
        string $email,
        string $name,
        string $raw_token
    ): void {
        // URL từ APP_URL constant (config.php D1-010) — không hardcode
        $verify_url = rtrim(APP_URL, '/') . '/verify-email?token=' . $raw_token;

        // Render HTML email template
        // VIEWS_PATH constant định nghĩa trong config.php (D1-010)
        ob_start();
        $user_name     = $name;
        $expires_hours = (int) (TOKEN_TTL_EMAIL_VERIFY / 3600);
        include VIEWS_PATH . '/emails/verify-email.php';
        $html_body = (string) ob_get_clean();

        $subject = 'Xác minh tài khoản BugTracker của bạn';

        try {
            $this->email_service->send($email, $name, $subject, $html_body);

        } catch (\Exception $e) {
            // Graceful degradation theo TDD Phần 1.3 Lớp 2 & 3:
            //   - Không throw lên Controller
            //   - Ghi log kỹ thuật
            //   - Insert vào email_queue để Admin retry thủ công

            // TODO: Replace bằng Logger instance sau khi D1-021 hoàn thành (Ngày 3)
            // Logger là instance class — KHÔNG gọi Logger::error() kiểu static
            error_log(sprintf(
                '[AuthService] Verification email send failed | User: %d | Email: %s | Error: %s | Trace: %s',
                $user_id,
                $email,
                $e->getMessage(),
                // getTraceAsString() trả về string — đúng type, giới hạn theo TDD Phần 4.2
                substr($e->getTraceAsString(), 0, 2000)
            ));

            // Insert vào email_queue để Admin retry từ /admin/email-queue
            $queue = new EmailQueue();
            $queue->insert($email, $name, $subject, $html_body, 'failed');
        }
    }
}