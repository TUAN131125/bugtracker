<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;

/**
 * WorkspaceMiddleware – Validate active_workspace_id
 *
 * Đây là lớp bảo vệ quan trọng nhất chống Horizontal Privilege
 * Escalation trong môi trường multi-tenant.
 *
 * Kiểm tra active_workspace_id trong session có thực sự tồn tại
 * trong workspace_members của user hiện tại không.
 * Nếu không → session có thể bị giả mạo hoặc workspace đã bị xóa.
 *
 * @package App\Middleware
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 2.1 (Multi-tenant isolation)
 * @see     TDD Backend v1.0.0 – Phần 3.4
 * @see     SRS v1.0.0 – Phần 2.4 (Luồng 3 – Branch Workspace)
 * @see     Task Assignment v1.0.0 – D1-011
 */
class WorkspaceMiddleware
{
    /**
     * @param  Request     $request
     * @param  string|null $param
     * @return void
     */
    public function handle(Request $request, ?string $param = null): void
    {
        $userId            = Session::getUserId();
        $activeWorkspaceId = Session::getActiveWorkspaceId();

        // Nếu chưa có active workspace → OnboardingMiddleware sẽ xử lý
        if (!$activeWorkspaceId) {
            return;
        }

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT wm.role
                 FROM workspace_members wm
                 JOIN workspaces w ON w.id = wm.workspace_id
                 WHERE wm.workspace_id = :workspace_id
                   AND wm.user_id      = :user_id
                   AND w.deleted_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([
                ':workspace_id' => $activeWorkspaceId,
                ':user_id'      => $userId,
            ]);
            $membership = $stmt->fetch();

            if (!$membership) {
                // Workspace không tồn tại hoặc user đã bị kick
                // Xóa active_workspace_id khỏi session
                Session::remove('active_workspace_id');
                Session::remove('onboarding_completed');
                Session::remove('current_role');

                Response::setFlash(
                    'warning',
                    'Workspace không còn khả dụng. Vui lòng chọn hoặc tạo Workspace mới.'
                );
                Response::redirect('/onboarding');
            }

            // Cache role vào session để RbacMiddleware đọc
            // mà không cần query lại DB
            Session::set('current_role', $membership['role']);

        } catch (\PDOException $e) {
            error_log('[WorkspaceMiddleware] DB error: ' . $e->getMessage());
            // Fail open — không block nếu DB lỗi tạm thời
        }
    }
}