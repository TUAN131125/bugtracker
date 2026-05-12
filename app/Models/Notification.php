<?php

namespace App\Models;

use App\Core\Database;

class Notification
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications
                (user_id, workspace_id, type, entity_type, entity_id, message, url, is_read, created_at)
            VALUES
                (:user_id, :workspace_id, :type, :entity_type, :entity_id, :message, :url, 0, NOW())
        ");
        $stmt->execute([
            'user_id'      => $data['user_id'],
            'workspace_id' => $data['workspace_id'],
            'type'         => $data['type'],
            'entity_type'  => $data['entity_type'],
            'entity_id'    => $data['entity_id'],
            'message'      => $data['message'],
            'url'          => $data['url'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function markRead(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notifications SET is_read = 1 WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function markAllRead(int $userId, int $workspaceId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ? AND workspace_id = ? AND is_read = 0
        ");
        return $stmt->execute([$userId, $workspaceId]);
    }

    public function listUnread(int $userId, int $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notifications
            WHERE user_id = ? AND workspace_id = ? AND is_read = 0
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$userId, $workspaceId]);
        return $stmt->fetchAll();
    }

    public function countUnread(int $userId, int $workspaceId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = ? AND workspace_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId, $workspaceId]);
        return (int) $stmt->fetchColumn();
    }
    
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM notifications WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}