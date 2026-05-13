<?php

declare(strict_types=1);

// =============================================================================
// BugTracker – Application Configuration
// /app/Config/config.php
//
// NGUYÊN TẮC BẤT BIẾN: File này KHÔNG được hardcode bất kỳ giá trị nào.
// Mọi thứ đọc từ $_ENV (được nạp bởi vlucas/phpdotenv trong index.php).
//
// Thứ tự ưu tiên:
//   1. $_ENV['KEY']    → Giá trị từ .env trên server thật
//   2. fallback ?? ... → Giá trị mặc định an toàn cho môi trường dev local
//
// Dev 1 phụ trách file này (Task Assignment D1-010).
// Dev 2 và Dev 3 KHÔNG sửa trực tiếp – yêu cầu Dev 1 nếu cần thêm constant.
//
// @see TDD Backend v1.0.0 – Phần 3.2 (Cấu trúc thư mục), Phần 3.5 (Deploy)
// @see Task Assignment v1.0.0 – D1-010
// =============================================================================

// =============================================================================
// SECTION 1 – PATH CONSTANTS
//
// Vị trí file này: /app/Config/config.php
//
//   __DIR__                     = /app/Config
//   dirname(__DIR__)            = /app                ← APP_DIR
//   dirname(dirname(__DIR__))   = /  (project root)  ← PROJECT_ROOT
//
// WHY dùng dirname() thay vì hardcode:
//   Path tuyệt đối thay đổi theo từng server (InfinityFree, XAMPP, Docker).
//   dirname() luôn tính đúng dựa trên vị trí thực của file.
// =============================================================================

// Thư mục /app – chứa Controllers, Models, Services, Views, Middleware, Helpers
define('APP_DIR',      dirname(__DIR__));

// Thư mục root dự án – chứa .env, vendor/, storage/, public_html/, database/
// FIX: Phải dùng dirname(dirname(__DIR__)) để đi lên 2 cấp từ /app/Config/
// Lỗi cũ: dirname(__DIR__) chỉ đi lên 1 cấp → trỏ vào /app thay vì root
define('PROJECT_ROOT', dirname(dirname(__DIR__)));

// Shortcut path hay dùng trong View layer (Response::view() load template từ đây)
define('VIEWS_PATH',   APP_DIR . '/Views');

// Thư mục vendor (Composer autoload – PHPMailer, phpdotenv)
define('VENDOR_DIR',   PROJECT_ROOT . '/vendor');

// Thư mục chứa database migration scripts
define('DATABASE_DIR', PROJECT_ROOT . '/database');

// =============================================================================
// SECTION 2 – APPLICATION
// =============================================================================

// Môi trường: 'local' | 'production'
// Dùng để quyết định có hiển thị lỗi hay không.
// KHÔNG BAO GIỜ để giá trị 'local' trên server production.
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');

// URL gốc của ứng dụng – dùng để build link trong email, redirect, assets.
// Không có trailing slash để dùng nhất quán: APP_URL . '/login'
// Ví dụ: https://myapp.infinityfreeapp.com
define('APP_URL', rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'));

// APP_KEY: dùng để sign/verify data nội bộ nếu cần.
// Sinh bằng: echo bin2hex(random_bytes(32));
// Bắt buộc phải set trong .env trên production.
define('APP_KEY', $_ENV['APP_KEY'] ?? '');

// APP_DEBUG: chỉ true khi local dev – KHÔNG ĐƯỢC true trên production.
// Khi true: hiển thị stack trace chi tiết thay vì trang lỗi thân thiện.
// Tự động false khi APP_ENV = 'production' để tránh lộ thông tin.
define('APP_DEBUG', APP_ENV === 'local');

// Tên ứng dụng – dùng trong <title>, email subject, và thông báo hệ thống
define('APP_NAME', $_ENV['APP_NAME'] ?? 'BugTracker');

// =============================================================================
// SECTION 3 – DATABASE
//
// Các credentials DB KHÔNG được define thành constant ở đây.
// WHY: constant PHP có thể bị lộ qua phpinfo() hoặc error page.
// Database::getInstance() đọc trực tiếp từ $_ENV – an toàn hơn.
//
// Các key cần có trong .env:
//   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
//
// =============================================================================

// Charset mặc định – bắt buộc utf8mb4 để hỗ trợ đầy đủ tiếng Việt và emoji
// (TDD Phần 2.2 – tất cả bảng dùng CHARSET utf8mb4)
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// =============================================================================
// SECTION 4 – STORAGE & FILE UPLOAD
// Liên quan: SRS UC-019 (Tạo Issue với file đính kèm), UC-026 (Comment)
// Liên quan: TDD Phần 3.1 (Bảo mật thư mục – lưu file ngoài public_html)
// =============================================================================

// Đường dẫn tuyệt đối đến thư mục lưu file – NGOÀI public_html để bảo mật.
// Trên InfinityFree: /home/vol{N}/websites/{domain}/storage
// Trên local XAMPP : PROJECT_ROOT . '/storage'
// WHY ngoài public_html: Tránh user truy cập file trực tiếp qua URL.
// Phải serve qua PHP script trung gian để kiểm tra quyền (D1-027).
define('STORAGE_PATH', rtrim(
    $_ENV['STORAGE_PATH'] ?? PROJECT_ROOT . '/storage',
    '/'
));

// Thư mục con dành riêng cho file đính kèm Issue và Comment
// Cấu trúc: /storage/attachments/{workspace_id}/{issue_id}/
define('ATTACHMENTS_DIR', STORAGE_PATH . '/attachments');

// Thư mục log ứng dụng (Logger.php ghi vào đây – TDD Phần 4.2)
// File log theo tháng: app-YYYY-MM.log
define('LOGS_DIR', STORAGE_PATH . '/logs');

// --- Giới hạn upload (SRS UC-019: max 5 file × 2MB, UC-026: max 3 file) ---

// Dung lượng tối đa mỗi file (bytes): 2MB theo SRS
// .env lưu dạng byte: UPLOAD_MAX_FILE_SIZE=2097152
define('UPLOAD_MAX_FILE_SIZE', (int)($_ENV['UPLOAD_MAX_FILE_SIZE'] ?? 2097152));

// Số file tối đa đính kèm vào một Issue (SRS UC-019)
define('UPLOAD_MAX_FILES', (int)($_ENV['UPLOAD_MAX_FILES'] ?? 5));

// Số file tối đa đính kèm vào một Comment (SRS UC-026)
define('UPLOAD_MAX_FILES_COMMENT', (int)($_ENV['UPLOAD_MAX_FILES_COMMENT'] ?? 3));

// Danh sách MIME type được phép upload (SRS UC-019)
// QUAN TRỌNG: Validate bằng finfo_file() ở server-side (D1-026).
// KHÔNG tin vào file extension hay Content-Type header từ client.
define('UPLOAD_ALLOWED_MIMES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf',
    'text/plain',
    'application/zip',
    'application/x-zip-compressed', // zip trên một số hệ điều hành Windows
]);

// Map MIME type → extension an toàn (dùng khi đặt tên file stored trên server)
// WHY: Tên file gốc từ user không đáng tin cậy – luôn dùng extension từ map này
define('UPLOAD_ALLOWED_EXTENSIONS', [
    'image/jpeg'                   => 'jpg',
    'image/png'                    => 'png',
    'image/gif'                    => 'gif',
    'application/pdf'              => 'pdf',
    'text/plain'                   => 'txt',
    'application/zip'              => 'zip',
    'application/x-zip-compressed' => 'zip',
]);

// MIME type được coi là ảnh – dùng để tạo thumbnail (GD Library)
define('IMAGE_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// --- Kích thước ảnh (GD Library resize khi upload) ---
// SRS Phần 3.2.1: avatar resize 100×100; attachment thumbnail 200×150
define('THUMBNAIL_WIDTH',  (int)($_ENV['THUMBNAIL_WIDTH']  ?? 200));
define('THUMBNAIL_HEIGHT', (int)($_ENV['THUMBNAIL_HEIGHT'] ?? 150));
define('AVATAR_SIZE',      (int)($_ENV['AVATAR_SIZE']      ?? 100));

// =============================================================================
// SECTION 5 – SECURITY & SESSION
// Liên quan: TDD Phần 4.6 (Cấu hình PHP Production)
// =============================================================================

// Thời gian sống của session (giây) – 2 giờ theo TDD Phần 4.6
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? 7200));

// Tên session cookie – đổi tên mặc định 'PHPSESSID' để giảm fingerprint
define('SESSION_NAME', $_ENV['SESSION_NAME'] ?? 'bt_session');

// =============================================================================
// SECTION 6 – TOKEN TTL (Time To Live, tính bằng giây)
// Liên quan: TDD Phần 1.4.1 – Cấu trúc Token và Thuật toán sinh Token
// =============================================================================

// Email verification token: 24 giờ (SRS UC-001, TDD Phần 1.4.1)
define('TOKEN_TTL_EMAIL_VERIFY', (int)($_ENV['TOKEN_TTL_EMAIL_VERIFY'] ?? 86400));

// Password reset token: 1 giờ – single-use (SRS UC-005, TDD Phần 1.4.1)
define('TOKEN_TTL_PASSWORD_RESET', (int)($_ENV['TOKEN_TTL_PASSWORD_RESET'] ?? 3600));

// Remember Me cookie: 30 ngày (SRS UC-003, TDD Phần 1.4.1)
define('TOKEN_TTL_REMEMBER_ME', (int)($_ENV['TOKEN_TTL_REMEMBER_ME'] ?? 2592000));

// Workspace invitation token: 7 ngày (SRS UC-008, TDD Phần 1.4.1)
define('TOKEN_TTL_INVITATION', (int)($_ENV['TOKEN_TTL_INVITATION'] ?? 604800));

// =============================================================================
// SECTION 7 – RATE LIMITING (Login attempts)
// Liên quan: SRS UC-003, TDD Phần 3.3 (LoginController), D1-022
// =============================================================================

// Số lần đăng nhập sai tối đa trước khi bị lock theo IP
define('LOGIN_MAX_ATTEMPTS', (int)($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5));

// Thời gian lock sau khi vượt quá số lần sai (phút)
// Dùng trong LoginAttempt::countRecentAttempts($ip, LOGIN_LOCKOUT_MINUTES)
define('LOGIN_LOCKOUT_MINUTES', (int)($_ENV['LOGIN_LOCKOUT_MINUTES'] ?? 15));

// =============================================================================
// SECTION 8 – PAGINATION & SEARCH
// Liên quan: SRS UC-035 (Activity Log), UC-037 (Global Search), D1-029
// =============================================================================

// Số bản ghi mỗi trang mặc định (SRS UC-035: 20 bản ghi/trang)
define('PER_PAGE_DEFAULT', (int)($_ENV['PER_PAGE_DEFAULT'] ?? 20));

// Số kết quả tối đa mỗi nhóm trong Global Search (SRS UC-037: 5 kết quả/nhóm)
// SearchApiController dùng constant này thay vì hardcode.
// Dev 2 và Dev 3 KHÔNG tự đặt lại giá trị này ở nơi khác.
define('SEARCH_RESULTS_PER_GROUP', (int)($_ENV['SEARCH_RESULTS_PER_GROUP'] ?? 5));

// Độ dài tối thiểu của từ khóa tìm kiếm (D1-029: tối thiểu 2 ký tự)
// WHY: Tránh query LIKE '%%' quét toàn bảng issues trên InfinityFree
define('SEARCH_MIN_QUERY_LENGTH', (int)($_ENV['SEARCH_MIN_QUERY_LENGTH'] ?? 2));

// Độ dài tối đa của từ khóa tìm kiếm (chống abuse, tránh query quá nặng)
define('SEARCH_MAX_QUERY_LENGTH', (int)($_ENV['SEARCH_MAX_QUERY_LENGTH'] ?? 100));

// =============================================================================
// SECTION 9 – NOTIFICATION POLLING
// Liên quan: SRS Phần 3.4.6, ViewLayer Guide Phần 7.1
// WHY polling thay vì WebSocket: InfinityFree Shared Hosting không hỗ trợ
// persistent connection hay background process.
// =============================================================================

// Số giây giữa các lần poll notification
// JS đọc giá trị này qua meta tag được PHP render vào <head>
// (ViewLayer Guide Phần 7.1 – dashboard.js startNotificationPolling)
define('NOTIFICATION_POLL_INTERVAL', (int)($_ENV['NOTIFICATION_POLL_INTERVAL'] ?? 60));

// Số ngày giữ notification đã đọc trước khi lazy cleanup
// (TDD Phần 2.4 – dọn dẹp khi user load trang notif)
define('NOTIFICATION_CLEANUP_DAYS', (int)($_ENV['NOTIFICATION_CLEANUP_DAYS'] ?? 30));

// =============================================================================
// SECTION 10 – ACTIVITY LOG
// Liên quan: TDD Phần 4.4, TDD Phần 2.4 (Chiến lược dọn dẹp)
// =============================================================================

// Số ngày giữ Activity Log trước khi Admin có thể xóa thủ công
// (TDD Phần 2.4: Admin bấm nút "Dọn dẹp log cũ hơn 6 tháng")
define('ACTIVITY_LOG_RETENTION_DAYS', (int)($_ENV['ACTIVITY_LOG_RETENTION_DAYS'] ?? 180));

// Số bản ghi xóa tối đa mỗi lần cleanup
// WHY giới hạn: Tránh vượt max_execution_time trên InfinityFree (TDD Phần 2.4)
// Áp dụng cho: activity_logs, system_logs, notifications, email_queue cleanup
define('CLEANUP_BATCH_SIZE', (int)($_ENV['CLEANUP_BATCH_SIZE'] ?? 500));

// =============================================================================
// SECTION 11 – ISSUE & COMMENT
// Liên quan: SRS UC-027 (Chỉnh sửa comment), SRS UC-015 (Project Key)
// =============================================================================

// Thời gian cho phép sửa comment kể từ lúc tạo (giây)
// SRS UC-027: "Trong 30 phút sau khi đăng, người tạo comment có thể sửa/xóa"
// CommentService::editComment() dùng constant này để validate
define('COMMENT_EDIT_WINDOW', (int)($_ENV['COMMENT_EDIT_WINDOW'] ?? 1800));

// Giới hạn độ dài Project Key (SRS UC-015: 2-6 ký tự in hoa A-Z)
// Validate regex: /^[A-Z]{PROJECT_KEY_MIN_LENGTH,PROJECT_KEY_MAX_LENGTH}$/
define('PROJECT_KEY_MIN_LENGTH', 2);
define('PROJECT_KEY_MAX_LENGTH', 6);

// =============================================================================
// SECTION 12 – EMAIL / SMTP
//
// Credentials SMTP (host, port, user, pass) KHÔNG được define thành constant.
// WHY: Tránh lộ qua phpinfo() hoặc debug page.
// EmailService.php đọc trực tiếp từ $_ENV – đây là thiết kế có chủ đích.
//
// Các key bắt buộc trong .env:
//   SMTP_HOST          → VD: smtp.gmail.com
//   SMTP_PORT          → VD: 587 (TLS) hoặc 465 (SSL)
//   SMTP_USER          → Gmail address dùng App Password
//   SMTP_PASS          → Gmail App Password (16 ký tự, KHÔNG phải mật khẩu Gmail)
//   SMTP_FROM          → VD: noreply@yourdomain.com
//   SMTP_FROM_NAME     → VD: BugTracker
//   SMTP_TIMEOUT       → VD: 10 (giây)
//
// Hướng dẫn tạo Gmail App Password:
//   Google Account → Security → 2-Step Verification → App passwords
//
// =============================================================================

// SMTP connection timeout (giây) – duy nhất constant SMTP cần dùng ở nhiều chỗ.
// WHY expose constant này: EmailService và AdminEmailQueueController đều cần
// giá trị này để set SMTPTimeout trên PHPMailer.
// TDD Phần 1.3: "Set SMTPTimeout = 10 giây" – tránh request treo trên InfinityFree.
define('SMTP_TIMEOUT', (int)($_ENV['SMTP_TIMEOUT'] ?? 10));

// Số email retry tối đa mỗi lần bấm "Retry All" trong Admin panel
// WHY giới hạn 10: Tránh timeout max_execution_time trên InfinityFree
// (TDD Phần 4.5 – Email Queue Manager: "batch 10 cái/lần")
define('EMAIL_QUEUE_RETRY_BATCH', (int)($_ENV['EMAIL_QUEUE_RETRY_BATCH'] ?? 10));

// Số ngày giữ email failed trong queue trước khi Admin có thể cleanup
// (TDD Phần 2.4: "Xóa email lỗi cũ hơn 7 ngày")
define('EMAIL_QUEUE_CLEANUP_DAYS', (int)($_ENV['EMAIL_QUEUE_CLEANUP_DAYS'] ?? 7));