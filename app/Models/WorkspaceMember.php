<?php

namespace App\Models;

use App\Core\Database;

class WorkspaceMember
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function add(int $workspaceId, int $userId, string $role): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO workspace_members (workspace_id, user_id, role, joined_at)
            VALUES (:workspace_id, :user_id, :role, NOW())
        ");
        return $stmt->execute([
            'workspace_id' => $workspaceId,
            'user_id'      => $userId,
            'role'         => $role,
        ]);
    }

    public function remove(int $workspaceId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM workspace_members WHERE workspace_id = ? AND user_id = ?
        ");
        return $stmt->execute([$workspaceId, $userId]);
    }

    public function updateRole(int $workspaceId, int $userId, string $role): bool
    {
        $stmt = $this->db->prepare("
            UPDATE workspace_members SET role = ?
            WHERE workspace_id = ? AND user_id = ?
        ");
        return $stmt->execute([$role, $workspaceId, $userId]);
    }

    public function isMember(int $workspaceId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM workspace_members
            WHERE workspace_id = ? AND user_id = ?
        ");
        $stmt->execute([$workspaceId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    public function getRole(int $workspaceId, int $userId): string|null
    {
        $stmt = $this->db->prepare("
            SELECT role FROM workspace_members
            WHERE workspace_id = ? AND user_id = ?
        ");
        $stmt->execute([$workspaceId, $userId]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }

    public function listMembers(int $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.email, u.avatar, wm.role, wm.joined_at
            FROM workspace_members wm
            JOIN users u ON wm.user_id = u.id
            WHERE wm.workspace_id = ?
            ORDER BY wm.joined_at ASC
        ");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll();
    }
}