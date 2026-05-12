<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * EmailQueue Model – quản lý hàng đợi email thất bại.
 *
 * WHY lưu body_html thay vì lưu tham số:
 * Khi retry, chỉ cần đọc body_html và gửi lại mà không phải rebuild template.
 * Tránh trường hợp token/data đã thay đổi giữa lần gửi đầu và lần retry.
 *
 * WHY không có cronjob: InfinityFree không hỗ trợ. Admin retry thủ công
 * qua trang /admin/email-queue (EmailQueueController – D1-024).
 */
class EmailQueue
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Thêm một email vào hàng đợi (thường là khi SMTP fail).
     *
     * @param  string $to        Email người nhận
     * @param  string $toName    Tên người nhận
     * @param  string $subject   Tiêu đề
     * @param  string $bodyHtml  HTML đã render sẵn
     * @param  string $status    'pending' | 'failed'
     * @param  string $lastError Message lỗi từ PHPMailer (nếu có)
     * @return int    ID bản ghi mới tạo
     */
    public function insert(
        string $to,
        string $toName,
        string $subject,
        string $bodyHtml,
        string $status = 'pending',
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
     * Lấy danh sách email thất bại để Admin review/retry.
     * Giới hạn 50 bản ghi để tránh query quá nặng trên InfinityFree.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFailedEmails(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, to_email, to_name, subject, status, attempts,
                    last_error, created_at, sent_at
             FROM email_queue
             WHERE status = :status
             ORDER BY created_at DESC
             LIMIT 50'
        );

        $stmt->execute([':status' => 'failed']);
        return $stmt->fetchAll();
    }

    /**
     * Lấy một email cụ thể theo ID (để retry).
     *
     * @param  int $id
     * @return array<string, mixed>|null
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

    /**
     * Đánh dấu email đã gửi thành công.
     *
     * @param int $id
     */
    public function markAsSent(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE email_queue
             SET status = :status, sent_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => 'sent',
            ':id'     => $id,
        ]);
    }

    /**
     * Đánh dấu email gửi lại vẫn thất bại, tăng attempts.
     *
     * @param int    $id
     * @param string $errorMessage
     */
    public function markAsFailed(int $id, string $errorMessage): void
    {
        $stmt = $this->db->prepare(
            'UPDATE email_queue
             SET status = :status,
                 last_error = :error,
                 attempts = attempts + 1
             WHERE id = :id'
        );

        $stmt->execute([
            ':status' => 'failed',
            ':error'  => $errorMessage,
            ':id'     => $id,
        ]);
    }

    /**
     * Tăng attempts khi thử gửi lại (gọi trước khi gửi).
     * Dùng tại EmailQueueController::retry().
     *
     * @param int $id
     */
    public function incrementAttempts(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE email_queue SET attempts = attempts + 1 WHERE id = :id'
        );

        $stmt->execute([':id' => $id]);
    }

    /**
     * Dọn dẹp email queue cũ hơn 7 ngày (Admin manual trigger).
     * WHY LIMIT 200: Tránh timeout trên InfinityFree khi queue lớn.
     *
     * @return int Số bản ghi đã xóa
     */
    public function deleteOldFailed(int $daysOld = 7): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM email_queue
             WHERE status = :status
               AND created_at < NOW() - INTERVAL :days DAY
             LIMIT 200'
        );

        $stmt->execute([
            ':status' => 'failed',
            ':days'   => $daysOld,
        ]);

        return $stmt->rowCount();
    }
}