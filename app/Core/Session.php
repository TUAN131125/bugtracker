<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session – PHP Session Wrapper
 *
 * Cung cấp interface thống nhất cho PHP session management.
 * Đảm bảo session_start() chỉ gọi một lần.
 * Hỗ trợ session regeneration sau đăng nhập (chống Session Fixation).
 *
 * @package App\Core
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 3.3, Phần 4.6
 * @see     Task Assignment v1.0.0 – D1-008
 */
class Session
{
    /**
     * Flag kiểm tra session đã được start chưa.
     *
     * @var bool
     */
    private static bool $started = false;

    // ----------------------------------------------------------------
    // Session Lifecycle
    // ----------------------------------------------------------------

    /**
     * Khởi động session với security options tối ưu.
     * Gọi idempotent — an toàn khi gọi nhiều lần.
     *
     * @return void
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // Cấu hình security trước khi start session
        // Tất cả theo TDD Backend Phần 4.6
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                 || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        session_set_cookie_params([
            'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
            'path'     => '/',
            'domain'   => '',          // Empty = current domain only
            'secure'   => $isSecure,   // HTTPS only khi production
            'httponly' => true,        // Chống XSS đọc session cookie
            'samesite' => 'Strict',    // Chống CSRF qua cookie
        ]);

        // Tên session tùy chỉnh — tránh dùng tên mặc định PHPSESSID
        // vì nó dễ bị nhận dạng và fingerprint
        $sessionName = $_ENV['SESSION_NAME'] ?? 'bugtracker_session';
        session_name($sessionName);

        session_start();
        self::$started = true;
    }

    /**
     * Set giá trị vào session.
     * Hỗ trợ dot notation để set nested value.
     * VD: Session::set('user.role', 'admin') → $_SESSION['user']['role'] = 'admin'
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();

        if (str_contains($key, '.')) {
            self::setNested($key, $value);
            return;
        }

        $_SESSION[$key] = $value;
    }

    /**
     * Lấy giá trị từ session.
     * Hỗ trợ dot notation để get nested value.
     * VD: Session::get('user.id') → $_SESSION['user']['id']
     *
     * @param  string $key
     * @param  mixed  $default Giá trị trả về nếu key không tồn tại.
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        if (str_contains($key, '.')) {
            return self::getNested($key, $default);
        }

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Kiểm tra key có tồn tại trong session không.
     *
     * @param  string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Xóa một key khỏi session.
     *
     * @param  string $key
     * @return void
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Regenerate session ID.
     * PHẢI gọi sau khi đăng nhập thành công để chống Session Fixation attack.
     * Xóa session cũ, tạo session mới với data được giữ nguyên.
     *
     * @return void
     */
    public static function regenerate(): void
    {
        self::start();

        // true = xóa file session cũ trên server
        session_regenerate_id(true);
    }

    /**
     * Hủy toàn bộ session (dùng khi logout).
     * Xóa data, unset cookie, destroy session file.
     *
     * @return void
     */
    public static function destroy(): void
    {
        self::start();

        // Xóa toàn bộ session data
        $_SESSION = [];

        // Xóa session cookie trên browser
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Hủy session file trên server
        session_destroy();
        self::$started = false;
    }

    /**
     * Lấy session ID hiện tại.
     *
     * @return string
     */
    public static function getId(): string
    {
        self::start();
        return session_id();
    }

    /**
     * Lấy toàn bộ session data (debug only).
     * KHÔNG dùng trong production code để tránh lộ thông tin.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        self::start();
        return $_SESSION;
    }

    // ----------------------------------------------------------------
    // Auth Convenience Methods
    // Các helper thường dùng trong Middleware và Controller
    // ----------------------------------------------------------------

    /**
     * Lưu thông tin user vào session sau khi đăng nhập.
     * Gọi session_regenerate_id() trước khi set data.
     *
     * @param  array<string, mixed> $user            User data từ DB (KHÔNG bao gồm password).
     * @param  int|null             $activeWorkspaceId Workspace đang active.
     * @return void
     */
    public static function loginUser(array $user, ?int $activeWorkspaceId = null): void
    {
        self::start();

        // Bắt buộc regenerate trước khi set user data
        // Chống Session Fixation attack (TDD Phần 4.6)
        self::regenerate();

        $_SESSION['user_id']             = $user['id'];
        $_SESSION['user_name']           = $user['name'];
        $_SESSION['user_email']          = $user['email'];
        $_SESSION['active_workspace_id'] = $activeWorkspaceId;
    }

    /**
     * Kiểm tra user đã đăng nhập chưa.
     *
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        self::start();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Lấy user_id từ session.
     *
     * @return int|null
     */
    public static function getUserId(): ?int
    {
        self::start();
        $id = $_SESSION['user_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    /**
     * Lấy active_workspace_id từ session.
     * WorkspaceMiddleware sẽ validate ID này với DB.
     *
     * @return int|null
     */
    public static function getActiveWorkspaceId(): ?int
    {
        self::start();
        $id = $_SESSION['active_workspace_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    /**
     * Cập nhật active workspace.
     * Gọi khi user chuyển đổi workspace (UC-010).
     *
     * @param  int $workspaceId
     * @return void
     */
    public static function setActiveWorkspace(int $workspaceId): void
    {
        self::start();
        $_SESSION['active_workspace_id'] = $workspaceId;
    }

    // ----------------------------------------------------------------
    // Private Helpers – Dot Notation Support
    // ----------------------------------------------------------------

    /**
     * Set nested session value bằng dot notation.
     *
     * @param  string $key   VD: 'user.preferences.theme'
     * @param  mixed  $value
     * @return void
     */
    private static function setNested(string $key, mixed $value): void
    {
        $keys    = explode('.', $key);
        $current = &$_SESSION;

        foreach ($keys as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * Get nested session value bằng dot notation.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    private static function getNested(string $key, mixed $default): mixed
    {
        $keys    = explode('.', $key);
        $current = $_SESSION;

        foreach ($keys as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}