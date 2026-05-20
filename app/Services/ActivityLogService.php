<?php

namespace App\Services;

use App\Core\Database;

class ActivityLogService
{
    private \PDO $db;

    // Constants cho action types
    const ISSUE_CREATED        = 'issue_created';
    const ISSUE_STATUS_CHANGED = 'issue_status_changed';
    const ISSUE_ASSIGNED       = 'issue_assigned';
    const COMMENT_ADDED        = 'comment_added';
    const COMMENT_DELETED      = 'comment_deleted';
    const MEMBER_INVITED       = 'member_invited';
    const MEMBER_KICKED        = 'member_kicked';
    const PROJECT_CREATED      = 'project_created';
    const PROJECT_ARCHIVED     = 'project_archived';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function log(
        int $workspaceId,
        int $userId,
        string $entityType,
        int $entityId,
        string $action,
        mixed $oldValue = null,
        mixed $newValue = null
    ): void {
        $metadata = json_encode([
            'from' => $oldValue,
            'to'   => $newValue,
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO activity_logs
                (workspace_id, user_id, action_type, entity_type, entity_id, metadata, created_at)
            VALUES
                (:workspace_id, :user_id, :action_type, :entity_type, :entity_id, :metadata, NOW())
        ");

        $stmt->execute([
            'workspace_id' => $workspaceId,
            'user_id'      => $userId,
            'action_type'  => $action,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'metadata'     => $metadata,
        ]);
    }

    public function getForIssue(int $issueId, int $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT al.*, u.name AS user_name, u.avatar_path AS user_avatar
            FROM activity_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.entity_type = 'issue'
              AND al.entity_id = ?
              AND al.workspace_id = ?
            ORDER BY al.created_at ASC
        ");
        $stmt->execute([$issueId, $workspaceId]);
        return $stmt->fetchAll();
    }

    public function getForWorkspace(int $workspaceId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT al.*, u.name AS user_name, u.avatar_path AS user_avatar
            FROM activity_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.workspace_id = ?
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$workspaceId, $limit]);
        return $stmt->fetchAll();
    }
}