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
 * Remember Me: HttpOnly cookie + DB token.
 *
 * @package App\Controllers\Auth
 * @version 1.0.0
 * @see     SRS v1.0.0 – UC-003, UC-004
 * @see     TDD Backend v1.0.0 – Phần 1.4 (Token security)
 * @see     Task Assignment v1.0.0 – D1-014
 */
class LoginController
{
    /** Số lần login thất bại tối đa trước khi lock */
    private const MAX_ATTEMPTS = 5;

    /** Thời gian lock (phút) */
    private const LOCK_MINUTES = 15;

    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Redirect trang chủ về login hoặc dashboard.
     * GET /
     *
     * @param  Request $request
     * @return void
     */
    public function index(Request $request): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/dashboard');
        }
        Response::redirect('/login');
    }

    /**
     * Hiển thị form đăng nhập.
     * GET /login
     *
     * @param  Request $request
     * @return void
     */
    public function showForm(Request $request): void
    {
        if (Session::isLoggedIn()) {
            Response::redirect('/dashboard');
        }

        Response::view('auth/login', [
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
     *
     * @param  Request $request
     * @return void
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
                'Tài khoản tạm thời bị khóa do đăng nhập sai nhiều lần. '
                . 'Vui lòng thử lại sau ' . self::LOCK_MINUTES . ' phút.'
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
            // Không tiết lộ email có tồn tại hay không (chống user enumeration)
            $this->recordFailedAttempt($clientIp, $email);
            Response::setFlash('error', 'Email hoặc mật khẩu không đúng.');
            Response::setOldInput(['email' => $email]);
            Response::redirect('/login');
        }

        // Bước 5: Kiểm tra email đã xác minh chưa
        if (!(bool) $user['is_verified']) {
            Response::setFlash(
                'error',
                'Tài khoản chưa được xác minh. Kiểm tra email hoặc '
                . '<a href="/resend-verification" class="underline">nhấn đây để gửi lại</a>.'
            );
            Response::setOldInput(['email' => $email]);
            Response::redirect('/login');
        }

        // Bước 6: Verify password
        if (!password_verify($password, $user['password'])) {
            $this->recordFailedAttempt($clientIp, $email);

            // Cảnh báo nếu sắp bị lock
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

        // Lấy workspace đầu tiên làm active
        $workspaces       = $this->userModel->getWorkspaces((int) $user['id']);
        $activeWorkspaceId = !empty($workspaces) ? (int) $workspaces[0]['id'] : null;

        // Session::loginUser() tự gọi regenerate() (TDD Phần 4.6)
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

        // Bước 9: Redirect về intended URL hoặc dashboard
        $intendedUrl = Session::get('intended_url', '/dashboard');
        Session::remove('intended_url');

        Response::redirect($intendedUrl);
    }

    /**
     * Đăng xuất.
     * POST /logout
     *
     * @param  Request $request
     * @return void
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
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);

        // Hủy session
        Session::destroy();

        Response::setFlash('success', 'Đã đăng xuất thành công.');
        Response::redirect('/login');
    }

    // ----------------------------------------------------------------
    // Private Helpers – Rate Limiting
    // ----------------------------------------------------------------

    /**
     * Kiểm tra IP có đang bị lock không.
     *
     * @param  string $ip
     * @return bool
     */
    private function isIpLocked(string $ip): bool
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT COUNT(*) as attempts
                 FROM login_attempts
                 WHERE ip_address = :ip
                   AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
            );
            $stmt->execute([
                ':ip'      => $ip,
                ':minutes' => self::LOCK_MINUTES,
            ]);
            $result = $stmt->fetch();
            return (int) $result['attempts'] >= self::MAX_ATTEMPTS;
        } catch (\PDOException $e) {
            error_log('[LoginController] Rate limit check failed: ' . $e->getMessage());
            return false; // Fail open
        }
    }

    /**
     * Lấy số lần thử còn lại trước khi bị lock.
     *
     * @param  string $ip
     * @return int
     */
    private function getRemainingAttempts(string $ip): int
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT COUNT(*) as attempts
                 FROM login_attempts
                 WHERE ip_address = :ip
                   AND attempted_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
            );
            $stmt->execute([':ip' => $ip, ':minutes' => self::LOCK_MINUTES]);
            $result = $stmt->fetch();
            return max(0, self::MAX_ATTEMPTS - (int) $result['attempts']);
        } catch (\PDOException $e) {
            return self::MAX_ATTEMPTS;
        }
    }

    /**
     * Ghi lại một lần login thất bại.
     *
     * @param  string $ip
     * @param  string $email
     * @return void
     */
    private function recordFailedAttempt(string $ip, string $email): void
    {
        try {
            // Lazy cleanup: xóa attempts cũ của IP này trước khi thêm mới
            $db = Database::getInstance();
            $db->prepare(
                'DELETE FROM login_attempts
                 WHERE ip_address = :ip
                   AND attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            )->execute([':ip' => $ip]);

            $db->prepare(
                'INSERT INTO login_attempts (ip_address, email_attempted, attempted_at)
                 VALUES (:ip, :email, NOW())'
            )->execute([':ip' => $ip, ':email' => $email]);

        } catch (\PDOException $e) {
            error_log('[LoginController] Record attempt failed: ' . $e->getMessage());
        }
    }

    /**
     * Xóa tất cả failed attempts của IP sau login thành công.
     *
     * @param  string $ip
     * @return void
     */
    private function clearFailedAttempts(string $ip): void
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'DELETE FROM login_attempts WHERE ip_address = :ip'
            );
            $stmt->execute([':ip' => $ip]);
        } catch (\PDOException $e) {
            error_log('[LoginController] Clear attempts failed: ' . $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // Private Helpers – Remember Me
    // ----------------------------------------------------------------

    /**
     * Tạo Remember Me cookie và lưu token hash vào DB.
     *
     * @param  int     $userId
     * @param  Request $request
     * @return void
     */
    private function setRememberMeCookie(int $userId, Request $request): void
    {
        // Sinh raw token bằng CSPRNG
        $rawToken  = bin2hex(random_bytes(32));

        // Lưu hash vào DB, không lưu raw (TDD Phần 1.4.1)
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (REMEMBER_ME_DAYS * 86400));

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'INSERT INTO user_tokens
                    (user_id, token_hash, expires_at, ip_address, user_agent)
                 VALUES
                    (:user_id, :token_hash, :expires_at, :ip, :ua)'
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
            return; // Không crash — Remember Me là optional feature
        }

        // Set cookie (TDD Phần 4.6: HttpOnly, Secure, SameSite=Strict)
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie(
            'remember_token',
            $rawToken,
            [
                'expires'  => time() + (REMEMBER_ME_DAYS * 86400),
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );
    }

    /**
     * Revoke Remember Me token trong DB khi logout.
     *
     * @param  string $rawToken
     * @return void
     */
    private function revokeRememberMeToken(string $rawToken): void
    {
        $tokenHash = hash('sha256', $rawToken);

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'DELETE FROM user_tokens WHERE token_hash = :token_hash'
            );
            $stmt->execute([':token_hash' => $tokenHash]);
        } catch (\PDOException $e) {
            error_log('[LoginController] Revoke token failed: ' . $e->getMessage());
        }
    }
}