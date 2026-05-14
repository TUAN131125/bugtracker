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
            ],
            'admin'  => [
                'manage_project', 'create_issue', 'assign_issue', 'close_issue',
                'manage_members', 'manage_tags', 'manage_milestones',
                'upload_attachment', 'delete_attachment',
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
}