<?php

namespace App\Models;

use App\Core\Database;

class Issue
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(int $projectId, int $workspaceId, array $data): int
    {
        // Lấy số thứ tự tiếp theo (đã có transaction trong Project model)
        $projectModel = new Project();
        $sequence     = $projectModel->getNextIssueSequence($projectId);

        // Lấy key của project để tạo issue_key (VD: BT-001)
        $stmt = $this->db->prepare("SELECT key FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        $project  = $stmt->fetch();
        $issueKey = $project['key'] . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);

        $stmt = $this->db->prepare("
            INSERT INTO issues (
                workspace_id, project_id, issue_number, issue_key,
                title, description, type, status, severity, priority,
                reporter_id, assignee_id, milestone_id,
                created_at, updated_at
            ) VALUES (
                :workspace_id, :project_id, :issue_number, :issue_key,
                :title, :description, :type, 'open', :severity, :priority,
                :reporter_id, :assignee_id, :milestone_id,
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            'workspace_id' => $workspaceId,
            'project_id'   => $projectId,
            'issue_number' => $sequence,
            'issue_key'    => $issueKey,
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'type'         => $data['type'] ?? 'bug',
            'severity'     => $data['severity'] ?? 'major',
            'priority'     => $data['priority'] ?? 'medium',
            'reporter_id'  => $data['reporter_id'],
            'assignee_id'  => $data['assignee_id'] ?? null,
            'milestone_id' => $data['milestone_id'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(string $issueKey, int $workspaceId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT i.*,
                u1.name AS reporter_name, u1.avatar_path AS reporter_avatar,
                u2.name AS assignee_name, u2.avatar_path AS assignee_avatar,
                p.name AS project_name, p.key AS project_key,
                m.name AS milestone_name
            FROM issues i
            LEFT JOIN users u1 ON u1.id = i.reporter_id
            LEFT JOIN users u2 ON u2.id = i.assignee_id
            LEFT JOIN projects p ON p.id = i.project_id
            LEFT JOIN milestones m ON m.id = i.milestone_id
            WHERE i.issue_key = ? AND i.workspace_id = ? AND i.deleted_at IS NULL
        ");
        $stmt->execute([$issueKey, $workspaceId]);
        return $stmt->fetch();
    }

    public function listByProject(int $projectId, int $workspaceId, array $filters = []): array
    {
        $sql    = "
            SELECT i.*,
                u.name AS assignee_name, u.avatar_path AS assignee_avatar
            FROM issues i
            LEFT JOIN users u ON u.id = i.assignee_id
            WHERE i.project_id = :project_id
              AND i.workspace_id = :workspace_id
              AND i.deleted_at IS NULL
        ";
        $params = [
            'project_id'   => $projectId,
            'workspace_id' => $workspaceId,
        ];

        // Filter động
        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND i.priority = :priority";
            $params['priority'] = $filters['priority'];
        }
        if (!empty($filters['assignee_id'])) {
            $sql .= " AND i.assignee_id = :assignee_id";
            $params['assignee_id'] = $filters['assignee_id'];
        }
        if (!empty($filters['milestone_id'])) {
            $sql .= " AND i.milestone_id = :milestone_id";
            $params['milestone_id'] = $filters['milestone_id'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND i.title LIKE :keyword";
            $params['keyword'] = '%' . $filters['keyword'] . '%';
        }

        $sql .= " ORDER BY i.created_at DESC";

        // Pagination
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int) $filters['limit'];
        }
        if (!empty($filters['offset'])) {
            $sql .= " OFFSET " . (int) $filters['offset'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE issues
            SET title = :title, description = :description,
                type = :type, severity = :severity, priority = :priority,
                assignee_id = :assignee_id, milestone_id = :milestone_id,
                updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'type'         => $data['type'] ?? 'bug',
            'severity'     => $data['severity'] ?? 'major',
            'priority'     => $data['priority'] ?? 'medium',
            'assignee_id'  => $data['assignee_id'] ?? null,
            'milestone_id' => $data['milestone_id'] ?? null,
            'id'           => $id,
        ]);
    }

    public function updateStatus(int $id, string $status, int $changedBy): bool
    {
        $resolvedAt = in_array($status, ['resolved', 'closed']) ? 'NOW()' : 'NULL';

        $stmt = $this->db->prepare("
            UPDATE issues
            SET status = :status,
                status_changed_by = :changed_by,
                resolved_at = {$resolvedAt},
                updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'status'     => $status,
            'changed_by' => $changedBy,
            'id'         => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE issues SET deleted_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
}