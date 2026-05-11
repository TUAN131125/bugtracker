<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database – PDO Singleton Connection Manager
 *
 * Cung cấp một điểm truy cập PDO duy nhất cho toàn bộ ứng dụng.
 * Tuân thủ mô hình Singleton để tránh tạo nhiều kết nối DB trên
 * cùng một request — quan trọng trên InfinityFree Shared Hosting
 * vì số lượng concurrent connection bị giới hạn nghiêm ngặt.
 *
 * Yêu cầu các biến môi trường trong file .env:
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
 *
 * @package    App\Core
 * @version    1.0.0
 * @see        TDD Backend v1.0.0 – Phần 2.1 (Multi-tenant), Phần 3.2 (Cấu trúc thư mục)
 * @see        Task Assignment v1.0.0 – D1-005
 */
class Database
{
    /**
     * Instance PDO duy nhất của toàn ứng dụng (Singleton).
     *
     * @var PDO|null
     */
    private static ?PDO $instance = null;

    /**
     * Ngăn chặn việc khởi tạo class từ bên ngoài.
     * Singleton pattern: chỉ được truy cập qua getInstance().
     */
    private function __construct()
    {
    }

    /**
     * Ngăn chặn clone instance.
     */
    private function __clone()
    {
    }

    /**
     * Trả về instance PDO duy nhất. Tạo mới nếu chưa tồn tại.
     *
     * Các PDO options được thiết lập theo TDD Backend v1.0.0:
     * - ERRMODE_EXCEPTION : Mọi lỗi DB đều throw PDOException, không âm thầm fail
     * - FETCH_ASSOC       : Mặc định trả về associative array, không trả về indexed array
     * - EMULATE_PREPARES  : false — dùng native prepared statements của MySQL
     *                       (bảo mật hơn, tránh edge-case SQL injection)
     *
     * @return PDO Instance PDO đã được cấu hình đầy đủ.
     *
     * @throws RuntimeException Khi biến môi trường DB bị thiếu hoặc không hợp lệ.
     * @throws PDOException     Khi không thể kết nối đến MySQL server.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    /**
     * Tạo kết nối PDO mới với MySQL.
     *
     * Tách riêng khỏi getInstance() để dễ unit test và để logic
     * tạo kết nối không bị lẫn với logic Singleton.
     *
     * @return PDO
     *
     * @throws RuntimeException Khi biến môi trường bị thiếu.
     * @throws PDOException     Khi MySQL từ chối kết nối.
     */
    private static function createConnection(): PDO
    {
        // ----------------------------------------------------------------
        // Bước 1: Đọc và validate biến môi trường
        // Phpdotenv đã load .env vào $_ENV trước khi class này được gọi
        // (xem public_html/index.php – Task D1-009)
        // ----------------------------------------------------------------
        $host    = $_ENV['DB_HOST'] ?? 'localhost';
        $port    = $_ENV['DB_PORT'] ?? '3306';
        $name  = $_ENV['DB_NAME'] ?? 'bugtracker_db';
        $user    = $_ENV['DB_USER'] ?? 'root';
        $pass    = $_ENV['DB_PASS'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        // ----------------------------------------------------------------
        // Bước 2: Xây dựng DSN
        // Dùng charset trong DSN + PDO::MYSQL_ATTR_INIT_COMMAND để đảm
        // bảo utf8mb4 được áp dụng ngay từ đầu connection — quan trọng
        // cho tiếng Việt, emoji trong comment/reaction (TDD Phần 2.2)
        // ----------------------------------------------------------------
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $name,
            $charset
        );

        // ----------------------------------------------------------------
        // Bước 3: PDO Options — theo đặc tả TDD Backend D1-005
        // ----------------------------------------------------------------
        $options = [
            // Mọi lỗi DB sẽ throw PDOException → bắt được qua try-catch
            // trong Model. Không để lỗi âm thầm theo kiểu ERRMODE_SILENT.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // Mặc định trả về associative array. Model không cần gọi
            // fetch(PDO::FETCH_ASSOC) mỗi lần nữa.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Dùng native prepared statements của MySQL thay vì emulation.
            // Bảo mật hơn vì MySQL server tự xử lý parameterized query,
            // tránh edge-case khi charset conversion gây SQL injection.
            PDO::ATTR_EMULATE_PREPARES   => false,

            // Đảm bảo charset được set ngay sau khi connect thành công.
            // Là lớp bảo vệ thứ hai sau charset trong DSN — cần thiết
            // vì một số phiên bản MySQL driver bỏ qua charset trong DSN.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",

            // Persistent connection: TẮT trên InfinityFree.
            // Shared Hosting không quản lý connection pool đúng cách,
            // persistent connection dễ gây "too many connections" error.
            PDO::ATTR_PERSISTENT         => false,
        ];

        // ----------------------------------------------------------------
        // Bước 4: Tạo kết nối — bọc trong try-catch để log rõ ràng
        // ----------------------------------------------------------------
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // KHÔNG để lộ credentials hay DSN ra ngoài.
            // Ghi log chi tiết vào Logger (Dev 1 implement Logger ở D1-021),
            // nhưng message throw ra chỉ chứa thông tin tối thiểu.
            //
            // Tạm thời dùng error_log cho Ngày 1 (Logger chưa có).
            // Dev 1 sẽ replace dòng này bằng Logger::critical() ở Ngày 3.
            error_log(sprintf(
                '[BugTracker][Database] PDO connection failed. Host: %s | DB: %s | Error: %s',
                $host,
                $name,
                $e->getMessage()
            ));

            // Throw RuntimeException thay vì PDOException để tầng trên
            // (global exception handler trong index.php) bắt được và
            // hiển thị trang 500 thân thiện, không lộ stack trace.
            throw new RuntimeException(
                'Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.',
                (int) $e->getCode(),
                $e  // Preserve original exception làm "previous" để debug nội bộ
            );
        }

        return $pdo;
    }

    /**
     * Đọc biến môi trường bắt buộc từ $_ENV.
     *
     * Phpdotenv load .env vào $_ENV (không phải getenv()) vì trên
     * InfinityFree, getenv() đôi khi bị chặn ở tầng server config.
     * Dùng $_ENV trực tiếp là cách an toàn nhất.
     *
     * @param  string      $key     Tên biến môi trường.
     * @param  string|null $default Giá trị mặc định nếu biến không tồn tại.
     *                              Nếu null và biến không tồn tại → throw exception.
     * @return string
     *
     * @throws RuntimeException Khi biến bắt buộc không được định nghĩa trong .env
     */
    private static function requireEnv(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? $default;

        if ($value === null) {
            throw new RuntimeException(
                "Biến môi trường bắt buộc '{$key}' chưa được định nghĩa trong file .env. "
                . 'Hãy kiểm tra file .env.example để biết danh sách biến cần thiết.'
            );
        }

        return (string) $value;
    }

    /**
     * Reset Singleton instance.
     *
     * CHỈ dùng trong môi trường testing (PHPUnit).
     * KHÔNG gọi method này trong production code.
     *
     * @internal
     * @return void
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}