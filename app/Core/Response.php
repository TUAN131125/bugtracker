<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response – HTTP Response Builder
 *
 * Xử lý tất cả loại response: redirect, JSON (AJAX), render View.
 * Quản lý flash messages để hiển thị thông báo sau redirect.
 *
 * @package App\Core
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 3.3 (Request Lifecycle)
 * @see     Task Assignment v1.0.0 – D1-007
 */
class Response
{
    /**
     * Redirect đến URL khác.
     * Gọi exit() sau khi set header để dừng execution.
     *
     * @param  string $url        URL đích. Có thể là đường dẫn tương đối hoặc tuyệt đối.
     * @param  int    $statusCode HTTP status code. Mặc định 302 (Found).
     * @return never
     */
    public static function redirect(string $url, int $statusCode = 302): never
    {
        // Nếu URL không bắt đầu bằng http/https → thêm APP_URL
        if (!str_starts_with($url, 'http')) {
            $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
            $url    = $appUrl . '/' . ltrim($url, '/');
        }

        http_response_code($statusCode);
        header('Location: ' . $url);
        exit();
    }

    /**
     * Trả về JSON response cho AJAX request.
     * Tự động set Content-Type header và encode data.
     *
     * Cấu trúc response chuẩn của BugTracker (Task Assignment Phần 2.2):
     *   {"success": true/false, "data": {...}, "message": "..."}
     *
     * @param  array<string, mixed> $data       Data cần trả về.
     * @param  int                  $statusCode HTTP status code.
     * @return never
     */
    public static function json(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        // JSON_UNESCAPED_UNICODE: giữ nguyên tiếng Việt thay vì \uXXXX
        // JSON_UNESCAPED_SLASHES: không escape dấu / trong URL
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    /**
     * Render PHP view template với data được inject.
     *
     * Template path: /app/Views/{template}.php
     * VD: view('auth/login', ['error' => 'Email không hợp lệ'])
     *
     * @param  string               $template  Đường dẫn tương đối trong /app/Views/.
     *                                         VD: 'auth/login', 'issues/list'
     * @param  array<string, mixed> $data      Dữ liệu inject vào template.
     * @param  int                  $statusCode
     * @return void
     */
    public static function view(
        string $template,
        array $data = [],
        int $statusCode = 200
    ): void {
        http_response_code($statusCode);

        $viewPath = dirname(__DIR__) . '/Views/' . ltrim($template, '/') . '.php';

        if (!file_exists($viewPath)) {
            // View không tồn tại — log và trả về 500
            error_log("[Response] View not found: {$viewPath}");
            self::view('errors/500', [], 500);
            return;
        }

        // extract() biến $data thành các biến PHP trong scope của view
        // VD: ['pageTitle' => 'Login'] → $pageTitle trong view
        extract($data, EXTR_SKIP);

        include $viewPath;
    }

    /**
     * Set flash message vào session.
     * Flash message sẽ tự xóa sau khi được đọc lần đầu.
     *
     * @param  string $type    Loại message: 'success' | 'error' | 'warning' | 'info'
     * @param  string $message Nội dung thông báo.
     * @return void
     */
    public static function setFlash(string $type, string $message): void
    {
        Session::start();
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * Lấy flash message và xóa khỏi session.
     * Trả về null nếu không có flash message với type đó.
     *
     * @param  string      $type
     * @return string|null
     */
    public static function getFlash(string $type): ?string
    {
        Session::start();

        $message = $_SESSION['_flash'][$type] ?? null;

        // Xóa flash message sau khi đọc — one-time use
        if (isset($_SESSION['_flash'][$type])) {
            unset($_SESSION['_flash'][$type]);
        }

        return $message;
    }

    /**
     * Lấy tất cả flash messages và xóa khỏi session.
     *
     * @return array<string, string>
     */
    public static function getAllFlash(): array
    {
        Session::start();

        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $flashes;
    }

    /**
     * Lưu old input vào session (dùng khi form validation fail).
     * Controller gọi method này trước khi redirect back.
     *
     * @param  array<string, mixed> $input  Thường là $_POST nhưng đã lọc password.
     * @return void
     */
    public static function setOldInput(array $input): void
    {
        Session::start();
        $_SESSION['_old_input'] = $input;
    }

    /**
     * Lấy old input và xóa khỏi session.
     *
     * @return array<string, mixed>
     */
    public static function getOldInput(): array
    {
        Session::start();

        $input = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);

        return $input;
    }

    /**
     * Set HTTP header tùy chỉnh.
     *
     * @param  string $name
     * @param  string $value
     * @return void
     */
    public static function setHeader(string $name, string $value): void
    {
        header("{$name}: {$value}");
    }

    /**
     * Trả về response với HTTP status code cụ thể.
     * Shorthand để set code trước khi render view.
     *
     * @param  int $code
     * @return void
     */
    public static function status(int $code): void
    {
        http_response_code($code);
    }
}