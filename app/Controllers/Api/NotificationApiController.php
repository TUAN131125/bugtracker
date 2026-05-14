<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use PDO;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * NotificationApiController
 *
 * JSON API endpoint cho hệ thống thông báo in-app.
 * Được gọi bởi dashboard.js qua polling mỗi 60 giây.
 *
 * Vì InfinityFree không hỗ trợ WebSocket, hệ thống dùng mô hình
 * Pull (Polling) — JS gọi endpoint này định kỳ để lấy badge count.
 *
 * Routes (routes.php):
 *   GET  /api/notifications              → index()       — danh sách + unread count
 *   POST /api/notifications/{id}/read    → markRead()    — đánh dấu 1 đã đọc
 *   POST /api/notifications/read-all    → markAllRead() — đánh dấu tất cả đã đọc
 *
 * Contract với Dev 3 (dashboard.js – Task Assignment Phần 5.1):
 *   index() response:
 *   {
 *     "success": true,
 *     "data": [
 *       {
 *         "id": 1,
 *         "type": "issue_assigned",
 *         "message": "Nguyen Van A đã giao BT-001 cho bạn",
 *         "url": "/issues/BT-001",
 *         "is_read": 0,
 *         "created_at": "2026-05-10 14:30:00"
 *       }
 *     ],
 *     "unread_count": 3
 *   }
 *
 * @author  Dev 2
 * @version 1.0.0
 * @see     SRS v1.0.0 – Phần 3.4.6 (Notification System)
 * @see     TDD Backend v1.0.0 – Phần 2.2.7 (Bảng notifications)
 * @see     Task Assignment v1.0.0 – D2-023, Phần 5.1 (Interface Contract)
 */
class NotificationApiController
{
    private PDO $db;

    /**
     * Số notification tối đa trả về mỗi lần gọi index().
     * Giữ nhỏ để tránh response lớn — polling mỗi 60s trên InfinityFree.
     */
    private const MAX_NOTIFICATIONS = 20;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================================
    // index() – GET /api/notifications
    // =========================================================================

    /**
     * Trả về danh sách notification gần nhất và số chưa đọc.
     *
     * Dev 3 dùng unread_count để update badge trên bell icon.
     * Dev 3 dùng data[] để render notification dropdown panel.
     *
     * @param  Request $request  Inject từ Router.
     * @return void
     */
    public function index(Request $request): void
    {
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Không hợp lệ.'], 400);
            return;
        }

        $user_id      = Session::getUserId();
        $workspace_id = Session::getActiveWorkspaceId();

        if (!$user_id || !$workspace_id) {
            Response::json(['success' => false, 'message' => 'Phiên làm việc không hợp lệ.'], 401);
            return;
        }

        try {
            // --- Lấy danh sách notifications ---
            $stmt = $this->db->prepare(
                'SELECT id, type, entity_type, entity_id,
                        message, url, is_read, created_at
                 FROM notifications
                 WHERE user_id      = :user_id
                   AND workspace_id = :workspace_id
                 ORDER BY created_at DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':user_id',      $user_id,                    PDO::PARAM_INT);
            $stmt->bindValue(':workspace_id', $workspace_id,               PDO::PARAM_INT);
            $stmt->bindValue(':limit',        self::MAX_NOTIFICATIONS,     PDO::PARAM_INT);
            $stmt->execute();
            $notifications = $stmt->fetchAll();

            // Cast kiểu dữ liệu để JSON response nhất quán
            $notifications = array_map(function (array $row): array {
                return [
                    'id'          => (int) $row['id'],
                    'type'        => $row['type'],
                    'entity_type' => $row['entity_type'],
                    'entity_id'   => (int) $row['entity_id'],
                    'message'     => $row['message'],
                    'url'         => $row['url'],
                    'is_read'     => (bool) $row['is_read'],
                    'created_at'  => $row['created_at'],
                ];
            }, $notifications);

            // --- Đếm số chưa đọc ---
            // Query riêng để đảm bảo count chính xác dù list bị giới hạn LIMIT
            // Index (user_id, is_read) đảm bảo query này siêu nhanh (TDD Phần 2.3)
            $count_stmt = $this->db->prepare(
                'SELECT COUNT(*) AS total
                 FROM notifications
                 WHERE user_id      = :user_id
                   AND workspace_id = :workspace_id
                   AND is_read      = 0'
            );
            $count_stmt->execute([
                ':user_id'      => $user_id,
                ':workspace_id' => $workspace_id,
            ]);
            $unread_count = (int) ($count_stmt->fetch()['total'] ?? 0);

            // Lazy cleanup: xóa notification đã đọc cũ hơn 30 ngày
            // Chạy với xác suất thấp (1/20) để không ảnh hưởng performance
            // Thay thế Cronjob trên InfinityFree (TDD Phần 2.4)
            if (random_int(1, 20) === 1) {
                $this->cleanupOldNotifications($user_id, $workspace_id);
            }

            Response::json([
                'success'      => true,
                'data'         => $notifications,
                'unread_count' => $unread_count,
            ]);

        } catch (\PDOException $e) {
            // TODO: Replace bằng Logger instance sau khi D1-021 hoàn thành (Ngày 3)
            error_log(sprintf(
                '[NotificationApiController::index] DB error | User: %d | Workspace: %d | Error: %s',
                $user_id,
                $workspace_id,
                $e->getMessage()
            ));

            Response::json([
                'success' => false,
                'message' => 'Không thể tải thông báo. Vui lòng thử lại.',
            ], 500);
        }
    }

    // =========================================================================
    // markRead() – POST /api/notifications/{id}/read
    // =========================================================================

    /**
     * Đánh dấu một notification cụ thể là đã đọc.
     *
     * @param  Request $request
     * @param  int     $id       Notification ID từ URL parameter.
     * @return void
     */
    public function markRead(Request $request, int $id): void
    {
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Không hợp lệ.'], 400);
            return;
        }

        $user_id      = Session::getUserId();
        $workspace_id = Session::getActiveWorkspaceId();

        if (!$user_id || !$workspace_id) {
            Response::json(['success' => false, 'message' => 'Phiên làm việc không hợp lệ.'], 401);
            return;
        }

        try {
            // UPDATE với WHERE user_id + workspace_id — chống IDOR
            // User không thể đánh dấu đọc notification của người khác
            $stmt = $this->db->prepare(
                'UPDATE notifications
                 SET is_read = 1
                 WHERE id           = :id
                   AND user_id      = :user_id
                   AND workspace_id = :workspace_id
                   AND is_read      = 0'
            );
            $stmt->execute([
                ':id'           => $id,
                ':user_id'      => $user_id,
                ':workspace_id' => $workspace_id,
            ]);

            if ($stmt->rowCount() === 0) {
                // Notification không tồn tại hoặc đã đọc — không phải lỗi
                Response::json([
                    'success' => true,
                    'message' => 'Thông báo đã được đánh dấu đọc.',
                ]);
                return;
            }

            // Trả về unread_count mới để Dev 3 update badge ngay
            $unread_count = $this->countUnread($user_id, $workspace_id);

            Response::json([
                'success'      => true,
                'message'      => 'Đã đánh dấu đọc.',
                'unread_count' => $unread_count,
            ]);

        } catch (\PDOException $e) {
            error_log(sprintf(
                '[NotificationApiController::markRead] DB error | User: %d | Notification: %d | Error: %s',
                $user_id,
                $id,
                $e->getMessage()
            ));

            Response::json([
                'success' => false,
                'message' => 'Không thể cập nhật. Vui lòng thử lại.',
            ], 500);
        }
    }

    // =========================================================================
    // markAllRead() – POST /api/notifications/read-all
    // =========================================================================

    /**
     * Đánh dấu tất cả notification của user trong workspace là đã đọc.
     *
     * @param  Request $request
     * @return void
     */
    public function markAllRead(Request $request): void
    {
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Không hợp lệ.'], 400);
            return;
        }

        $user_id      = Session::getUserId();
        $workspace_id = Session::getActiveWorkspaceId();

        if (!$user_id || !$workspace_id) {
            Response::json(['success' => false, 'message' => 'Phiên làm việc không hợp lệ.'], 401);
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'UPDATE notifications
                 SET is_read = 1
                 WHERE user_id      = :user_id
                   AND workspace_id = :workspace_id
                   AND is_read      = 0'
            );
            $stmt->execute([
                ':user_id'      => $user_id,
                ':workspace_id' => $workspace_id,
            ]);

            $updated_count = $stmt->rowCount();

            Response::json([
                'success'       => true,
                'message'       => "Đã đánh dấu đọc {$updated_count} thông báo.",
                'unread_count'  => 0,
            ]);

        } catch (\PDOException $e) {
            error_log(sprintf(
                '[NotificationApiController::markAllRead] DB error | User: %d | Workspace: %d | Error: %s',
                $user_id,
                $workspace_id,
                $e->getMessage()
            ));

            Response::json([
                'success' => false,
                'message' => 'Không thể cập nhật. Vui lòng thử lại.',
            ], 500);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Đếm số notification chưa đọc của user trong workspace.
     * Tách thành method riêng để tái sử dụng trong markRead().
     *
     * @param  int $user_id
     * @param  int $workspace_id
     * @return int
     */
    private function countUnread(int $user_id, int $workspace_id): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
             FROM notifications
             WHERE user_id      = :user_id
               AND workspace_id = :workspace_id
               AND is_read      = 0'
        );
        $stmt->execute([
            ':user_id'      => $user_id,
            ':workspace_id' => $workspace_id,
        ]);

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    /**
     * Lazy cleanup: xóa notification đã đọc cũ hơn 30 ngày.
     *
     * Thay thế Cronjob trên InfinityFree theo TDD Phần 2.4.
     * Giới hạn DELETE 200 bản ghi/lần để tránh timeout.
     * Chỉ xóa của user hiện tại — tránh lock table toàn bộ.
     *
     * @param  int $user_id
     * @param  int $workspace_id
     * @return void
     */
    private function cleanupOldNotifications(int $user_id, int $workspace_id): void
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM notifications
                 WHERE user_id      = :user_id
                   AND workspace_id = :workspace_id
                   AND is_read      = 1
                   AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                 LIMIT 200'
            );
            $stmt->execute([
                ':user_id'      => $user_id,
                ':workspace_id' => $workspace_id,
            ]);
        } catch (\PDOException $e) {
            // Silent fail — cleanup không quan trọng bằng request chính
            error_log(sprintf(
                '[NotificationApiController::cleanupOldNotifications] Failed | User: %d | Error: %s',
                $user_id,
                $e->getMessage()
            ));
        }
    }
}