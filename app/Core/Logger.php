<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Logger – Hệ thống ghi log 2 tầng.
 *
 * Tầng 1 (Primary): Ghi vào bảng system_logs trong DB.
 *   → Admin xem qua trang /admin/system-logs
 *   → Có thể filter theo level, context, thời gian
 *
 * Tầng 2 (Fallback): Ghi vào /storage/logs/app-YYYY-MM.log
 *   → Dùng khi DB không available
 *   → Rotate theo tháng để tránh file quá lớn (InfinityFree Inode limit)
 *
 * QUAN TRỌNG – Security:
 *   KHÔNG bao giờ log: password, raw token, session ID, credit card.
 *   Nếu cần debug auth, log user_id hoặc email (đã hash) thay vì credentials.
 *
 * WHY 2 tầng: InfinityFree ẩn Apache/PHP error_log. Developer cần
 *   một nơi có thể query và xem log mà không cần SSH.
 */
class Logger
{
    // Level constants – theo thứ tự tăng dần độ nghiêm trọng
    public const DEBUG    = 'DEBUG';
    public const INFO     = 'INFO';
    public const WARNING  = 'WARNING';
    public const ERROR    = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    // Độ dài tối đa của stack trace lưu vào DB (tránh làm phình bảng)
    private const MAX_TRACE_LENGTH = 2000;

    // Độ dài tối đa của log line ghi vào file
    private const MAX_FILE_LINE_LENGTH = 4000;

    private ?\PDO $db = null;

    public function __construct()
    {
        // Lazy-load DB để tránh circular dependency khi DB chưa khởi tạo
        try {
            $this->db = Database::getInstance();
        } catch (\Throwable $e) {
            // DB không available – Logger vẫn hoạt động qua file
            $this->db = null;
        }
    }

    // =========================================================================
    // Public API – 5 level methods
    // =========================================================================

    /**
     * Ghi log DEBUG – chỉ dùng trong development, không dùng production.
     */
    public function debug(string $message, string $context = '', string $trace = ''): void
    {
        $this->log(self::DEBUG, $message, $context, $trace);
    }

    /**
     * Ghi log INFO – sự kiện bình thường đáng ghi nhận.
     * VD: Email sent successfully, User logged in.
     */
    public function info(string $message, string $context = '', string $trace = ''): void
    {
        $this->log(self::INFO, $message, $context, $trace);
    }

    /**
     * Ghi log WARNING – không phải lỗi nhưng cần chú ý.
     * VD: Rate limit gần đạt ngưỡng, file upload gần đến limit.
     */
    public function warning(string $message, string $context = '', string $trace = ''): void
    {
        $this->log(self::WARNING, $message, $context, $trace);
    }

    /**
     * Ghi log ERROR – lỗi xảy ra nhưng hệ thống vẫn chạy được.
     * VD: SMTP fail, file upload fail, DB query lỗi nhưng đã catch.
     */
    public function error(string $message, string $context = '', string $trace = ''): void
    {
        $this->log(self::ERROR, $message, $context, $trace);
    }

    /**
     * Ghi log CRITICAL – lỗi nghiêm trọng, cần xử lý ngay.
     * VD: DB connection fail hoàn toàn, uncaught exception.
     */
    public function critical(string $message, string $context = '', string $trace = ''): void
    {
        $this->log(self::CRITICAL, $message, $context, $trace);
    }

    // =========================================================================
    // Core logic
    // =========================================================================

    /**
     * Ghi log vào DB (tầng 1) và file (tầng 2 hoặc fallback).
     *
     * @param string $level   Một trong 5 constants ở trên
     * @param string $message Nội dung log (KHÔNG chứa password/token)
     * @param string $context Tên class/service gọi logger (VD: 'EmailService')
     * @param string $trace   Stack trace (nếu có), sẽ bị truncate
     */
    private function log(
        string $level,
        string $message,
        string $context,
        string $trace
    ): void {
        // Sanitize: Truncate trace để tránh làm phình DB
        $truncatedTrace = strlen($trace) > self::MAX_TRACE_LENGTH
            ? substr($trace, 0, self::MAX_TRACE_LENGTH) . '... [truncated]'
            : $trace;

        // Tầng 1: Ghi vào DB
        $dbSuccess = $this->writeToDatabase($level, $message, $context, $truncatedTrace);

        // Tầng 2: Ghi vào file (luôn ghi, không chỉ khi DB fail)
        // WHY luôn ghi cả 2: File log giúp debug ngay cả khi DB query chậm
        // và là backup khi bảng system_logs bị lock hoặc đầy.
        $this->writeToFile($level, $message, $context, $truncatedTrace);
    }

    /**
     * Ghi vào bảng system_logs (Tầng 1 – Primary).
     *
     * @return bool true nếu ghi thành công
     */
    private function writeToDatabase(
        string $level,
        string $message,
        string $context,
        string $trace
    ): bool {
        if ($this->db === null) {
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO system_logs (level, context, message, trace, created_at)
                 VALUES (:level, :context, :message, :trace, NOW())'
            );

            $stmt->execute([
                ':level'   => $level,
                ':context' => $context ?: null,
                ':message' => $message,
                ':trace'   => $trace ?: null,
            ]);

            return true;

        } catch (\Throwable $e) {
            // DB fail → chỉ ghi file, không throw để tránh vòng lặp lỗi
            $this->writeToFile(
                self::ERROR,
                "Logger DB write failed: " . $e->getMessage(),
                'Logger',
                ''
            );
            return false;
        }
    }

    /**
     * Ghi vào file log (Tầng 2 – Fallback + Backup).
     *
     * File path: /storage/logs/app-YYYY-MM.log
     * Format: [YYYY-MM-DD HH:MM:SS] [LEVEL] [context] message \n trace
     *
     * WHY rotate theo tháng: Tránh 1 file quá lớn + giảm Inode sử dụng.
     * Admin tự xóa file cũ qua FTP khi cần dọn dẹp.
     */
    private function writeToFile(
        string $level,
        string $message,
        string $context,
        string $trace
    ): void {
        try {
            $logDir  = PROJECT_ROOT . '/storage/logs';
            $logFile = $logDir . '/app-' . date('Y-m') . '.log';

            // Tạo thư mục nếu chưa có (lần đầu chạy)
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            // Format log line
            $contextPart = $context ? "[{$context}] " : '';
            $line = sprintf(
                "[%s] [%s] %s%s\n",
                date('Y-m-d H:i:s'),
                $level,
                $contextPart,
                $message
            );

            // Thêm trace nếu có (indent để dễ đọc)
            if (!empty($trace)) {
                $line .= "    TRACE: " . str_replace(
                    "\n",
                    "\n    ",
                    $trace
                ) . "\n";
            }

            // Truncate line nếu quá dài
            if (strlen($line) > self::MAX_FILE_LINE_LENGTH) {
                $line = substr($line, 0, self::MAX_FILE_LINE_LENGTH) . "...\n";
            }

            // FILE_APPEND + LOCK_EX để tránh race condition khi concurrent requests
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        } catch (\Throwable $e) {
            // Không thể làm gì thêm nếu cả file cũng fail
            // error_log cuối cùng để PHP ghi vào server log (nếu server cho phép)
            error_log('[BugTracker Logger] Cannot write to log file: ' . $e->getMessage());
        }
    }
}