<?php

namespace App\Models;

use App\Core\Database;

class WorkspaceInvitation
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(int $wsId, string $email, string $role, string $token, int $invitedBy, bool $isPreReg = false): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO workspace_invitations
                (workspace_id, email, role, token, invited_by, status, is_pre_registered, expires_at, created_at)
            VALUES
                (:ws_id, :email, :role, :token, :invited_by, 'pending', :is_pre_reg, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
        ");
        return $stmt->execute([
            'ws_id'      => $wsId,
            'email'      => $email,
            'role'       => $role,
            'token'      => $token,
            'invited_by' => $invitedBy,
            'is_pre_reg' => (int) $isPreReg,
        ]);
    }

    public function findByToken(string $token): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM workspace_invitations WHERE token = ?
        ");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    public function accept(string $token): bool
    {
        $stmt = $this->db->prepare("
            UPDATE workspace_invitations
            SET status = 'accepted', used_at = NOW()
            WHERE token = ?
        ");
        return $stmt->execute([$token]);
    }

    public function listPending(int $wsId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM workspace_invitations
            WHERE workspace_id = ? AND status = 'pending' AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute([$wsId]);
        return $stmt->fetchAll();
    }

    public function resend(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE workspace_invitations
            SET expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY)
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
}