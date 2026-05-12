<?php

namespace App\Models;

use App\Core\Database;

class Project
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(int $workspaceId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO projects
                (workspace_id, name, description, key, color, status, last_issue_number, created_by, created_at)
            VALUES
                (:workspace_id, :name, :description, :key, :color, 'active', 0, :created_by, NOW())
        ");
        $stmt->execute([
            'workspace_id' => $workspaceId,
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'key'          => strtoupper($data['key']),
            'color'        => $data['color'] ?? '#2E86AB',
            'created_by'   => $data['created_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id, int $workspaceId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM projects
            WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$id, $workspaceId]);
        return $stmt->fetch();
    }

    public function findByKey(string $key, int $workspaceId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM projects
            WHERE key = ? AND workspace_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$key, $workspaceId]);
        return $stmt->fetch();
    }

    public function listByWorkspace(int $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*,
                COUNT(CASE WHEN i.status = 'open' THEN 1 END) AS open_issues,
                COUNT(CASE WHEN i.status = 'closed' THEN 1 END) AS closed_issues,
                COUNT(i.id) AS total_issues
            FROM projects p
            LEFT JOIN issues i ON i.project_id = p.id AND i.deleted_at IS NULL
            WHERE p.workspace_id = ? AND p.deleted_at IS NULL
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE projects
            SET name = :name, description = :description, color = :color, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? '#2E86AB',
            'id'          => $id,
        ]);
    }

    public function archive(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE projects SET status = 'archived', updated_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE projects SET deleted_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function getNextIssueSequence(int $projectId): int
    {
        // Dùng transaction để tránh race condition
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                SELECT last_issue_number FROM projects WHERE id = ? FOR UPDATE
            ");
            $stmt->execute([$projectId]);
            $row = $stmt->fetch();

            $nextNumber = ($row['last_issue_number'] ?? 0) + 1;

            $update = $this->db->prepare("
                UPDATE projects SET last_issue_number = ? WHERE id = ?
            ");
            $update->execute([$nextNumber, $projectId]);

            $this->db->commit();
            return $nextNumber;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}