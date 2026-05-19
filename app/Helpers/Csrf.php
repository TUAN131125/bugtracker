<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Session;
use App\Core\Response;

/**
 * Csrf – CSRF Token Helper
 *
 * Sinh, validate và render CSRF token cho mọi form POST.
 * Token được lưu trong session và so sánh bằng hash_equals()
 * để chống timing attack.
 *
 * Cách dùng trong View (Dev 3):
 *   <?= Csrf::getHiddenInput() ?>
 *
 * Cách dùng trong Controller (Dev 1, Dev 2):
 *   Csrf::validateOrFail($request->post('csrf_token'));
 *
 * @package App\Helpers
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 3.4
 * @see     Task Assignment v1.0.0 – D1-015
 */
class Csrf
{
    /** Key lưu CSRF token trong session */
    private const SESSION_KEY = '_csrf_token';

    /**
     * Sinh CSRF token mới và lưu vào session.
     * Nếu token đã tồn tại trong session, trả về token cũ
     * (không sinh mới mỗi lần gọi — tránh invalidate form đang mở).
     *
     * @return string Token hex 64 chars.
     */
    public static function generateToken(): string
    {

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Lấy token hiện tại từ session (không sinh mới).
     *
     * @return string
     */
    public static function getToken(): string
    {
        return self::generateToken();
    }

    /**
     * Render hidden input HTML chứa CSRF token.
     * Dev 3 đặt dòng này trong mọi form POST.
     *
     * @return string HTML: <input type="hidden" name="csrf_token" value="...">
     */
    public static function getHiddenInput(): string
    {
        $token = self::generateToken();
        // htmlspecialchars phòng ngừa XSS trong attribute
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
    }

    /**
     * Validate CSRF token từ request.
     * Dùng hash_equals() để chống timing attack.
     *
     * @param  string $submittedToken Token từ $_POST['csrf_token'].
     * @return bool
     */
    public static function validateToken(string $submittedToken): bool
    {

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';

        if (empty($sessionToken) || empty($submittedToken)) {
            return false;
        }

        // hash_equals: constant time comparison (TDD Phần 1.4.3)
        return hash_equals($sessionToken, $submittedToken);
    }

    /**
     * Validate token và tự động fail (403) nếu không hợp lệ.
     * Shorthand dùng trong Controller — không cần if/else.
     *
     * Sau khi validate thành công, regenerate token để
     * mỗi form submission dùng token khác nhau.
     *
     * @param  string $submittedToken
     * @return void   Tiếp tục nếu hợp lệ. Trả về 403 và exit nếu không.
     */
    public static function validateOrFail(string $submittedToken): void
    {
        if (!self::validateToken($submittedToken)) {
            error_log(sprintf(
                '[CSRF] Token validation failed. IP: %s | URI: %s',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['REQUEST_URI'] ?? 'unknown'
            ));

            // Kiểm tra AJAX request
            $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

            if ($isAjax) {
                Response::json([
                    'success' => false,
                    'message' => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
                    'code'    => 403,
                ], 403);
            }

            http_response_code(403);
            $viewPath = dirname(__DIR__) . '/Views/errors/403.php';
            if (file_exists($viewPath)) {
                include $viewPath;
            } else {
                echo '<h1>403 – CSRF Token không hợp lệ</h1>';
                echo '<p>Phiên làm việc đã hết hạn. <a href="javascript:history.back()">Quay lại</a></p>';
            }
            exit();
        }

        // Regenerate token sau mỗi submit thành công
        self::regenerateToken();
    }

    /**
     * Tạo token mới, hủy token cũ.
     * Gọi sau khi validate thành công.
     *
     * @return string Token mới.
     */
    public static function regenerateToken(): string
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::SESSION_KEY];
    }
}