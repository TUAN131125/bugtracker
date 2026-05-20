<?php

declare(strict_types=1);

/**
 * BugTracker – Front Controller
 *
 * Entry point duy nhất của toàn bộ ứng dụng.
 * Mọi HTTP request đều được .htaccess rewrite về file này.
 *
 * Thứ tự khởi tạo (BẮT BUỘC theo đúng thứ tự này):
 *   1. Autoloader (Composer PSR-4)
 *   2. Load biến môi trường (.env)
 *   3. Cấu hình PHP runtime
 *   4. Đăng ký global exception/error handler
 *   5. Load config.php  ← define tất cả CONSTANT (APP_URL, VIEWS_PATH...)
 *   6. Load functions.php ← dùng CONSTANT từ bước 5, PHẢI sau config.php
 *   7. Khởi tạo Router và dispatch request
 *
 * WHY thứ tự 5 trước 6 là bắt buộc:
 *   functions.php khai báo các helper như asset(), url() dùng constant APP_URL.
 *   Nếu functions.php được require trước config.php, APP_URL chưa được define
 *   → khi asset() được gọi trong View → PHP throw ErrorException (undefined
 *   constant) → set_error_handler bắt và throw → global exception handler
 *   chạy → output buffer bị clear → browser nhận HTML rỗng, <head></head>
 *   không có CSS nào.
 *
 * @see TDD Backend v1.0.0 – Phần 3.2, Phần 3.3
 * @see Task Assignment v1.0.0 – D1-009
 */

// ----------------------------------------------------------------
// Bước 1: Autoloader
// /vendor/ nằm một cấp trên public_html (ngoài webroot – TDD Phần 3.1)
// ----------------------------------------------------------------
$vendorPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($vendorPath)) {
    http_response_code(500);
    echo 'Lỗi khởi động: Chưa chạy "composer install". Liên hệ quản trị viên.';
    exit(1);
}

require_once $vendorPath;

// ----------------------------------------------------------------
// Bước 2: Load biến môi trường từ .env
// .env nằm ở root (cùng cấp với /app, /public_html)
// ----------------------------------------------------------------
$envPath = dirname(__DIR__);

try {
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
    $dotenv->load();

    // Validate các biến bắt buộc phải có
    $dotenv->required([
        'APP_URL',
        'APP_ENV',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
    ])->notEmpty();

    // DB_PASS có thể là empty string (local dev không có pass), nhưng key phải tồn tại
    $dotenv->required('DB_PASS');

} catch (Dotenv\Exception\InvalidPathException $e) {
    http_response_code(500);
    echo 'Lỗi khởi động: Không tìm thấy file .env. Liên hệ quản trị viên.';
    exit(1);
} catch (Dotenv\Exception\ValidationException $e) {
    http_response_code(500);
    echo 'Lỗi khởi động: File .env thiếu biến bắt buộc. Liên hệ quản trị viên.';
    exit(1);
}

// ----------------------------------------------------------------
// Bước 3: Cấu hình PHP runtime theo TDD Phần 4.6
// ----------------------------------------------------------------
$isProduction = ($_ENV['APP_ENV'] ?? 'local') === 'production';

if ($isProduction) {
    // Production: ẩn lỗi, không lộ stack trace ra browser
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    // Development: hiển thị mọi lỗi để debug
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Luôn bật log_errors dù production hay development
ini_set('log_errors', '1');

// Timezone mặc định cho toàn bộ ứng dụng
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ----------------------------------------------------------------
// Bước 4: Global Exception/Error Handler
// Bắt mọi exception chưa được xử lý → log + trang 500
// Không để stack trace lộ ra browser trong production
// ----------------------------------------------------------------
set_exception_handler(function (Throwable $e) use ($isProduction): void {
    die("LỖI THỰC TẾ LÀ: " . $e->getMessage() . " (Tại file: " . $e->getFile() . " - Dòng: " . $e->getLine() . ")");
    error_log(sprintf(
        '[BugTracker][CRITICAL] Uncaught %s: %s in %s:%d | Trace: %s',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        substr($e->getTraceAsString(), 0, 2000) // Giới hạn 2000 chars (TDD Phần 4.2)
    ));

    http_response_code(500);

    $errorViewPath = dirname(__DIR__) . '/app/Views/errors/500.php';

    if (file_exists($errorViewPath)) {
        // Tạo error ID để user báo cáo mà không lộ chi tiết kỹ thuật
        $errorId = strtoupper(substr(md5(uniqid((string) time(), true)), 0, 8));
        include $errorViewPath;
    } else {
        echo '<h1>500 – Lỗi máy chủ nội bộ</h1>';
        echo '<p>Đã xảy ra lỗi không mong muốn. Vui lòng thử lại sau.</p>';
    }

    exit(1);
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    // Chỉ throw ErrorException cho các error level đang được report
    if (!(error_reporting() & $severity)) {
        return false; // Bỏ qua error bị suppress bởi @ operator
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// ----------------------------------------------------------------
// Bước 5: Load config.php
// PHẢI trước functions.php vì functions.php dùng constant từ file này:
//   APP_URL      → dùng trong asset(), url()
//   VIEWS_PATH   → dùng trong các View include partial
//   STORAGE_PATH → dùng trong FileUploadService
//   UPLOAD_MAX_FILES, UPLOAD_MAX_FILE_SIZE, ... → dùng trong Controller
// ----------------------------------------------------------------
require_once dirname(__DIR__) . '/app/Config/config.php';

// ----------------------------------------------------------------
// Bước 6: Load helper functions
// Sau config.php → tất cả constant đã sẵn sàng → asset(), url()... hoạt động đúng
// ----------------------------------------------------------------
require_once dirname(__DIR__) . '/app/Helpers/Functions.php';

\App\Core\Session::start();

// ----------------------------------------------------------------
// Bước 7: Khởi tạo Router, load routes, dispatch request
// ----------------------------------------------------------------
$router  = new \App\Core\Router();
$request = new \App\Core\Request();

// Đăng ký routes (Dev 1 + Dev 2 thêm routes vào file này)
require_once dirname(__DIR__) . '/app/Config/routes.php';

// Dispatch request đến đúng Controller::method
$router->dispatch($request);