<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Helpers\Csrf;
use App\Models\User;

/**
 * LoginController – Đăng nhập và Đăng xuất
 *
 * Xử lý UC-003 (Đăng nhập) và UC-004 (Đăng xuất).
 * Rate limiting: 5 lần sai/15 phút → lock IP.
 * Remember Me: HttpOnly cookie + DB token (Auto-login).
 *
 * @package App\Controllers\Auth
 * @version 1.0.1
 */
class LoginController
{
    /** Số lần login thất bại tối đa trước khi lock */
    private const MAX_ATTEMPTS = 5;

    /** Thời gian lock (phút) */
    private const LOCK_MINUTES = 15;

    /** Thời gian sống của Remember Me cookie (ngày) */
    private const REMEMBER_ME_DAYS = 30;

    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Redirect trang chủ về login hoặc dashboard/onboarding.
     * GET /
     */
    public function index(Request $request): void
    {
        if (Session::isLoggedIn() || $this->attemptAutoLogin($request)) {
            $this->redirectBasedOnWorkspace();
        }
        Response::redirect('/login');
    }

    /**
     * Hiển thị form đăng nhập.
     * GET /login
     */
    public function showForm(Request $request): void
    {
        // Kiểm tra session hiện tại hoặc thử auto-login qua Remember Me cookie
        if (Session::isLoggedIn() || $this->attemptAutoLogin($request)) {
            $this->redirectBasedOnWorkspace();
        }

        Response::view('auth/login', [
            'pageId'     => 'login',
            'pageTitle'  => 'Đăng nhập',
            'csrfToken'  => Csrf::generateToken(),
            'oldInput'   => Response::getOldInput(),
            'flashError' => Response::getFlash('error'),
            'flashInfo'  => Response::getFlash('info'),
        ]);
    }

    /**
     * Xử lý submit form đăng nhập.
     * POST /login
     */
    public function login(Request $request): void
    {
        // Bước 1: Validate CSRF
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $email      = trim(strtolower($request->post('email', '')));
        $password   = $request->post('password', '');
        $rememberMe = (bool) $request->post('remember_me', false);
        $clientIp   = $request->ip();

        // Bước 2: Kiểm tra rate limit
        if ($this->isIpLocked($clientIp)) {
            Response::setFlash(
                'error',
                'Tài khoản tạm thời bị khóa do đăng nhập sai nhiều lần. Vui lòng thử lại sau ' . self::LOCK_MINUTES . ' phút.'
            );
            Response::setOldInput(['email' => $email]);
            Response::redirect('/login');
        }

        // Bước 3: Validate input cơ bản
        if (empty($email) || empty($password)) {
            $this->recordFailedAttempt($clientIp, $email);
            Response::setFlash('error', 'Email hoặc mật khẩu không đúng.');
            Response::setOldInput(['email' => $email]);
            Response::redirect('/login');
        }

        // Bước 4: Tìm user theo email
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            $this->recordFailedAttempt($clientIp, $email);
            Response::setFlash('error', 'Email hoặc mật khẩu không đúng.');
            Response::setOldInput(['email' => $email]);
            Response::redirect('/login');
        }

        // Bước 5: Kiểm tra email đã xác minh chưa
        if (!(bool) $user['is_verified']) {
            Response::setFlash(
                'error',
                'Tài khoản chưa được xác minh. Kiểm tra email hoặc <a href="/resend-verification" class="underline">nhấn đây để gửi lại</a>.'
            );
            Response::setOldInput(['email' => $email]);
            Response::redirect('/login');
        }

        // Bước 6: Verify password
        if (!password_verify($password, $user['password'])) {
            $this->recordFailedAttempt($clientIp, $email);

            $remainingAttempts = $this->getRemainingAttempts($clientIp);
            $message = 'Email hoặc mật khẩu không đúng.';
            if ($remainingAttempts === 1) {
                $message .= ' Tài khoản sẽ bị khóa tạm thời sau 1 lần thất bại nữa.';
            }

            Response::setFlash('error', $message);
            Response::setOldInput(['email' => $email]);
            Response::redirect('/login');
        }

        // Bước 7: Đăng nhập thành công — xóa failed attempts
        $this->clearFailedAttempts($clientIp);

        $workspaces        = $this->userModel->getWorkspaces((int) $user['id']);
        $activeWorkspaceId = !empty($workspaces) ? (int) $workspaces[0]['id'] : null;

        Session::loginUser(
            [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
            ],
            $activeWorkspaceId
        );

        // Bước 8: Xử lý Remember Me
        if ($rememberMe) {
            $this->setRememberMeCookie((int) $user['id'], $request);
        }

        // Bước 9: Redirect
        if ($activeWorkspaceId === null) {
            Response::redirect('/onboarding');
        }

        $intendedUrl = Session::get('intended_url', '/dashboard');
        Session::remove('intended_url');
        Response::redirect($intendedUrl);
    }

    /**
     * Đăng xuất.
     * POST /logout
     */
    public function logout(Request $request): void
    {
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $userId = Session::getUserId();

        // Revoke Remember Me token trong DB
        if ($userId && !empty($_COOKIE['remember_token'])) {
            $this->revokeRememberMeToken($_COOKIE['remember_token']);
        }

        // Xóa cookie
        $this->clearRememberMeCookie();

        // Hủy session
        Session::destroy();

        Response::setFlash('success', 'Đã đăng xuất thành công.');
        Response::redirect('/login');
    }

    // ----------------------------------------------------------------
    // Private Helpers – Tích hợp Remember Me & Auto Login
    // ----------------------------------------------------------------

    /**
     * Thử auto-login bằng Remember Me cookie.
     */
    private function attemptAutoLogin(Request $request): bool
    {
        $rawToken = $_COOKIE['remember_token'] ?? null;
        if (!$rawToken) {
            return false;
        }

        $tokenHash = hash('sha256', $rawToken);

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT u.id, u.name, u.email, u.is_verified, u.deleted_at
                 FROM user_tokens ut
                 JOIN users u ON u.id = ut.user_id
                 WHERE ut.token_hash = :token_hash
                   AND ut.expires_at > NOW()
                 LIMIT 1'
            );
            $stmt->execute([':token_hash' => $tokenHash]);
            $user = $stmt->fetch();

            // Nếu token không tồn tại, hết hạn, hoặc tài khoản có vấn đề
            if (!$user || $user['deleted_at'] !== null || !(bool) $user['is_verified']) {
                $this->clearRememberMeCookie();
                return false;
            }

            // Hợp lệ -> Đăng nhập tự động
            $workspaces        = $this->userModel->getWorkspaces((int) $user['id']);
            $activeWorkspaceId = !empty($workspaces) ? (int) $workspaces[0]['id'] : null;

            Session::loginUser(
                [
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                ],
                $activeWorkspaceId
            );

            return true;

        } catch (\PDOException $e) {
            error_log('[LoginController] Auto-login failed: ' . $e->getMessage());
            return false;
        }
    }

    private function setRememberMeCookie(int $userId, Request $request): void
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::REMEMBER_ME_DAYS * 86400));

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'INSERT INTO user_tokens (user_id, token_hash, expires_at, ip_address, user_agent)
                 VALUES (:user_id, :token_hash, :expires_at, :ip, :ua)'
            );
            $stmt->execute([
                ':user_id'    => $userId,
                ':token_hash' => $tokenHash,
                ':expires_at' => $expiresAt,
                ':ip'         => $request->ip(),
                ':ua'         => substr($request->userAgent(), 0, 500),
            ]);
        } catch (\PDOException $e) {
            error_log('[LoginController] Remember Me DB insert failed: ' . $e->getMessage());
            return; 
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie('remember_token', $rawToken, [
            'expires'  => time() + (self::REMEMBER_ME_DAYS * 86400),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function revokeRememberMeToken(string $rawToken): void
    {
        $tokenHash = hash('sha256', $rawToken);
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare('DELETE FROM user_tokens WHERE token_hash = :token_hash');
            $stmt->execute([':token_hash' => $tokenHash]);
        } catch (\PDOException $e) {
            error_log('[LoginController] Revoke token failed: ' . $e->getMessage());
        }
    }

    private function clearRememberMeCookie(): void
    {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function redirectBasedOnWorkspace(): void
    {
        $activeWorkspaceId = Session::get('active_workspace_id');
        if (!$activeWorkspaceId) {
            Response::redirect('/onboarding');
        }

        $intendedUrl = Session::get('intended_url', '/dashboard');
        Session::remove('intended_url');
        Response::redirect($intendedUrl);
    }

    // ----------------------------------------------------------------
    // Private Helpers – Rate Limiting
    // ----------------------------------------------------------------

    private function isIpLocked(string $ip): bool
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT COUNT(*) as attempts FROM login_attempts
                 WHERE ip_address = :ip AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
            );
            $stmt->execute([':ip' => $ip, ':minutes' => self::LOCK_MINUTES]);
            $result = $stmt->fetch();
            return (int) $result['attempts'] >= self::MAX_ATTEMPTS;
        } catch (\PDOException $e) {
            error_log('[LoginController] Rate limit check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function getRemainingAttempts(string $ip): int
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT COUNT(*) as attempts FROM login_attempts
                 WHERE ip_address = :ip AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
            );
            $stmt->execute([':ip' => $ip, ':minutes' => self::LOCK_MINUTES]);
            $result = $stmt->fetch();
            return max(0, self::MAX_ATTEMPTS - (int) $result['attempts']);
        } catch (\PDOException $e) {
            return self::MAX_ATTEMPTS;
        }
    }

    private function recordFailedAttempt(string $ip, string $email): void
    {
        try {
            $db = Database::getInstance();
            $db->prepare(
                'DELETE FROM login_attempts WHERE ip_address = :ip AND attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            )->execute([':ip' => $ip]);

            $db->prepare(
                'INSERT INTO login_attempts (ip_address, email_attempted, attempted_at) VALUES (:ip, :email, NOW())'
            )->execute([':ip' => $ip, ':email' => $email]);
        } catch (\PDOException $e) {
            error_log('[LoginController] Record attempt failed: ' . $e->getMessage());
        }
    }

    private function clearFailedAttempts(string $ip): void
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare('DELETE FROM login_attempts WHERE ip_address = :ip');
            $stmt->execute([':ip' => $ip]);
        } catch (\PDOException $e) {
            error_log('[LoginController] Clear attempts failed: ' . $e->getMessage());
        }
    }
}