<?php

namespace App\Controllers\Api;

use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Core\Request;
use App\Models\Notification;

class NotificationApiController
{
    private Notification $notifModel;

    public function __construct()
    {
        $this->notifModel = new Notification();
    }

    // GET /api/notifications
    public function index(): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');

        if (!$userId || !$workspaceId) {
            Response::json(['success' => false, 'message' => 'Unauthorized.'], 401);
            exit();
        }

        $notifications = $this->notifModel->listUnread($userId, $workspaceId);
        $unreadCount   = $this->notifModel->countUnread($userId, $workspaceId);

        Response::json([
            'success'      => true,
            'data'         => $notifications,
            'unread_count' => (int) $unreadCount,
        ]);
    }

    // POST /api/notifications/{id}/read
    public function markRead(int $notifId): void
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            Response::json(['success' => false, 'message' => 'Unauthorized.'], 401);
            exit();
        }

        $notif = $this->notifModel->findById($notifId);

        if (!$notif || (int)$notif['user_id'] !== (int)$userId) {
            Response::json(['success' => false, 'message' => 'Notification không tồn tại.'], 404);
            exit();
        }

        $this->notifModel->markRead($notifId);

        $workspaceId = Session::get('active_workspace_id');
        $unreadCount = $this->notifModel->countUnread($userId, $workspaceId);

        Response::json([
            'success'      => true,
            'message'      => 'Đã đánh dấu đã đọc.',
            'unread_count' => (int) $unreadCount,
        ]);
    }

    // POST /api/notifications/read-all
    public function markAllRead(): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');

        if (!$userId || !$workspaceId) {
            Response::json(['success' => false, 'message' => 'Unauthorized.'], 401);
            exit();
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE notifications
             SET is_read = 1, read_at = NOW()
             WHERE user_id = ? AND workspace_id = ? AND is_read = 0"
        );
        $stmt->execute([$userId, $workspaceId]);

        Response::json([
            'success'      => true,
            'message'      => 'Đã đánh dấu tất cả là đã đọc.',
            'unread_count' => 0,
        ]);
    }
}