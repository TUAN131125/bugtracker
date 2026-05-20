<?php

namespace App\Services;

use App\Models\WorkspaceMember;

class RbacService
{
    private WorkspaceMember $memberModel;

    public function __construct()
    {
        $this->memberModel = new WorkspaceMember();
    }

    public function hasPermission(int $userId, int $workspaceId, string $action): bool
    {
        $role = $this->memberModel->getRole($workspaceId, $userId);
        if (!$role) return false;

        $permissions = [
            'owner'  => [
                'manage_project', 'create_issue', 'assign_issue', 'close_issue',
                'manage_members', 'delete_workspace', 'manage_tags', 'manage_milestones',
                'upload_attachment', 'delete_attachment',
                'invite_members',    // [BỔ SUNG] Tường minh hoá quyền mời thành viên
                'manage_workspace',  // [BỔ SUNG] Sửa/đổi tên/avatar/cài đặt Workspace
            ],
            'admin'  => [
                'manage_project', 'create_issue', 'assign_issue', 'close_issue',
                'manage_members', 'manage_tags', 'manage_milestones',
                'upload_attachment', 'delete_attachment',
                'invite_members',    // [BỔ SUNG] Admin cũng được mời thành viên
                'manage_workspace',  // [BỔ SUNG] Admin được sửa cài đặt Workspace (trừ xóa)
            ],
            'member' => [
                'create_issue', 'assign_issue', 'close_issue',
                'upload_attachment', 'delete_attachment',
            ],
            'guest'  => [
                // SRS Phần 1.3: Guest được đính kèm file vào Issue (✅)
                // nhưng chỉ xóa được attachment của chính mình
                // → upload_attachment có, delete_attachment KHÔNG có ở đây
                // → canDeleteAttachment() sẽ xử lý logic "của mình" riêng
                'upload_attachment',
            ],
        ];

        return in_array($action, $permissions[$role] ?? []);
    }

    public function canManageProject(int $userId, int $workspaceId): bool
    {
        return $this->hasPermission($userId, $workspaceId, 'manage_project');
    }

    public function canCreateIssue(int $userId, int $workspaceId): bool
    {
        return $this->hasPermission($userId, $workspaceId, 'create_issue');
    }

    public function canAssignIssue(int $userId, int $workspaceId): bool
    {
        return $this->hasPermission($userId, $workspaceId, 'assign_issue');
    }

    public function canCloseIssue(int $userId, int $workspaceId): bool
    {
        return $this->hasPermission($userId, $workspaceId, 'close_issue');
    }

    public function canManageMembers(int $userId, int $workspaceId): bool
    {
        return $this->hasPermission($userId, $workspaceId, 'manage_members');
    }

    public function canDeleteWorkspace(int $userId, int $workspaceId): bool
    {
        return $this->hasPermission($userId, $workspaceId, 'delete_workspace');
    }

    /**
     * Kiểm tra quyền upload file đính kèm vào Issue/Comment.
     *
     * Theo SRS Phần 1.3 (RBAC Matrix):
     *   Owner ✅ | Admin ✅ | Member ✅ | Guest ✅
     * Tất cả vai trò đều được upload, không phân biệt.
     *
     * @param int $userId      ID người dùng cần kiểm tra
     * @param int $workspaceId ID Workspace hiện tại
     * @return bool
     */
    public function canUploadAttachment(int $userId, int $workspaceId): bool
    {
        return $this->hasPermission($userId, $workspaceId, 'upload_attachment');
    }

    /**
     * Kiểm tra quyền xóa file đính kèm.
     *
     * Theo SRS Phần 1.3 và nguyên tắc tương tự Comment system (Phần 3.4.1):
     *   - Owner / Admin : được xóa attachment của BẤT KỲ ai
     *   - Member        : chỉ xóa attachment do CHÍNH MÌNH upload
     *   - Guest         : chỉ xóa attachment do CHÍNH MÌNH upload
     *
     * WHY cần $uploaderId:
     *   hasPermission() chỉ biết role, không biết ai là chủ sở hữu file.
     *   Logic "của mình" phải xét thêm uploader_id từ bảng attachments.
     *   AttachmentController truyền uploader_id vào đây sau khi query DB.
     *
     * @param int $userId      ID người dùng đang thực hiện xóa
     * @param int $workspaceId ID Workspace hiện tại
     * @param int $uploaderId  ID người đã upload file (lấy từ attachments.uploader_id)
     * @return bool
     */
    public function canDeleteAttachment(
        int $userId,
        int $workspaceId,
        int $uploaderId
    ): bool {
        $role = $this->memberModel->getRole($workspaceId, $userId);

        if (!$role) {
            return false;
        }

        // Owner và Admin: xóa được attachment của bất kỳ ai
        if (in_array($role, ['owner', 'admin'])) {
            return true;
        }

        // Member và Guest: chỉ xóa được attachment của chính mình
        // WHY không dùng hasPermission() ở đây:
        // hasPermission() không có thông tin về $uploaderId,
        // nên phải xử lý riêng tại đây thay vì gọi qua permission matrix.
        if (in_array($role, ['member', 'guest'])) {
            return $userId === $uploaderId;
        }

        return false;
    }

    // =========================================================================
    // [BỔ SUNG] Các method liên quan đến Invitation – đồng bộ với WorkspaceMember
    // =========================================================================

    /**
     * Kiểm tra quyền mời thành viên mới vào Workspace.
     *
     * WHY bổ sung tại đây (thay vì chỉ có ở WorkspaceMember model):
     *   Theo kiến trúc TDD mục 3.4, RbacService là "source of truth" cho
     *   toàn bộ permission logic. Controller nào dùng injection RbacService
     *   (thay vì WorkspaceMember trực tiếp) cũng cần gọi được method này.
     *   Việc có method ở cả 2 chỗ là intentional – WorkspaceMember.canInviteMembers()
     *   phục vụ InvitationController (legacy call pattern), còn method này
     *   phục vụ các Controller inject RbacService (pattern chuẩn hơn).
     *
     * Delegate sang hasPermission() để dùng chung permission matrix,
     * không duplicate điều kiện role.
     *
     * @param int $userId      ID của user thực hiện hành động
     * @param int $workspaceId ID của Workspace mục tiêu
     * @return bool
     */
    public function canInviteMembers(int $userId, int $workspaceId): bool
    {
        return $this->hasPermission($userId, $workspaceId, 'invite_members');
    }

    /**
     * Lấy role của user trong Workspace thông qua RbacService.
     *
     * WHY expose method này tại Service layer:
     *   Controller đôi khi cần biết role cụ thể để đưa ra quyết định phức
     *   tạp hơn (ví dụ: admin không được assign role admin cho người khác).
     *   Thay vì inject thêm WorkspaceMember vào Controller đã inject RbacService,
     *   expose proxy method này để Controller chỉ cần 1 dependency.
     *
     * @param int $userId
     * @param int $workspaceId
     * @return string|null 'owner' | 'admin' | 'member' | 'guest' | null
     */
    public function getRoleInWorkspace(int $userId, int $workspaceId): string|null
    {
        return $this->memberModel->getRole($workspaceId, $userId);
    }

    /**
     * Kiểm tra user có phải thành viên của Workspace không (bất kể role).
     *
     * WHY cần method riêng thay vì check getRoleInWorkspace() !== null:
     *   Ngữ nghĩa tường minh hơn khi đọc code.
     *   isMember() trong WorkspaceMember dùng COUNT(*) – đồng nhất behavior.
     *
     * @param int $userId
     * @param int $workspaceId
     * @return bool
     */
    public function isMember(int $userId, int $workspaceId): bool
    {
        return $this->memberModel->isMember($workspaceId, $userId);
    }

    // =========================================================================
    // [BỔ SUNG] Fix lỗi Intelephense P1013 trong WorkspaceController.php
    // Ln 193, 221 – "Undefined method 'canManageWorkspace'"
    // =========================================================================

    /**
     * Kiểm tra quyền quản lý cài đặt Workspace (sửa tên, avatar, description...).
     *
     * WHY tách riêng canManageWorkspace() thay vì dùng canManageMembers():
     *   'manage_members' = quyền thêm/xóa/đổi role thành viên.
     *   'manage_workspace' = quyền sửa thông tin Workspace (tên, slug, avatar,
     *   description, settings JSON). Đây là 2 hành động độc lập – tách permission
     *   để sau này có thể grant từng loại riêng mà không bị phụ thuộc.
     *
     * Phân quyền theo TDD mục 2.2.2 (bảng workspace_members, role ENUM):
     *   owner  ✅ – toàn quyền, kể cả xóa Workspace
     *   admin  ✅ – sửa được cài đặt, nhưng KHÔNG xóa Workspace
     *   member ❌ – chỉ đọc
     *   guest  ❌ – chỉ đọc
     *
     * @param int $userId      ID của user đang thực hiện hành động
     * @param int $workspaceId ID của Workspace mục tiêu
     * @return bool
     */
    public function canManageWorkspace(int $userId, int $workspaceId): bool
    {
        return $this->hasPermission($userId, $workspaceId, 'manage_workspace');
    }
}