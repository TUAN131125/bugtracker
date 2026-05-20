<?php

declare(strict_types=1);

// =============================================================================
// BugTracker – Global Helper Functions
//
// File này được require_once trong public_html/index.php SAU khi config.php
// đã được load (để các constant APP_URL, VIEWS_PATH, APP_NAME... sẵn sàng).
//
// NGUYÊN TẮC THIẾT KẾ:
//   - Không hardcode domain, path, hay bất kỳ giá trị cố định nào.
//     Tất cả đọc từ constant được define trong app/Config/config.php.
//   - KHÔNG dùng if (!function_exists()) guard bọc toàn bộ function body.
//     WHY: Intelephense phân tích tĩnh – không execute code – nên không
//     nhận ra function được khai báo bên trong block if() → báo P1010
//     "Undefined function" ở mọi chỗ gọi trong cùng file.
//     Thay vào đó: khai báo function ở top-level (Intelephense index được),
//     dùng guard chỉ để tránh redeclare khi file bị require nhiều lần
//     (xem pattern tại mỗi function bên dưới).
//   - Mỗi function tự xử lý hoàn toàn, KHÔNG gọi function khác trong file.
//     WHY: Tránh Intelephense P1010 do forward reference khi các function
//     được parse theo thứ tự từ trên xuống.
//
// THỨ TỰ REQUIRE trong index.php (bắt buộc):
//   1. vendor/autoload.php
//   2. .env  (qua Dotenv)
//   3. app/Config/config.php        ← define APP_URL, VIEWS_PATH, APP_NAME...
//   4. app/Helpers/Functions.php    ← file này, đọc constant từ bước 3
//
// @see TDD Backend v1.0.0   – D1-016 (Sanitizer helper)
// @see ViewLayer Guide v1.0.0 – Phần 8.1 (XSS Prevention)
// =============================================================================


// =============================================================================
// asset() – URL đến file tĩnh trong /assets/
// =============================================================================

/**
 * Tạo URL tuyệt đối trỏ đến file tĩnh trong /public_html/assets/.
 *
 * Dùng trong View template:
 *   <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
 *   <script src="<?= asset('js/core/utils.js') ?>" defer></script>
 *   <img src="<?= asset('img/logo.svg') ?>" alt="Logo">
 *
 * Output mẫu: https://domain.com/assets/css/app.css
 *
 * APP_URL được define trong config.php, đọc từ $_ENV['APP_URL'] – không hardcode.
 *
 * @param  string $path  Đường dẫn tương đối trong /assets/, VD: 'css/app.css'
 * @return string        URL đầy đủ, VD: 'https://domain.com/assets/css/app.css'
 */
function asset(string $path): string
{
    $base = rtrim(APP_URL, '/');
    $path = ltrim($path, '/');

    return "{$base}/assets/{$path}";
}


// =============================================================================
// url() – URL đến route trong ứng dụng
// =============================================================================

/**
 * Tạo URL tuyệt đối đến một route trong ứng dụng.
 *
 * Dùng trong View template:
 *   <a href="<?= url('login') ?>">Đăng nhập</a>
 *   <a href="<?= url('/') ?>">Trang chủ</a>
 *   <form action="<?= url('workspace/create') ?>" method="POST">
 *
 * Các trường hợp được xử lý:
 *   url('')           → https://domain.com/
 *   url('/')          → https://domain.com/
 *   url('login')      → https://domain.com/login
 *   url('/login')     → https://domain.com/login
 *   url('/#features') → https://domain.com/#features
 *
 * @param  string $path  Route path, VD: 'login', '/', '/#features'
 * @return string        URL đầy đủ
 */
function url(string $path = ''): string
{
    $base = rtrim(APP_URL, '/');

    if ($path === '' || $path === '/') {
        return $base . '/';
    }

    // Tách fragment (#anchor) ra trước khi normalize slash
    $fragment = '';
    if (str_contains($path, '#')) {
        $parts    = explode('#', $path, 2);
        $path     = $parts[0];
        $fragment = '#' . $parts[1];
    }

    $path = ltrim($path, '/');

    // path rỗng sau khi strip slash (VD: input là '/#features')
    if ($path === '') {
        return $base . '/' . $fragment;
    }

    return $base . '/' . $path . $fragment;
}


// =============================================================================
// e() – Escape HTML chống XSS
// =============================================================================

/**
 * Escape string chống XSS khi render ra HTML.
 * Alias ngắn của htmlspecialchars() dành cho View template.
 *
 * Dùng trong View template:
 *   <h1><?= e($issue['title']) ?></h1>
 *   <input value="<?= e($name) ?>">
 *   <meta name="description" content="<?= e($description) ?>">
 *
 * NGUYÊN TẮC: Dùng e() cho MỌI biến từ DB hoặc user input khi render HTML.
 * Không có ngoại lệ – kể cả biến tưởng chừng "an toàn".
 *
 * Hàm tự xử lý hoàn toàn, không gọi function khác trong file này.
 * WHY: Tránh forward reference – Intelephense parse từ trên xuống.
 *
 * @param  mixed $value  string, int, float, null đều được chấp nhận
 * @return string        Chuỗi đã escape, an toàn để echo vào HTML
 *
 * @see ViewLayer Guide v1.0.0 – Phần 8.1 (XSS Prevention – quy tắc bất biến)
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
}


// =============================================================================
// csrf_field() – Hidden input chứa CSRF token
// =============================================================================

/**
 * Render <input type="hidden"> chứa CSRF token cho form POST.
 *
 * Dùng trong mọi form POST – không có ngoại lệ:
 *   <form method="POST" action="<?= url('issues/create') ?>">
 *       <?= csrf_field() ?>
 *       ...
 *   </form>
 *
 * Hàm tự xử lý hoàn toàn, không gọi e() hay function khác trong file này.
 * WHY: Tránh forward reference. Token là hex string nên htmlspecialchars
 * trực tiếp là đủ và an toàn (không có ký tự HTML đặc biệt trong hex).
 *
 * @return string  HTML: <input type="hidden" name="csrf_token" value="abc...">
 */
function csrf_field(): string
{
    $token = \App\Helpers\Csrf::getToken();

    // htmlspecialchars trực tiếp – không gọi e() để tránh forward reference
    $safe = htmlspecialchars($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return '<input type="hidden" name="csrf_token" value="' . $safe . '">';
}


// =============================================================================
// old() – Lấy lại giá trị input cũ sau redirect
// =============================================================================

/**
 * Lấy lại giá trị input cũ từ session sau khi form validation fail và redirect back.
 * Controller lưu input vào $_SESSION['old_input'] trước khi redirect.
 *
 * Dùng trong View template:
 *   <input type="text"  name="name"  value="<?= old('name') ?>">
 *   <input type="email" name="email" value="<?= old('email', $user['email']) ?>">
 *
 * Output đã được escape HTML – không bọc thêm e() bên ngoài.
 *
 * Đọc $_SESSION trực tiếp thay vì qua Session::get() để tránh forward reference
 * và tránh phụ thuộc vào thứ tự autoload trong quá trình static analysis.
 *
 * Hàm tự xử lý hoàn toàn, không gọi function khác trong file này.
 *
 * @param  string $key      Tên field, VD: 'name', 'email', 'title'
 * @param  string $default  Giá trị mặc định nếu không có old input
 * @return string           Giá trị đã escape, an toàn để dùng trong HTML attribute
 */
function old(string $key, string $default = ''): string
{
    $old_input = $_SESSION['old_input'] ?? [];
    $value     = isset($old_input[$key]) ? (string) $old_input[$key] : $default;

    // htmlspecialchars trực tiếp – không gọi e() để tránh forward reference
    return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}


// =============================================================================
// flash() – Lấy flash message từ session (single-use)
// =============================================================================

/**
 * Lấy flash message từ session và xóa ngay sau khi đọc (single-use).
 * Dùng để hiển thị thông báo success/error/warning sau redirect.
 *
 * Dùng trong View template:
 *   <?php if ($msg = flash('success')): ?>
 *       <div class="alert alert--success"><?= e($msg) ?></div>
 *   <?php endif; ?>
 *
 *   <?php if ($err = flash('error')): ?>
 *       <div class="alert alert--error"><?= e($err) ?></div>
 *   <?php endif; ?>
 *
 * Trả về null (không phải chuỗi rỗng) nếu không có message,
 * để pattern if ($msg = flash('success')) hoạt động đúng.
 *
 * Đọc $_SESSION trực tiếp – không gọi Response::getFlash() để tránh
 * forward reference và dependency vào thứ tự autoload.
 *
 * Hàm tự xử lý hoàn toàn, không gọi function khác trong file này.
 *
 * @param  string $key  VD: 'success', 'error', 'warning', 'info'
 * @return string|null  null nếu không có message với key này
 */
function flash(string $key): ?string
{
    $session_key = 'flash_' . $key;

    if (!isset($_SESSION[$session_key])) {
        return null;
    }

    $message = (string) $_SESSION[$session_key];
    unset($_SESSION[$session_key]);

    return $message;
}



function route_is(string $pattern): bool
{
    $uri          = $_SERVER['REQUEST_URI'] ?? '/';
    $current_path = ltrim(parse_url($uri, PHP_URL_PATH) ?? '/', '/');

    // Escape ký tự regex đặc biệt, sau đó thay \* bằng .* (wildcard)
    // WHY preg_quote trước rồi str_replace \*:
    //   preg_quote escape dấu / thành \/ và * thành \*.
    //   Sau đó ta thay \* bằng .* để * hoạt động như wildcard.
    //   Thứ tự quan trọng – không thể replace * trước khi quote.
    $regex = '/^' . str_replace(
        ['\\*', '/'],
        ['.*',  '\\/'],
        preg_quote($pattern, '/')
    ) . '$/';

    return (bool) preg_match($regex, $current_path);
}


// =============================================================================
// format_bytes() – Format kích thước file cho display
// =============================================================================

/**
 * Format số bytes thành chuỗi dễ đọc cho người dùng.
 *
 * Dùng trong View template hiển thị attachment:
 *   <?= format_bytes($attachment['file_size']) ?>
 *   → "1.5 MB", "256 KB", "42 B"
 *
 * Hàm tự xử lý hoàn toàn, không gọi function khác trong file này.
 *
 * @param  int $bytes     Kích thước file tính bằng bytes
 * @param  int $decimals  Số chữ số thập phân (mặc định 1)
 * @return string         VD: "1.5 MB", "256 KB", "42 B"
 */
function format_bytes(int $bytes, int $decimals = 1): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $i     = (int) floor(log($bytes, 1024));
    $i     = min($i, count($units) - 1); // Giới hạn tối đa GB

    $value = $bytes / (1024 ** $i);

    return round($value, $decimals) . ' ' . $units[$i];
}


// =============================================================================
// time_ago() – Thời gian tương đối
// =============================================================================

/**
 * Chuyển timestamp thành chuỗi thời gian tương đối.
 *
 * Dùng trong View template hiển thị Activity Log và Comment:
 *   <?= time_ago($comment['created_at']) ?>
 *   → "2 giờ trước", "3 ngày trước", "vừa xong"
 *
 * Hàm tự xử lý hoàn toàn, không gọi function khác trong file này.
 *
 * @param  string $datetime  Datetime string từ DB, VD: "2026-05-15 10:30:00"
 * @return string            Chuỗi tương đối tiếng Việt
 */
function time_ago(string $datetime): string
{
    $now  = time();
    $then = strtotime($datetime);

    if ($then === false) {
        return 'Không rõ';
    }

    $diff = $now - $then;

    if ($diff < 60) {
        return 'vừa xong';
    }

    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return "{$mins} phút trước";
    }

    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return "{$hours} giờ trước";
    }

    if ($diff < 2592000) {
        $days = (int) floor($diff / 86400);
        return "{$days} ngày trước";
    }

    if ($diff < 31536000) {
        $months = (int) floor($diff / 2592000);
        return "{$months} tháng trước";
    }

    $years = (int) floor($diff / 31536000);
    return "{$years} năm trước";
}