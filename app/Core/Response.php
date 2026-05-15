<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Response – HTTP Response Builder
 *
 * Xử lý tất cả loại response: redirect, JSON (AJAX), render View.
 * Quản lý flash messages để hiển thị thông báo sau redirect.
 *
 * LAYOUT SYSTEM:
 *   View template có thể khai báo layout bằng cách set biến $layout:
 *     $layout = 'landing';   → load /app/Views/layouts/landing.php
 *     $layout = 'app';       → load /app/Views/layouts/app.php
 *     $layout = 'auth';      → load /app/Views/layouts/auth.php
 *     (không set $layout)    → render view trực tiếp, không có layout
 *
 *   Layout nhận $content (string) là HTML đã render của view content,
 *   inject qua: <?= $content ?>
 *
 *   WHY dùng output buffering (ob_start/ob_get_clean):
 *     Phải capture output của view content thành string trước, sau đó
 *     truyền vào layout dưới dạng biến $content. Nếu include thẳng cả
 *     2 file thì không có cách nào để layout "bao bọc" content.
 *
 * @package App\Core
 * @version 1.0.1
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
     *
     * Cấu trúc response chuẩn của BugTracker:
     *   {"success": true/false, "data": {...}, "message": "..."}
     *
     * @param  array<string, mixed> $data
     * @param  int                  $statusCode
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
     * Render PHP view template với data được inject, hỗ trợ layout system.
     *
     * LUỒNG XỬ LÝ:
     *   1. ob_start() – bắt đầu capture output
     *   2. extract($data) – inject biến vào scope
     *   3. include view template → view có thể set $layout = 'landing'
     *   4. ob_get_clean() → lấy HTML content của view thành $content (string)
     *   5. Nếu view đã set $layout → load layouts/{$layout}.php với $content
     *   6. Nếu không có $layout → echo $content trực tiếp
     *
     * View template path : /app/Views/{template}.php
     * Layout path        : /app/Views/layouts/{layout}.php
     *
     * Ví dụ:
     *   Response::view('landing/index', ['pageTitle' => '...'])
     *   → landing/index.php set $layout = 'landing'
     *   → layouts/landing.php được load với $content = HTML của landing/index.php
     *
     * @param  string               $template   Đường dẫn tương đối trong /app/Views/
     *                                          VD: 'landing/index', 'auth/login'
     * @param  array<string, mixed> $data       Dữ liệu inject vào template
     * @param  int                  $statusCode
     * @return void
     */
    public static function view(
        string $template,
        array $data = [],
        int $statusCode = 200
    ): void {
        http_response_code($statusCode);

        $viewsDir = dirname(__DIR__) . '/Views/';
        $viewPath = $viewsDir . ltrim($template, '/') . '.php';

        if (!file_exists($viewPath)) {
            error_log("[Response::view] View không tìm thấy: {$viewPath}");

            // Tránh infinite loop nếu errors/500.php cũng không tồn tại
            if ($template === 'errors/500') {
                http_response_code(500);
                echo '<h1>500 – Lỗi máy chủ nội bộ</h1>';
                echo '<p>Đã xảy ra lỗi không mong muốn. Vui lòng thử lại sau.</p>';
                return;
            }

            self::view('errors/500', [], 500);
            return;
        }

        // ----------------------------------------------------------------
        // Bước 1: Capture output của view content vào buffer
        //
        // WHY ob_start trước extract+include:
        //   extract() tạo biến trong scope hiện tại (bao gồm $layout nếu
        //   $data có key 'layout'). View template sau đó có thể override
        //   $layout bằng cách tự set lại ($layout = 'landing').
        //   ob_get_clean() capture tất cả echo/HTML output của view.
        // ----------------------------------------------------------------
        ob_start();

        // EXTR_SKIP: không ghi đè biến đã tồn tại trong scope (an toàn hơn EXTR_OVERWRITE)
        extract($data, EXTR_SKIP);

        // $layout có thể được set bên trong view template.
        // Khai báo trước include để tránh "undefined variable" notice
        // nếu view không set $layout.
        $layout = $layout ?? null;

        include $viewPath;

        // Sau khi include, $layout có thể đã được view override
        // VD: landing/index.php có dòng: $layout = 'landing';
        $content = ob_get_clean();

        // ----------------------------------------------------------------
        // Bước 2: Wrap content vào layout (nếu view đã khai báo $layout)
        // ----------------------------------------------------------------
        if (!empty($layout)) {
            $layoutPath = $viewsDir . 'layouts/' . $layout . '.php';

            if (!file_exists($layoutPath)) {
                error_log("[Response::view] Layout không tìm thấy: {$layoutPath}");
                // Layout không có → render content trực tiếp, không crash
                echo $content;
                return;
            }

            include $layoutPath;
            return;
        }

        // ----------------------------------------------------------------
        // Bước 3: Không có layout → echo content trực tiếp
        // Dùng cho: API partial views, error pages, email templates
        // ----------------------------------------------------------------
        echo $content;
    }

    public static function setFlash(string $type, string $message): void
    {
        Session::start();
        $_SESSION['_flash'][$type] = $message;
    }


    public static function getFlash(string $type): ?string
    {
        Session::start();

        $message = $_SESSION['_flash'][$type] ?? null;

        if (isset($_SESSION['_flash'][$type])) {
            unset($_SESSION['_flash'][$type]);
        }

        return $message;
    }

    
    public static function getAllFlash(): array
    {
        Session::start();

        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $flashes;
    }


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
     * Set HTTP status code.
     *
     * @param  int $code
     * @return void
     */
    public static function status(int $code): void
    {
        http_response_code($code);
    }
}