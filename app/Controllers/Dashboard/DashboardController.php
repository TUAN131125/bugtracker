<?php
// /app/Controllers/Dashboard/DashboardController.php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Logger;

/**
 * DashboardController
 *
 * Chịu trách nhiệm cung cấp dữ liệu tổng hợp cho trang Dashboard.
 * Dev 3 đọc contract data tại Phần 5.1 – Task Assignment Document.
 *
 * QUAN TRỌNG (InfinityFree): Tất cả query dùng JOIN + GROUP BY trong một lần gọi DB.
 * Tuyệt đối không query trong vòng lặp (N+1).
 *
 * @author  Dev 1
 * @version 1.0.0
 */
class DashboardController
{
    private \PDO $db;

    public function __construct()
    {
        // Database singleton – không tạo connection mới mỗi lần gọi
        $this->db = Database::getInstance();
    }

    /**
     * index()
     *
     * Entry point duy nhất của Dashboard.
     * Gom tất cả aggregate queries vào đây, truyền data cho View.
     *
     * Route: GET /dashboard (đăng ký trong routes.php)
     * Middleware chain: AuthMiddleware → WorkspaceMiddleware → OnboardingMiddleware
     *
     * @return void
     */
    public function index(): void
    {
        // ----------------------------------------------------------------
        // 1. Lấy context từ session (đã được WorkspaceMiddleware validate)
        // ----------------------------------------------------------------
        $workspaceId = (int) ($_SESSION['active_workspace_id'] ?? 0);
        $userId      = (int) ($_SESSION['user_id'] ?? 0);

        // Guard: Middleware phải đảm bảo 2 giá trị này hợp lệ.
        // Nếu vẫn = 0 tại đây → có lỗi middleware, log và redirect.
        if ($workspaceId === 0 || $userId === 0) {
            Logger::error(
                'DashboardController::index() called with invalid session',
                'DashboardController',
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
            );
            Response::redirect('/onboarding');
            return;
        }

        try {
            // ----------------------------------------------------------------
            // 2. Query tổng hợp Issue theo status (cho Donut Chart)
            //    Một query duy nhất, GROUP BY status
            //    Index (workspace_id, status) đảm bảo không full table scan
            // ----------------------------------------------------------------
            $statusCounts = $this->getIssueStatusCounts($workspaceId);

            // ----------------------------------------------------------------
            // 3. Query số Issue theo từng Project (cho Bar Chart)
            //    JOIN projects để lấy tên project kèm theo count
            // ----------------------------------------------------------------
            $projectCounts = $this->getIssueCountByProject($workspaceId);

            // ----------------------------------------------------------------
            // 4. Query Issue được giao cho user hiện tại (Widget "Của tôi")
            //    Chỉ lấy 5 issue mới nhất, status != closed và != wont_fix
            // ----------------------------------------------------------------
            $myIssues = $this->getMyAssignedIssues($workspaceId, $userId);

            // ----------------------------------------------------------------
            // 5. Query Activity Log gần nhất (Widget "Hoạt động")
            //    JOIN users để lấy tên người thực hiện
            //    Giới hạn 10 bản ghi, index (workspace_id, created_at)
            // ----------------------------------------------------------------
            $recentActivity = $this->getRecentActivity($workspaceId);

            // ----------------------------------------------------------------
            // 6. Đếm notification chưa đọc (badge trên bell icon)
            //    Index (user_id, is_read) đảm bảo query này rất nhanh
            // ----------------------------------------------------------------
            $unreadNotifCount = $this->getUnreadNotificationCount($userId, $workspaceId);

            // ----------------------------------------------------------------
            // 7. Gom thành $chart_data theo đúng contract với Dev 3 (Phần 5.1)
            //    Dev 3 sẽ json_encode($chart_data) để Chart.js đọc
            // ----------------------------------------------------------------
            $chartData = [
                'status_counts'  => $statusCounts,
                'project_counts' => $projectCounts,
            ];

            // ----------------------------------------------------------------
            // 8. Render view – truyền đúng tên biến theo contract Phần 5.1
            // ----------------------------------------------------------------
            Response::view('dashboard/index', [
                'pageId'             => 'dashboard',
                'pageTitle'          => 'Dashboard',
                'chart_data'         => $chartData,
                'my_issues'          => $myIssues,
                'recent_activity'    => $recentActivity,
                'unread_notif_count' => $unreadNotifCount,
            ]);

        } catch (\PDOException $e) {
            // Ghi log lỗi DB, KHÔNG lộ thông tin ra màn hình
            Logger::error(
                'Dashboard query failed: ' . $e->getMessage(),
                'DashboardController',
                $e->getTrace()
            );
            // Dev 3 đã chuẩn bị trang 500 thân thiện
            Response::view('errors/500', ['pageId' => 'error', 'pageTitle' => 'Lỗi hệ thống']);
        }
    }

    // ================================================================
    // PRIVATE METHODS – Mỗi method = 1 nhóm query có liên quan
    // Tách ra để dễ test độc lập và dễ đọc
    // ================================================================

    /**
     * Lấy số lượng Issue theo từng trạng thái trong Workspace.
     *
     * SQL pattern: GROUP BY status → một query, nhiều row kết quả.
     * Kết quả trả về dạng array key-value để Dev 3 dễ dùng với Chart.js:
     * [
     *   ['status' => 'open',        'count' => 12, 'label' => 'Mới'],
     *   ['status' => 'in_progress', 'count' => 5,  'label' => 'Đang xử lý'],
     *   ...
     * ]
     *
     * @param int $workspaceId
     * @return array
     */
    private function getIssueStatusCounts(int $workspaceId): array
    {
        // Map status DB value → label tiếng Việt (đồng bộ với ViewLayer Guide Appendix C)
        $labelMap = [
            'open'        => 'Mới',
            'in_triage'   => 'Đang xem xét',
            'in_progress' => 'Đang xử lý',
            'resolved'    => 'Đã giải quyết',
            'closed'      => 'Đã đóng',
            'reopened'    => 'Mở lại',
            'wont_fix'    => 'Không sửa',
            'duplicate'   => 'Trùng lặp',
        ];

        $stmt = $this->db->prepare(
            "SELECT status, COUNT(*) AS count
             FROM issues
             WHERE workspace_id = :workspace_id
               AND deleted_at IS NULL
             GROUP BY status
             ORDER BY FIELD(status, 'open','in_triage','in_progress','resolved','closed','reopened','wont_fix','duplicate')"
        );
        $stmt->execute([':workspace_id' => $workspaceId]);
        $rows = $stmt->fetchAll();

        // Gắn label tiếng Việt để Dev 3 render trực tiếp, không cần xử lý thêm
        return array_map(function (array $row) use ($labelMap): array {
            return [
                'status' => $row['status'],
                'count'  => (int) $row['count'],
                'label'  => $labelMap[$row['status']] ?? $row['status'],
            ];
        }, $rows);
    }

    /**
     * Lấy số lượng Issue theo từng Project (cho Bar Chart).
     *
     * JOIN projects để lấy project name và key.
     * Chỉ lấy project đang active (không archive).
     * Chỉ tính issue chưa bị soft delete.
     *
     * Kết quả:
     * [
     *   ['project_name' => 'BugTracker', 'project_key' => 'BT', 'count' => 42],
     *   ...
     * ]
     *
     * @param int $workspaceId
     * @return array
     */
    private function getIssueCountByProject(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.name AS project_name,
                    p.key  AS project_key,
                    COUNT(i.id) AS count
             FROM projects p
             LEFT JOIN issues i
                    ON i.project_id   = p.id
                   AND i.workspace_id = :workspace_id_issues
                   AND i.deleted_at IS NULL
             WHERE p.workspace_id = :workspace_id_projects
               AND p.status       = 'active'
               AND p.deleted_at IS NULL
             GROUP BY p.id, p.name, p.key
             ORDER BY count DESC
             LIMIT 10"
            // Giới hạn 10 project trên Bar Chart – tránh chart quá chật
        );
        $stmt->execute([
            ':workspace_id_issues'   => $workspaceId,
            ':workspace_id_projects' => $workspaceId,
        ]);

        return array_map(function (array $row): array {
            return [
                'project_name' => $row['project_name'],
                'project_key'  => $row['project_key'],
                'count'        => (int) $row['count'],
            ];
        }, $stmt->fetchAll());
    }

    /**
     * Lấy 5 Issue mới nhất được giao cho user hiện tại.
     *
     * Chỉ hiện issue đang "cần làm" (không lấy closed/wont_fix/duplicate).
     * JOIN projects để lấy project key (dùng hiển thị Issue ID dạng BT-001).
     *
     * Kết quả:
     * [
     *   [
     *     'issue_key'    => 'BT-007',
     *     'title'        => 'Login button broken',
     *     'status'       => 'in_progress',
     *     'priority'     => 'urgent',
     *     'project_name' => 'BugTracker',
     *     'updated_at'   => '2026-05-10 14:30:00',
     *   ],
     *   ...
     * ]
     *
     * @param int $workspaceId
     * @param int $userId
     * @return array
     */
    private function getMyAssignedIssues(int $workspaceId, int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.issue_key,
                    i.title,
                    i.status,
                    i.priority,
                    i.severity,
                    i.updated_at,
                    p.name AS project_name
             FROM issues i
             JOIN projects p ON p.id = i.project_id
             WHERE i.workspace_id = :workspace_id
               AND i.assignee_id  = :user_id
               AND i.status NOT IN ('closed', 'wont_fix', 'duplicate')
               AND i.deleted_at IS NULL
               AND p.deleted_at IS NULL
             ORDER BY
               FIELD(i.priority, 'urgent','high','medium','low'),
               i.updated_at DESC
             LIMIT 5"
            // Sort: issue urgent + mới update lên đầu
        );
        $stmt->execute([
            ':workspace_id' => $workspaceId,
            ':user_id'      => $userId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Lấy 10 hoạt động gần nhất trong Workspace (Activity Log).
     *
     * JOIN users để lấy tên người thực hiện.
     * Index (workspace_id, created_at DESC) trên activity_logs đảm bảo query nhanh.
     *
     * Kết quả:
     * [
     *   [
     *     'actor_name'  => 'Nguyen Van A',
     *     'action_type' => 'issue_status_changed',
     *     'metadata'    => '{"issue_key":"BT-001","from":"open","to":"in_progress"}',
     *     'created_at'  => '2026-05-10 13:00:00',
     *   ],
     *   ...
     * ]
     *
     * NOTE: metadata là JSON string – Dev 3 dùng json_decode() khi render
     *
     * @param int $workspaceId
     * @return array
     */
    private function getRecentActivity(int $workspaceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.name   AS actor_name,
                    u.avatar_path,
                    al.action_type,
                    al.entity_type,
                    al.entity_id,
                    al.metadata,
                    al.created_at
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.workspace_id = :workspace_id
             ORDER BY al.created_at DESC
             LIMIT 10"
        );
        $stmt->execute([':workspace_id' => $workspaceId]);

        return $stmt->fetchAll();
    }

    /**
     * Đếm số notification chưa đọc của user trong workspace.
     *
     * Index (user_id, is_read) đảm bảo query này siêu nhanh dù bảng lớn.
     * Được gọi mỗi page load – phải tối ưu tuyệt đối.
     *
     * @param int $userId
     * @param int $workspaceId
     * @return int
     */
    private function getUnreadNotificationCount(int $userId, int $workspaceId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM notifications
             WHERE user_id      = :user_id
               AND workspace_id = :workspace_id
               AND is_read      = 0"
        );
        $stmt->execute([
            ':user_id'      => $userId,
            ':workspace_id' => $workspaceId,
        ]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }
}