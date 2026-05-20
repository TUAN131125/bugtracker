<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use PDO;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

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
 * @see     Task Assignment v1.0.0 – D1-028, Phần 5.1 (Interface Contract)
 */
class DashboardController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Entry point duy nhất của Dashboard.
     * Gom tất cả aggregate queries vào đây, truyền data cho View.
     *
     * Route: GET /dashboard
     * Middleware chain: AuthMiddleware → OnboardingMiddleware → WorkspaceMiddleware
     *
     * @param  Request $request  Inject từ Router.
     * @return void
     */
    public function index(Request $request): void
    {
        // ----------------------------------------------------------------
        // 1. Lấy context từ Session class (đã validate bởi WorkspaceMiddleware)
        //    KHÔNG truy cập $_SESSION trực tiếp – dùng Session abstraction layer.
        // ----------------------------------------------------------------
        $workspace_id = Session::getActiveWorkspaceId();
        $user_id      = Session::getUserId();

        // Guard: Middleware phải đảm bảo 2 giá trị này hợp lệ.
        // Nếu vẫn null tại đây → có lỗi middleware, log và redirect.
        if (!$workspace_id || !$user_id) {
            // TODO: Replace bằng Logger::error() sau khi D1-021 hoàn thành (Ngày 3)
            error_log(
                '[DashboardController] Invalid session — workspace_id or user_id is null. '
                . 'Session data: ' . json_encode([
                    'workspace_id' => $workspace_id,
                    'user_id'      => $user_id,
                ])
            );
            Response::redirect('/onboarding');
            return;
        }

        try {
            // ----------------------------------------------------------------
            // 2. Query tổng hợp Issue theo status (cho Donut Chart)
            // ----------------------------------------------------------------
            $status_counts = $this->getIssueStatusCounts($workspace_id);

            // ----------------------------------------------------------------
            // 3. Query số Issue theo từng Project (cho Bar Chart)
            // ----------------------------------------------------------------
            $project_counts = $this->getIssueCountByProject($workspace_id);

            // ----------------------------------------------------------------
            // 4. Query Issue được giao cho user hiện tại
            // ----------------------------------------------------------------
            $my_issues = $this->getMyAssignedIssues($workspace_id, $user_id);

            // ----------------------------------------------------------------
            // 5. Query Activity Log gần nhất
            // ----------------------------------------------------------------
            $recent_activity = $this->getRecentActivity($workspace_id);

            // ----------------------------------------------------------------
            // 6. Đếm notification chưa đọc
            // ----------------------------------------------------------------
            $unread_notif_count = $this->getUnreadNotificationCount($user_id, $workspace_id);

            // ----------------------------------------------------------------
            // 7. Gom chart_data theo contract Dev 3 (Task Assignment Phần 5.1)
            // ----------------------------------------------------------------
            $chart_data = [
                'status_counts'  => $status_counts,
                'project_counts' => $project_counts,
            ];

            // ----------------------------------------------------------------
            // 8. Render view – tên biến khớp chính xác với contract Phần 5.1
            // ----------------------------------------------------------------
            Response::view('dashboard/index', [
                'pageId'             => 'dashboard',
                'pageTitle'          => 'Dashboard',
                'chart_data'         => $chart_data,
                'my_issues'          => $my_issues,
                'recent_activity'    => $recent_activity,
                'unread_notif_count' => $unread_notif_count,
            ]);

        } catch (\PDOException $e) {
            // TODO: Replace bằng Logger::error() sau khi D1-021 hoàn thành (Ngày 3)
            error_log(sprintf(
                '[DashboardController] Query failed | Workspace: %d | User: %d | Error: %s',
                $workspace_id,
                $user_id,
                $e->getMessage()
            ));

            Response::view('errors/500', [
                'pageId'    => 'error',
                'pageTitle' => 'Lỗi hệ thống',
            ], 500);
        }
    }

    // ================================================================
    // PRIVATE METHODS
    // ================================================================

    /**
     * Lấy số lượng Issue theo từng trạng thái trong Workspace.
     *
     * Kết quả:
     * [
     *   ['status' => 'open', 'count' => 12, 'label' => 'Mới'],
     *   ...
     * ]
     *
     * @param  int   $workspace_id
     * @return array<int, array<string, mixed>>
     */
    private function getIssueStatusCounts(int $workspace_id): array
    {
        // Map status DB value → label tiếng Việt
        // Đồng bộ với ViewLayer Guide Appendix C (STATUS_LABELS_VI)
        $label_map = [
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
             ORDER BY FIELD(
                 status,
                 'open','in_triage','in_progress','resolved',
                 'closed','reopened','wont_fix','duplicate'
             )"
        );
        $stmt->execute([':workspace_id' => $workspace_id]);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row) use ($label_map): array {
            return [
                'status' => $row['status'],
                'count'  => (int) $row['count'],
                'label'  => $label_map[$row['status']] ?? $row['status'],
            ];
        }, $rows);
    }

    /**
     * Lấy số lượng Issue theo từng Project (cho Bar Chart).
     *
     * Kết quả:
     * [
     *   ['project_name' => 'BugTracker', 'project_key' => 'BT', 'count' => 42],
     *   ...
     * ]
     *
     * @param  int   $workspace_id
     * @return array<int, array<string, mixed>>
     */
    private function getIssueCountByProject(int $workspace_id): array
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
        );

        $stmt->execute([
            // PDO không cho phép dùng cùng named param 2 lần trong 1 query,
            // phải dùng 2 tên khác nhau dù cùng giá trị.
            ':workspace_id_issues'   => $workspace_id,
            ':workspace_id_projects' => $workspace_id,
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
     * Kết quả:
     * [
     *   [
     *     'issue_key'    => 'BT-007',
     *     'title'        => 'Login button broken',
     *     'status'       => 'in_progress',
     *     'priority'     => 'urgent',
     *     'severity'     => 'critical',
     *     'project_name' => 'BugTracker',
     *     'updated_at'   => '2026-05-10 14:30:00',
     *   ],
     * ]
     *
     * @param  int   $workspace_id
     * @param  int   $user_id
     * @return array<int, array<string, mixed>>
     */
    private function getMyAssignedIssues(int $workspace_id, int $user_id): array
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
        );
        $stmt->execute([
            ':workspace_id' => $workspace_id,
            ':user_id'      => $user_id,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Lấy 10 hoạt động gần nhất trong Workspace.
     *
     * Kết quả:
     * [
     *   [
     *     'actor_name'  => 'Nguyen Van A',
     *     'avatar_path' => '/storage/...',
     *     'action_type' => 'issue_status_changed',
     *     'entity_type' => 'issue',
     *     'entity_id'   => 12,
     *     'metadata'    => '{"issue_key":"BT-001","from":"open","to":"in_progress"}',
     *     'created_at'  => '2026-05-10 13:00:00',
     *   ],
     * ]
     *
     * NOTE: metadata là JSON string – Dev 3 dùng JSON.parse() khi render.
     *
     * @param  int   $workspace_id
     * @return array<int, array<string, mixed>>
     */
    private function getRecentActivity(int $workspace_id): array
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
        $stmt->execute([':workspace_id' => $workspace_id]);

        return $stmt->fetchAll();
    }

    /**
     * Đếm số notification chưa đọc của user trong workspace.
     * Index (user_id, is_read) đảm bảo query siêu nhanh.
     *
     * @param  int $user_id
     * @param  int $workspace_id
     * @return int
     */
    private function getUnreadNotificationCount(int $user_id, int $workspace_id): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM notifications
             WHERE user_id      = :user_id
               AND workspace_id = :workspace_id
               AND is_read      = 0"
        );
        $stmt->execute([
            ':user_id'      => $user_id,
            ':workspace_id' => $workspace_id,
        ]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }
}