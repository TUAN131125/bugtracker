<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Model LoginAttempt
 *
 * Quản lý bảng login_attempts để phục vụ Rate Limiting khi đăng nhập.
 * Tất cả query đều có INDEX trên (ip_address, attempted_at) theo TDD Phần 2.3.
 *
 * @author  Dev 1
 * @version 1.0.0
 */
class LoginAttempt
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Đếm số lần đăng nhập thất bại của một IP trong N phút gần nhất.
     *
     * @param  string $ip_address  IPv4 hoặc IPv6
     * @param  int    $minutes     Khoảng thời gian cần kiểm tra (mặc định 15 phút)
     * @return int    Số lần thất bại
     */
    public function countRecentAttempts(string $ip_address, int $minutes = 15): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) 
             FROM login_attempts 
             WHERE ip_address = :ip 
               AND attempted_at > NOW() - INTERVAL :minutes MINUTE"
        );
        $stmt->bindValue(':ip', $ip_address, PDO::PARAM_STR);
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Ghi nhận một lần đăng nhập thất bại.
     * Đồng thời dọn dẹp các bản ghi cũ hơn 1 giờ của cùng IP
     * để tránh bảng phình to (lazy cleanup theo TDD Phần 2.4).
     *
     * @param  string      $ip_address      IP thực hiện đăng nhập
     * @param  string|null $email_attempted Email được thử (có thể null nếu không xác định)
     * @return void
     */
    public function recordAttempt(string $ip_address, ?string $email_attempted = null): void
    {
        // Lazy cleanup: xóa các bản ghi cũ hơn 1 giờ của IP này
        // Theo TDD Phần 2.4: chỉ xóa của IP đó, tránh xóa bảng lớn
        $cleanup = $this->db->prepare(
            "DELETE FROM login_attempts 
             WHERE ip_address = :ip 
               AND attempted_at < NOW() - INTERVAL 1 HOUR"
        );
        $cleanup->bindValue(':ip', $ip_address, PDO::PARAM_STR);
        $cleanup->execute();

        // Insert bản ghi thất bại mới
        $stmt = $this->db->prepare(
            "INSERT INTO login_attempts (ip_address, email_attempted, attempted_at) 
             VALUES (:ip, :email, NOW())"
        );
        $stmt->bindValue(':ip', $ip_address, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email_attempted, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Kiểm tra IP có đang bị khóa hay không (>= 5 lần thất bại trong 15 phút).
     *
     * Ngưỡng và khoảng thời gian cứng theo SRS Phần 3.1.2 và Task D1-014.
     *
     * @param  string $ip_address
     * @return bool   true = đang bị khóa
     */
    public function isLocked(string $ip_address): bool
    {
        return $this->countRecentAttempts($ip_address, 15) >= 5;
    }

    /**
     * Xóa toàn bộ lịch sử thất bại của một IP sau khi đăng nhập thành công.
     * Gọi trong LoginController sau khi xác thực thành công.
     *
     * @param  string $ip_address
     * @return void
     */
    public function clearAttemptsForIp(string $ip_address): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM login_attempts WHERE ip_address = :ip"
        );
        $stmt->bindValue(':ip', $ip_address, PDO::PARAM_STR);
        $stmt->execute();
    }
}