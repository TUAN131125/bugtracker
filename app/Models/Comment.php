<?php

namespace App\Models;

use App\Core\Database;

class Comment
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(int $issueId, int $userId, string $content, int $workspaceId, ?int $parentId = null): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO comments
                (issue_id, workspace_id, user_id, parent_comment_id, content, created_at)
            VALUES
                (:issue_id, :workspace_id, :user_id, :parent_id, :content, NOW())
        ");
        $stmt->execute([
            'issue_id'     => $issueId,
            'workspace_id' => $workspaceId,
            'user_id'      => $userId,
            'parent_id'    => $parentId,
            'content'      => $content,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.name AS user_name, u.avatar_path AS user_avatar
            FROM comments c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.id = ? AND c.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function update(int $id, string $content): bool
    {
        $stmt = $this->db->prepare("
            UPDATE comments
            SET content = :content, is_edited = 1, edited_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        return $stmt->execute([
            'content' => $content,
            'id'      => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE comments SET deleted_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function listByIssue(int $issueId, int $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.name AS user_name, u.avatar_path AS user_avatar
            FROM comments c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.issue_id = ?
              AND c.workspace_id = ?
              AND c.deleted_at IS NULL
            ORDER BY c.parent_comment_id ASC, c.created_at ASC
        ");
        $stmt->execute([$issueId, $workspaceId]);
        $rows = $stmt->fetchAll();

        // Group thành nested array: parent + children
        $parents  = [];
        $children = [];

        foreach ($rows as $row) {
            if ($row['parent_comment_id'] === null) {
                $row['replies'] = [];
                $parents[$row['id']] = $row;
            } else {
                $children[] = $row;
            }
        }

        // Gắn replies vào parent
        foreach ($children as $child) {
            $parentId = $child['parent_comment_id'];
            if (isset($parents[$parentId])) {
                $parents[$parentId]['replies'][] = $child;
            }
        }

        return array_values($parents);
    }

    public function isWithin30Minutes(int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS seconds_ago
            FROM comments WHERE id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row && (int)$row['seconds_ago'] <= 1800;
    }
}