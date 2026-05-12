<?php

namespace App\Models;

use App\Core\Database;

class Tag
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(int $workspaceId, string $name, string $color, int $createdBy): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tags (workspace_id, name, color, created_by, created_at)
            VALUES (:workspace_id, :name, :color, :created_by, NOW())
        ");
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'name'         => $name,
            'color'        => $color,
            'created_by'   => $createdBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listByWorkspace(int $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM tags WHERE workspace_id = ? ORDER BY name ASC
        ");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM tags WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function attachToIssue(int $issueId, int $tagId): bool
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO issue_tags (issue_id, tag_id) VALUES (?, ?)
        ");
        return $stmt->execute([$issueId, $tagId]);
    }

    public function detachFromIssue(int $issueId, int $tagId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM issue_tags WHERE issue_id = ? AND tag_id = ?
        ");
        return $stmt->execute([$issueId, $tagId]);
    }

    public function getTagsByIssue(int $issueId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.* FROM tags t
            JOIN issue_tags it ON it.tag_id = t.id
            WHERE it.issue_id = ?
        ");
        $stmt->execute([$issueId]);
        return $stmt->fetchAll();
    }
}
