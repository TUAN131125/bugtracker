<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Model UserToken
 *
 * Quản lý Remember Me tokens theo TDD Phần 1.4.1.
 * QUAN TRỌNG: Chỉ lưu hash của token vào DB, không bao giờ lưu raw token.
 * Cookie phía client chứa raw token; DB chứa hash(token).
 *
 * @author  Dev 1
 * @version 1.0.0
 */
class UserToken
{
    private PDO $db;

    // TTL cookie Remember Me: 30 ngày theo TDD Phần 1.4.1
    public const TTL_DAYS = 30;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Tạo một Remember Me token mới cho user.
     *
     * Sinh raw token bằng CSPRNG, hash trước khi lưu DB.
     * Trả về raw token để ghi vào cookie phía client.
     *
     * @param  int    $user_id
     * @param  string $ip_address
     * @param  string $user_agent
     * @return string Raw token (ghi vào cookie, KHÔNG lưu DB)
     */
    public function create(int $user_id, string $ip_address, string $user_agent): string
    {
        // Sinh raw token bằng CSPRNG theo TDD Phần 1.4.1
        $raw_token  = bin2hex(random_bytes(32)); // 64 ký tự hex
        $token_hash = hash('sha256', $raw_token);
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . self::TTL_DAYS . ' days'));

        $stmt = $this->db->prepare(
            "INSERT INTO user_tokens 
                 (user_id, token_hash, expires_at, ip_address, user_agent, created_at) 
             VALUES 
                 (:user_id, :token_hash, :expires_at, :ip, :ua, NOW())"
        );
        $stmt->bindValue(':user_id',    $user_id,    PDO::PARAM_INT);
        $stmt->bindValue(':token_hash', $token_hash, PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expires_at, PDO::PARAM_STR);
        $stmt->bindValue(':ip',         $ip_address, PDO::PARAM_STR);
        $stmt->bindValue(':ua',         $user_agent, PDO::PARAM_STR);
        $stmt->execute();

        return $raw_token; // Caller ghi vào cookie
    }

    /**
     * Tìm bản ghi token hợp lệ bằng raw token từ cookie.
     *
     * Hash raw token rồi so sánh với DB. Chỉ trả về token chưa hết hạn.
     *
     * @param  string     $raw_token Raw token từ cookie
     * @return array|null Bản ghi token hoặc null nếu không tìm thấy / hết hạn
     */
    public function findByRawToken(string $raw_token): ?array
    {
        $token_hash = hash('sha256', $raw_token);

        $stmt = $this->db->prepare(
            "SELECT * FROM user_tokens 
             WHERE token_hash = :hash 
               AND expires_at > NOW() 
             LIMIT 1"
        );
        $stmt->bindValue(':hash', $token_hash, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Thu hồi (xóa) một token cụ thể theo ID.
     * Gọi khi user đăng xuất trên thiết bị hiện tại.
     *
     * @param  int  $token_id
     * @return void
     */
    public function revoke(int $token_id): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_tokens WHERE id = :id"
        );
        $stmt->bindValue(':id', $token_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Thu hồi toàn bộ token của một user.
     * Gọi khi user đổi mật khẩu hoặc yêu cầu đăng xuất tất cả thiết bị.
     *
     * @param  int  $user_id
     * @return void
     */
    public function revokeAllForUser(int $user_id): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_tokens WHERE user_id = :user_id"
        );
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Xóa toàn bộ token đã hết hạn (lazy cleanup).
     * Gọi không thường xuyên, ví dụ sau khi login thành công.
     *
     * @return void
     */
    public function purgeExpired(): void
    {
        $this->db->exec("DELETE FROM user_tokens WHERE expires_at <= NOW()");
    }
}