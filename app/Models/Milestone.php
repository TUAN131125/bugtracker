<?php

namespace App\Models;

use App\Core\Database;

class Milestone
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(int $projectId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO milestones
                (project_id, workspace_id, name, description, start_date, due_date, status, created_at)
            VALUES
                (:project_id, :workspace_id, :name, :description, :start_date, :due_date, 'open', NOW())
        ");
        $stmt->execute([
            'project_id'   => $projectId,
            'workspace_id' => $data['workspace_id'],
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'start_date'   => $data['start_date'] ?? null,
            'due_date'     => $data['due_date'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listByProject(int $projectId): array
    {
        $stmt = $this->db->prepare("
            SELECT m.*,
                COUNT(CASE WHEN i.status NOT IN ('closed','wont_fix') THEN 1 END) AS open_issues,
                COUNT(CASE WHEN i.status IN ('closed','resolved') THEN 1 END) AS closed_issues
            FROM milestones m
            LEFT JOIN issues i ON i.milestone_id = m.id AND i.deleted_at IS NULL
            WHERE m.project_id = ?
            GROUP BY m.id
            ORDER BY m.due_date ASC
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE milestones
            SET name = :name, description = :description,
                start_date = :start_date, due_date = :due_date, status = :status
            WHERE id = :id
        ");
        return $stmt->execute([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date'  => $data['start_date'] ?? null,
            'due_date'    => $data['due_date'] ?? null,
            'status'      => $data['status'] ?? 'open',
            'id'          => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM milestones WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Tìm một milestone theo ID và Workspace ID (chống IDOR).
     *
     * @param int $id
     * @param int $workspaceId
     * @return array|false
     */
    public function findById(int $id, int $workspaceId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM milestones 
            WHERE id = :id AND workspace_id = :workspace_id 
            LIMIT 1
        ");
        
        $stmt->execute([
            'id'           => $id,
            'workspace_id' => $workspaceId
        ]);
        
        return $stmt->fetch();
    }
}