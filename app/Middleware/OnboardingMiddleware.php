<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;

/**
 * OnboardingMiddleware – Kiểm tra user đã có Workspace chưa
 *
 * Chạy sau AuthMiddleware. Nếu user chưa thuộc Workspace nào
 * → redirect /onboarding để chọn tạo mới hoặc tham gia.
 *
 * Bypass hoàn toàn cho các route /onboarding và /workspace/create
 * để tránh redirect loop.
 *
 * @package App\Middleware
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 3.4
 * @see     SRS v1.0.0 – Phần 2.2 (Luồng 1 – Brand New User)
 * @see     Task Assignment v1.0.0 – D1-011
 */
class OnboardingMiddleware
{
    /**
     * Danh sách URI pattern được bypass (không kiểm tra onboarding).
     *
     * @var array<string>
     */
    private array $bypassPatterns = [
        '/onboarding',
        '/workspace/create',
        '/invite/',
        '/logout',
        // Thêm vào — các route public không cần workspace
        '/login',
        '/register',
        '/verify-email',
        '/forgot-password',
        '/reset-password',
        '/resend-verification',
    ];

    /**
     * @param  Request     $request
     * @param  string|null $param
     * @return void
     */
    public function handle(Request $request, ?string $param = null): void
    {
        $uri = $request->uri();

        // Bỏ qua kiểm tra cho các route bypass
        foreach ($this->bypassPatterns as $pattern) {
            if (str_starts_with($uri, $pattern)) {
                return;
            }
        }

        // Kiểm tra session flag trước để tránh DB query mỗi request
        if (Session::get('onboarding_completed') === true) {
            return;
        }

        $userId = Session::getUserId();

        if (!$userId) {
            return; // AuthMiddleware đã xử lý — không cần làm gì thêm
        }

        try {
            // Kiểm tra user có thuộc workspace nào không
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT COUNT(*) as total
                 FROM workspace_members wm
                 JOIN workspaces w ON w.id = wm.workspace_id
                 WHERE wm.user_id = :user_id
                   AND w.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([':user_id' => $userId]);
            $result = $stmt->fetch();

            if ((int) $result['total'] === 0) {
                // Chưa có workspace → redirect onboarding
                Response::redirect('/onboarding');
            }

            // Có workspace → cache vào session để tránh query lần sau
            Session::set('onboarding_completed', true);

        } catch (\PDOException $e) {
            error_log('[OnboardingMiddleware] DB error: ' . $e->getMessage());
            // Fail open: không block user nếu DB lỗi tạm thời
        }
    }
}