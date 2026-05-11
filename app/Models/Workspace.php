<?php

namespace App\Models;

use App\Core\Database;

class Workspace
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO workspaces (name, slug, description, owner_id, created_at)
            VALUES (:name, :slug, :description, :owner_id, NOW())
        ");
        $stmt->execute([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'owner_id'    => $data['owner_id'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM workspaces WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findBySlug(string $slug): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM workspaces WHERE slug = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE workspaces
            SET name = :name, description = :description, updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'id'          => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE workspaces SET deleted_at = NOW() WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*, wm.role
            FROM workspaces w
            JOIN workspace_members wm ON w.id = wm.workspace_id
            WHERE wm.user_id = ? AND w.deleted_at IS NULL
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}