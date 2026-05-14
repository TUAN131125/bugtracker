<?php

/**
 * functions.php – Global Helper Functions
 *
 * Load một lần duy nhất trong index.php.
 * Tất cả function ở đây dùng được ở MỌI class/controller
 * mà không cần 'use function' hay backslash.
 *
 * @see public_html/index.php – nơi require file này
 */

if (!function_exists('url')) {
    /**
     * Sinh URL tuyệt đối từ path tương đối.
     * WHY dùng APP_URL từ .env thay vì hardcode:
     * Đảm bảo hoạt động đúng cả local lẫn InfinityFree production.
     *
     * @param string $path  Ví dụ: '/login', '/issues/BT-001'
     * @return string       Ví dụ: 'https://domain.com/login'
     */
    function url(string $path = ''): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

if (!function_exists('asset')) {
    /**
     * Sinh URL đến file static trong /public_html/assets/.
     *
     * @param string $path  Ví dụ: 'css/app.css', 'js/app.js'
     * @return string       Ví dụ: 'https://domain.com/assets/css/app.css'
     */
    function asset(string $path): string
    {
        return url('/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('config')) {
    /**
     * Lấy giá trị từ $_ENV (đã load bởi phpdotenv).
     *
     * @param string $key      Tên biến trong .env
     * @param mixed  $default  Giá trị mặc định nếu key không tồn tại
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('redirect')) {
    /**
     * Redirect ngay lập tức đến URL chỉ định.
     *
     * @param string $path
     * @return never
     */
    function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}