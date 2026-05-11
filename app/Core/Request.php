<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Request – HTTP Request Wrapper
 *
 * Bọc $_GET, $_POST, $_FILES, $_SERVER thành một interface thống nhất.
 * Mọi input từ user PHẢI đi qua class này — không truy cập
 * $_GET/$_POST trực tiếp trong Controller hoặc Middleware.
 *
 * @package App\Core
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 3.3 (Request Lifecycle)
 * @see     Task Assignment v1.0.0 – D1-007
 */
class Request
{
    /**
     * Route parameters extracted bởi Router.
     * VD: route '/issues/{id}' với URI '/issues/BT-001' → ['id' => 'BT-001']
     *
     * @var array<string, string>
     */
    private array $routeParams = [];

    // ----------------------------------------------------------------
    // Input Getters
    // ----------------------------------------------------------------

    /**
     * Lấy giá trị từ query string ($_GET).
     *
     * @param  string      $key
     * @param  mixed       $default Giá trị trả về nếu key không tồn tại.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Lấy giá trị từ POST body ($_POST).
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Lấy tất cả POST data dưới dạng array.
     * Dùng khi cần validate toàn bộ form data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $_POST;
    }

    /**
     * Lấy thông tin file upload ($_FILES).
     *
     * @param  string     $key  Tên input file trong form HTML.
     * @return array|null       Thông tin file hoặc null nếu không có.
     */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * Lấy route parameter được Router inject.
     * VD: route '/issues/{id}' → $request->param('id')
     *
     * @param  string      $key
     * @param  mixed       $default
     * @return mixed
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * Lấy toàn bộ route parameters.
     *
     * @return array<string, string>
     */
    public function params(): array
    {
        return $this->routeParams;
    }

    /**
     * Lấy giá trị từ JSON body (cho AJAX endpoints).
     * Tự động decode JSON body của request.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function json(string $key, mixed $default = null): mixed
    {
        static $decoded = null;

        // Decode một lần, cache lại cho các lần gọi tiếp theo
        if ($decoded === null) {
            $body    = file_get_contents('php://input');
            $decoded = json_decode($body ?: '', true) ?? [];
        }

        return $decoded[$key] ?? $default;
    }

    /**
     * Lấy toàn bộ JSON body dưới dạng array.
     *
     * @return array<string, mixed>
     */
    public function jsonAll(): array
    {
        $body = file_get_contents('php://input');
        return json_decode($body ?: '', true) ?? [];
    }

    // ----------------------------------------------------------------
    // Request Meta
    // ----------------------------------------------------------------

    /**
     * Lấy HTTP method của request (GET, POST, PUT, DELETE).
     * Hỗ trợ method spoofing qua hidden field '_method' trong form HTML
     * (vì HTML form chỉ hỗ trợ GET và POST).
     *
     * @return string Uppercase HTTP method.
     */
    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Method spoofing: HTML form gửi POST với hidden _method=PUT/DELETE
        if ($method === 'POST') {
            $spoofed = strtoupper($_POST['_method'] ?? '');
            if (in_array($spoofed, ['PUT', 'DELETE', 'PATCH'], true)) {
                return $spoofed;
            }
        }

        return $method;
    }

    /**
     * Lấy URI path của request (không bao gồm query string).
     *
     * @return string
     */
    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Kiểm tra request có phải là POST không.
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    /**
     * Kiểm tra request có phải là AJAX không.
     * Dựa vào header X-Requested-With mà api.js tự động gắn vào.
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * Lấy IP address của client.
     * Xử lý cả trường hợp request đi qua proxy/load balancer.
     *
     * @return string
     */
    public function ip(): string
    {
        // Ưu tiên lấy IP thật khi đi qua proxy
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X_FORWARDED_FOR có thể chứa nhiều IP, lấy IP đầu tiên
                $ip = trim(explode(',', $_SERVER[$header])[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Lấy User-Agent string của browser.
     *
     * @return string
     */
    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Lấy header value theo tên.
     * VD: $request->header('Authorization')
     *
     * @param  string      $name    Tên header (case-insensitive).
     * @param  string|null $default
     * @return string|null
     */
    public function header(string $name, ?string $default = null): ?string
    {
        // Chuyển header name thành format của $_SERVER
        // VD: 'X-CSRF-Token' → 'HTTP_X_CSRF_TOKEN'
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }

    /**
     * Kiểm tra request có phải HTTPS không.
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    }

    // ----------------------------------------------------------------
    // Setter (chỉ Router được gọi)
    // ----------------------------------------------------------------

    /**
     * Inject route parameters từ Router.
     * Method này chỉ được gọi bởi Router sau khi match route.
     *
     * @param  array<string, string> $params
     * @return void
     */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }
}