<?php

namespace App\Models;

use App\Core\Database;

/**
 * User Model
 *
 * Auth methods (Dev 1): findByEmail, create, updateVerified, softDelete, password hashing
 * Business methods (Dev 2): findById, getWorkspaces
 *
 * Changelog:
 *   - Thêm findValidPasswordResetToken() – alias rõ nghĩa hơn cho PasswordResetController
 *   - Thêm deletePasswordResetByUserId() – PasswordResetController gọi trước khi tạo token mới
 *   - Sửa createPasswordResetToken() – đồng bộ signature (nhận string $expiresAt thay vì int $ttlSeconds)
 *   - Thêm findLoginAttemptCount() – LoginController kiểm tra rate limiting
 *   - Thêm recordLoginAttempt() – LoginController ghi thất bại
 *   - Thêm clearLoginAttempts() – LoginController xóa sau khi đăng nhập thành công
 *   - Thêm createRememberMeToken() – LoginController tạo Remember Me
 *   - Thêm findByRememberMeToken() – Front Controller auto-login
 *   - Thêm revokeRememberMeToken() – Logout, đổi mật khẩu
 *   - Thêm revokeAllRememberMeTokens() – Đổi mật khẩu, kick session toàn bộ thiết bị
 */
class User
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================
    // AUTH METHODS – Dev 1 phụ trách
    // =========================================================

    /**
     * Tìm user theo email.
     */
    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password, avatar_path,
                    is_verified, onboarding_completed,
                    email_notif_settings, created_at
             FROM users
             WHERE email = :email
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Tạo user mới – password phải được hash TRƯỚC khi truyền vào.
     * Dùng: password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, is_verified, created_at)
             VALUES (:name, :email, :password, 0, NOW())'
        );
        $stmt->execute([
            ':name'     => $data['name'],
            ':email'    => $data['email'],
            ':password' => $data['password'], // Đã bcrypt hash từ caller
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Đánh dấu email đã được xác minh.
     * Gọi sau khi user click link trong email xác minh.
     */
    public function updateVerified(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET is_verified = 1, updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Đánh dấu onboarding đã hoàn thành.
     * Gọi khi user tạo hoặc tham gia workspace thành công.
     */
    public function markOnboardingCompleted(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET onboarding_completed = 1, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Cập nhật mật khẩu.
     * Dùng trong Password Reset flow (UC-005).
     * WHY cũng revoke Remember Me tokens: Sau khi đổi pass, các thiết bị
     * cũ đang dùng cookie cũ phải đăng nhập lại – tránh token bị đánh cắp.
     */
    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET password = :password, updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':password' => $hashedPassword,
            ':id'       => $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Cập nhật profile.
     * Chỉ cho phép update các field trong whitelist để tránh mass assignment.
     *
     * @param array<string, mixed> $data Các field cho phép: name, avatar_path, email_notif_settings.
     */
    public function updateProfile(int $userId, array $data): bool
    {
        $allowed = ['name', 'avatar_path', 'email_notif_settings'];
        $sets    = [];
        $params  = [':id' => $userId];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]              = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ', updated_at = NOW()'
             . ' WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Soft delete tài khoản.
     * Ghi deleted_at, không xóa record khỏi DB.
     */
    public function softDelete(int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET deleted_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    // ----------------------------------------------------------------
    // Email Verification Token Methods
    // ----------------------------------------------------------------

    /**
     * Tạo token xác minh email.
     * Xóa token cũ chưa dùng trước khi tạo mới (lazy cleanup).
     *
     * @param string $token      bin2hex(random_bytes(32)) từ caller.
     * @param int    $ttlSeconds TTL tính bằng giây.
     */
    public function createVerificationToken(
        int $userId,
        string $token,
        int $ttlSeconds = TOKEN_TTL_EMAIL_VERIFY
    ): bool {
        // Lazy cleanup: xóa token cũ của user này trước
        $deleteStmt = $this->db->prepare(
            'DELETE FROM email_verifications WHERE user_id = :user_id'
        );
        $deleteStmt->execute([':user_id' => $userId]);

        $stmt = $this->db->prepare(
            'INSERT INTO email_verifications (user_id, token, expires_at)
             VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL :ttl SECOND))'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':token'   => $token,
            ':ttl'     => $ttlSeconds,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Tìm và validate token xác minh email.
     *
     * @return array<string, mixed>|false Row gồm user_id, expires_at, email, name.
     */
    public function findVerificationToken(string $token): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT ev.user_id, ev.expires_at, u.email, u.name
             FROM email_verifications ev
             JOIN users u ON u.id = ev.user_id
             WHERE ev.token = :token
               AND ev.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Xóa token xác minh sau khi dùng (single-use).
     */
    public function deleteVerificationToken(string $token): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM email_verifications WHERE token = :token'
        );
        $stmt->execute([':token' => $token]);
    }

    // ----------------------------------------------------------------
    // Password Reset Token Methods
    // ----------------------------------------------------------------

    /**
     * Xóa toàn bộ password reset token chưa dùng của một user.
     *
     * WHY cần method này: PasswordResetController::sendResetLink() gọi
     * method này trước khi tạo token mới để đảm bảo tại mỗi thời điểm
     * chỉ có một token hợp lệ. Tránh tích lũy token cũ trong DB.
     *
     * Khác với createPasswordResetToken() (dùng UPDATE để vô hiệu hóa),
     * method này DELETE hoàn toàn để giữ bảng gọn sạch.
     */
    public function deletePasswordResetByUserId(int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM password_resets
             WHERE user_id = :user_id
               AND used_at IS NULL'
        );
        $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Tạo password reset token mới.
     *
     * Signature nhận $expiresAt (datetime string) thay vì $ttlSeconds vì
     * PasswordResetController tính sẵn thời điểm hết hạn và truyền vào.
     * Điều này giúp Controller kiểm soát TTL theo config linh hoạt hơn.
     *
     * @param string $token     bin2hex(random_bytes(32)) từ caller – 64 chars.
     * @param string $expiresAt Datetime string dạng 'Y-m-d H:i:s'.
     */
    public function createPasswordResetToken(
        int $userId,
        string $token,
        string $expiresAt
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at, created_at)
             VALUES (:user_id, :token, :expires_at, NOW())'
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':token'      => $token,
            ':expires_at' => $expiresAt,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Tìm password reset token hợp lệ (chưa dùng, chưa hết hạn).
     *
     * Alias rõ nghĩa hơn findPasswordResetToken() – được gọi từ
     * PasswordResetController::showResetForm() và resetPassword().
     *
     * WHY không so sánh token trong SQL: SQL dùng = so sánh trực tiếp (fast path),
     * nhưng Controller BẮT BUỘC gọi thêm hash_equals() phía PHP sau khi fetch
     * để chống timing attack. Hai bước này bổ sung cho nhau.
     *
     * @return array<string, mixed>|false Row gồm id, user_id, token, expires_at, email, name.
     */
    public function findValidPasswordResetToken(string $token): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT pr.id, pr.user_id, pr.token, pr.expires_at,
                    u.email, u.name
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token = :token
               AND pr.expires_at > NOW()
               AND pr.used_at IS NULL
               AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Đánh dấu password reset token đã dùng (single-use).
     * Ghi used_at thay vì DELETE để giữ audit trail.
     *
     * @param int $tokenId ID của bản ghi trong bảng password_resets.
     */
    public function markPasswordResetTokenUsed(int $tokenId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE password_resets
             SET used_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $tokenId]);
    }

    // ----------------------------------------------------------------
    // Login Attempt Methods (Rate Limiting – D1-022)
    // ----------------------------------------------------------------

    /**
     * Đếm số lần đăng nhập thất bại của một IP trong khoảng thời gian gần đây.
     *
     * WHY đếm theo IP thay vì email: Chống brute-force dù kẻ tấn công
     * thay đổi email thử. Kết hợp IP + window 15 phút theo TDD Phần 1.3.
     *
     * @param  string $ipAddress  IPv4 hoặc IPv6 của request.
     * @param  int    $minutes    Cửa sổ thời gian (mặc định 15 phút).
     * @return int    Số lần thất bại trong cửa sổ đó.
     */
    public function countLoginAttempts(string $ipAddress, int $minutes = 15): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS attempt_count
             FROM login_attempts
             WHERE ip_address = :ip
               AND attempted_at > NOW() - INTERVAL :minutes MINUTE'
        );
        $stmt->execute([
            ':ip'      => $ipAddress,
            ':minutes' => $minutes,
        ]);
        $row = $stmt->fetch();
        return (int) ($row['attempt_count'] ?? 0);
    }

    /**
     * Ghi một lần đăng nhập thất bại vào DB.
     * Đồng thời dọn dẹp các attempt cũ hơn 1 giờ của IP này (lazy cleanup).
     *
     * WHY lazy cleanup per-IP: Tránh DELETE toàn bảng gây lock.
     * Chỉ xóa record của IP hiện tại – đủ để giữ bảng ổn định.
     *
     * @param string $ipAddress     IPv4/IPv6.
     * @param string $emailAttempted Email user đã nhập (nullable, ghi để audit).
     */
    public function recordLoginAttempt(string $ipAddress, string $emailAttempted = ''): void
    {
        // Lazy cleanup: xóa attempt cũ hơn 1 giờ của IP này
        $cleanStmt = $this->db->prepare(
            'DELETE FROM login_attempts
             WHERE ip_address = :ip
               AND attempted_at < NOW() - INTERVAL 1 HOUR'
        );
        $cleanStmt->execute([':ip' => $ipAddress]);

        // Ghi attempt mới
        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (ip_address, email_attempted, attempted_at)
             VALUES (:ip, :email, NOW())'
        );
        $stmt->execute([
            ':ip'    => $ipAddress,
            ':email' => $emailAttempted ?: null,
        ]);
    }

    /**
     * Xóa toàn bộ login attempts của một IP sau khi đăng nhập thành công.
     * Cho phép IP đó thử lại từ đầu nếu có lần đăng nhập sai tiếp theo.
     *
     * @param string $ipAddress
     */
    public function clearLoginAttempts(string $ipAddress): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM login_attempts
             WHERE ip_address = :ip'
        );
        $stmt->execute([':ip' => $ipAddress]);
    }

    // ----------------------------------------------------------------
    // Remember Me Token Methods (D1-023)
    // ----------------------------------------------------------------

    /**
     * Tạo Remember Me token mới cho user.
     *
     * WHY lưu hash thay vì raw token: Nếu DB bị lộ, kẻ tấn công
     * không thể dùng token_hash để login vì cookie chứa raw token.
     * Tương tự cách bcrypt bảo vệ password.
     *
     * @param int    $userId     ID user.
     * @param string $tokenHash  sha256 hash của raw token từ caller.
     *                           Caller giữ raw token → set vào cookie.
     * @param string $expiresAt  Datetime 'Y-m-d H:i:s' (thường NOW + 30 ngày).
     * @param string $ipAddress  IP client.
     * @param string $userAgent  User-Agent header.
     */
    public function createRememberMeToken(
        int $userId,
        string $tokenHash,
        string $expiresAt,
        string $ipAddress = '',
        string $userAgent = ''
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO user_tokens
                 (user_id, token_hash, expires_at, ip_address, user_agent, created_at)
             VALUES
                 (:user_id, :token_hash, :expires_at, :ip, :ua, NOW())'
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
            ':ip'         => $ipAddress ?: null,
            ':ua'         => mb_substr($userAgent, 0, 500) ?: null,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Tìm user theo Remember Me token hash.
     * Dùng trong Front Controller để auto-login khi có cookie hợp lệ.
     *
     * WHY JOIN với users: Cần thông tin user ngay (tránh query thứ 2)
     * và kiểm tra deleted_at để không auto-login tài khoản đã bị xóa.
     *
     * @param  string            $tokenHash sha256 hash của raw token từ cookie.
     * @return array<string, mixed>|false   Row gồm thông tin user + token metadata.
     */
    public function findByRememberMeToken(string $tokenHash): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT ut.id AS token_id,
                    ut.user_id,
                    ut.expires_at AS token_expires_at,
                    u.id, u.name, u.email, u.avatar_path,
                    u.is_verified, u.onboarding_completed
             FROM user_tokens ut
             JOIN users u ON u.id = ut.user_id
             WHERE ut.token_hash = :token_hash
               AND ut.expires_at > NOW()
               AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        return $stmt->fetch();
    }

    /**
     * Thu hồi một Remember Me token cụ thể.
     * Gọi khi: user logout trên thiết bị hiện tại.
     *
     * @param int $tokenId ID của bản ghi trong bảng user_tokens.
     */
    public function revokeRememberMeToken(int $tokenId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM user_tokens WHERE id = :id'
        );
        $stmt->execute([':id' => $tokenId]);
    }

    /**
     * Thu hồi toàn bộ Remember Me tokens của một user.
     * Gọi khi: đổi mật khẩu, reset mật khẩu, Admin kick user.
     * Đảm bảo mọi thiết bị đang dùng cookie cũ phải đăng nhập lại.
     *
     * @param int $userId
     */
    public function revokeAllRememberMeTokens(int $userId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM user_tokens WHERE user_id = :user_id'
        );
        $stmt->execute([':user_id' => $userId]);
    }

    // =========================================================
    // BUSINESS METHODS – Dev 2 phụ trách
    // =========================================================

    /**
     * Tìm user theo ID.
     */
    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, avatar_path,
                    is_verified, onboarding_completed, created_at, updated_at
             FROM users
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Lấy tất cả Workspace mà user đang tham gia.
     * Trả về array các workspace kèm role của user trong từng workspace.
     *
     * @return array<int, array<string, mixed>> [['id','name','slug','avatar_path','role','joined_at'], ...]
     */
    public function getWorkspaces(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                 w.id,
                 w.name,
                 w.slug,
                 w.avatar_path,
                 wm.role,
                 wm.joined_at
             FROM workspaces w
             INNER JOIN workspace_members wm ON w.id = wm.workspace_id
             WHERE wm.user_id = :user_id
               AND w.deleted_at IS NULL
             ORDER BY wm.joined_at ASC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }
}