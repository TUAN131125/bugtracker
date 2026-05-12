<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * AuthMiddleware – Kiểm tra session đăng nhập hợp lệ
 *
 * Chạy đầu tiên trong middleware chain cho mọi route
 * yêu cầu xác thực. Nếu không có session hợp lệ,
 * kiểm tra tiếp Remember Me cookie trước khi redirect login.
 *
 * Thứ tự chain: AuthMiddleware → OnboardingMiddleware
 *             → WorkspaceMiddleware → RbacMiddleware
 *
 * @package App\Middleware
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 3.4
 * @see     Task Assignment v1.0.0 – D1-011
 */
class AuthMiddleware
{
    /**
     * Xử lý kiểm tra xác thực.
     *
     * @param  Request     $request
     * @param  string|null $param   Không dùng trong middleware này.
     * @return void        Tiếp tục nếu hợp lệ. Gọi exit() nếu không.
     */
    public function handle(Request $request, ?string $param = null): void
    {
        Session::start();

        // Bước 1: Kiểm tra session trực tiếp
        if (Session::isLoggedIn()) {
            return; // Hợp lệ — tiếp tục chain
        }

        // Bước 2: Kiểm tra Remember Me cookie
        // Nếu có cookie hợp lệ → tự động đăng nhập lại
        if ($this->attemptRememberMeLogin($request)) {
            return; // Đăng nhập lại thành công — tiếp tục chain
        }

        // Bước 3: Không có session và không có cookie hợp lệ
        // Lưu intended URL để redirect sau khi login thành công
        $currentUri = $request->uri();
        if ($currentUri !== '/login' && $currentUri !== '/') {
            Session::set('intended_url', $currentUri);
        }

        Response::setFlash('info', 'Vui lòng đăng nhập để tiếp tục.');
        Response::redirect('/login');
    }

    /**
     * Thử đăng nhập lại bằng Remember Me cookie.
     *
     * @param  Request $request
     * @return bool    true nếu tự đăng nhập thành công.
     */
    private function attemptRememberMeLogin(Request $request): bool
    {
        $cookieName = 'remember_token';

        if (empty($_COOKIE[$cookieName])) {
            return false;
        }

        $rawToken = $_COOKIE[$cookieName];

        // Hash token để so sánh với DB (không lưu raw token trong DB)
        // Dùng SHA-256 vì đây là lookup hash, không phải password hash
        $tokenHash = hash('sha256', $rawToken);

        try {
            $db   = \App\Core\Database::getInstance();
            $stmt = $db->prepare(
                'SELECT ut.user_id, ut.expires_at,
                        u.id, u.name, u.email,
                        u.is_verified, u.onboarding_completed
                 FROM user_tokens ut
                 JOIN users u ON u.id = ut.user_id
                 WHERE ut.token_hash = :token_hash
                   AND ut.expires_at > NOW()
                   AND u.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([':token_hash' => $tokenHash]);
            $row = $stmt->fetch();

            if (!$row) {
                // Token không tồn tại hoặc hết hạn → xóa cookie
                $this->clearRememberMeCookie();
                return false;
            }

            // Tìm workspace đầu tiên của user để set active
            $wsStmt = $db->prepare(
                'SELECT workspace_id FROM workspace_members
                 WHERE user_id = :user_id
                 ORDER BY joined_at ASC
                 LIMIT 1'
            );
            $wsStmt->execute([':user_id' => $row['user_id']]);
            $workspace = $wsStmt->fetch();

            // Đăng nhập lại — regenerate session
            Session::loginUser(
                [
                    'id'    => $row['id'],
                    'name'  => $row['name'],
                    'email' => $row['email'],
                ],
                $workspace ? (int) $workspace['workspace_id'] : null
            );

            return true;

        } catch (\PDOException $e) {
            // Silent fail — không crash app chỉ vì Remember Me lỗi
            error_log('[AuthMiddleware] Remember Me DB error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Xóa Remember Me cookie trên browser.
     *
     * @return void
     */
    private function clearRememberMeCookie(): void
    {
        setcookie(
            'remember_token',
            '',
            time() - 3600,
            '/',
            '',
            true,
            true
        );
    }
}