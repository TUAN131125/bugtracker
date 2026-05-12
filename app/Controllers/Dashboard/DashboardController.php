<?php

namespace App\Controllers\Dashboard;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Models\Notification;

class DashboardController
{
    private \PDO $db;
    private Notification $notificationModel;

    public function __construct()
    {
        $this->db                = Database::getInstance();
        $this->notificationModel = new Notification();
    }

    public function index(): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');

        // Widget: tổng Issue theo status
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) AS count
            FROM issues
            WHERE workspace_id = ? AND deleted_at IS NULL
            GROUP BY status
        ");
        $stmt->execute([$workspaceId]);
        $statusCounts = $stmt->fetchAll();

        // Widget: Issue theo từng project
        $stmt = $this->db->prepare("
            SELECT p.name AS project_name, COUNT(i.id) AS total
            FROM projects p
            LEFT JOIN issues i ON i.project_id = p.id AND i.deleted_at IS NULL
            WHERE p.workspace_id = ? AND p.deleted_at IS NULL
            GROUP BY p.id
            ORDER BY total DESC
        ");
        $stmt->execute([$workspaceId]);
        $projectCounts = $stmt->fetchAll();

        // Issue giao cho user hiện tại
        $stmt = $this->db->prepare("
            SELECT i.*, p.key AS project_key
            FROM issues i
            JOIN projects p ON p.id = i.project_id
            WHERE i.assignee_id = ?
              AND i.workspace_id = ?
              AND i.status NOT IN ('closed', 'wont_fix', 'duplicate')
              AND i.deleted_at IS NULL
            ORDER BY i.updated_at DESC
            LIMIT 5
        ");
        $stmt->execute([$userId, $workspaceId]);
        $myIssues = $stmt->fetchAll();

        // Hoạt động gần đây
        $stmt = $this->db->prepare("
            SELECT al.*, u.name AS user_name, u.avatar_path AS user_avatar
            FROM activity_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.workspace_id = ?
            ORDER BY al.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$workspaceId]);
        $recentActivity = $stmt->fetchAll();

        // Số notification chưa đọc
        $unreadCount = $this->notificationModel->countUnread($userId, $workspaceId);

        // Chuẩn bị data cho Chart.js
        $chartData = [
            'status_counts'  => $statusCounts,
            'project_counts' => $projectCounts,
        ];

        Response::view('dashboard/index', [
            'chart_data'          => $chartData,
            'my_issues'           => $myIssues,
            'recent_activity'     => $recentActivity,
            'unread_notif_count'  => $unreadCount,
        ]);
    }
}