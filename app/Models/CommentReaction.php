<?php

namespace App\Models;

use App\Core\Database;

class CommentReaction
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function toggle(int $commentId, int $userId, string $emoji): bool
    {
        // Kiểm tra đã react chưa
        $stmt = $this->db->prepare("
            SELECT id FROM comment_reactions
            WHERE comment_id = ? AND user_id = ? AND emoji = ?
        ");
        $stmt->execute([$commentId, $userId, $emoji]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Đã react → bỏ reaction
            $del = $this->db->prepare("
                DELETE FROM comment_reactions WHERE id = ?
            ");
            return $del->execute([$existing['id']]);
        } else {
            // Chưa react → thêm mới
            $ins = $this->db->prepare("
                INSERT INTO comment_reactions (comment_id, user_id, emoji, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            return $ins->execute([$commentId, $userId, $emoji]);
        }
    }

    public function countByComment(int $commentId): array
    {
        $stmt = $this->db->prepare("
            SELECT emoji, COUNT(*) AS count
            FROM comment_reactions
            WHERE comment_id = ?
            GROUP BY emoji
        ");
        $stmt->execute([$commentId]);
        return $stmt->fetchAll();
    }
}