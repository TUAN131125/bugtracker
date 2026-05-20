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
            SELECT u.id as user_id, u.name, u.email, u.avatar, wm.id as membership_id, wm.role, wm.joined_at
            FROM workspace_members wm
            JOIN users u ON wm.user_id = u.id
            WHERE wm.workspace_id = ?
            ORDER BY wm.joined_at ASC
        ");
        $stmt->execute([$workspaceId]);
        return $stmt->fetchAll();
    }

    /**
     * Tìm thông tin thành viên (membership) dựa vào ID bản ghi và Workspace ID.
     *
     * @param int $id          ID của bản ghi trong bảng workspace_members
     * @param int $workspaceId ID của workspace (chống IDOR)
     * @return array|false     Mảng chứa thông tin thành viên hoặc false nếu không tìm thấy
     */
    public function findMembershipById(int $id, int $workspaceId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM workspace_members 
            WHERE id = :id AND workspace_id = :workspace_id 
            LIMIT 1
        ");
        
        $stmt->execute([
            'id'           => $id,
            'workspace_id' => $workspaceId
        ]);
        
        return $stmt->fetch();
    }

    // =========================================================================
    // [BỔ SUNG] Fix lỗi Intelephense P1013 "Undefined method"
    // Ln 112, 118, 451 trong InvitationController.php
    // =========================================================================

    /**
     * Lấy role của user trong Workspace theo signature (userId, workspaceId).
     *
     * WHY cần method riêng thay vì dùng getRole() trực tiếp:
     *   getRole() có signature (workspaceId, userId) – tham số ngược lại.
     *   InvitationController gọi getRoleInWorkspace($userId, $workspaceId)
     *   nên thứ tự này khác. Nếu chỉ alias thẳng sang getRole() mà không
     *   có method này, Intelephense vẫn báo lỗi P1013.
     *   Tạo method tường minh giúp IDE nhận diện đúng và tránh bug âm thầm
     *   do truyền nhầm thứ tự tham số.
     *
     * @param int $userId      ID của user
     * @param int $workspaceId ID của Workspace
     * @return string|null     'owner' | 'admin' | 'member' | 'guest' | null
     */
    public function getRoleInWorkspace(int $userId, int $workspaceId): string|null
    {
        // Delegate sang getRole() – đảo thứ tự tham số cho đúng
        return $this->getRole($workspaceId, $userId);
    }

    /**
     * Kiểm tra user có quyền mời thành viên vào Workspace không.
     *
     * WHY đặt ở Model thay vì chỉ dùng RbacService::canManageMembers():
     *   InvitationController đang gọi $this->memberModel->canInviteMembers()
     *   trực tiếp (Ln 112, 451). Thay vì refactor Controller, ta implement
     *   method còn thiếu ngay tại đây – ít thay đổi nhất, an toàn nhất.
     *
     * WHY KHÔNG gọi new RbacService() bên trong:
     *   RbacService phụ thuộc WorkspaceMember → gọi ngược lại sẽ tạo
     *   circular dependency. Thay vào đó, inline điều kiện role trực tiếp.
     *   Logic đơn giản (2 role được phép) nên không cần abstraction thêm.
     *
     * Theo TDD mục 1.5: chỉ owner và admin mới được tạo/thu hồi invitation.
     *
     * @param int $userId      ID của user đang thực hiện hành động
     * @param int $workspaceId ID của Workspace mục tiêu
     * @return bool
     */
    public function canInviteMembers(int $userId, int $workspaceId): bool
    {
        $role = $this->getRole($workspaceId, $userId);

        if ($role === null) {
            return false; // Không phải thành viên → không có quyền gì
        }

        return in_array($role, ['owner', 'admin'], true);
    }
}