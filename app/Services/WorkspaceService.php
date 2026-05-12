<?php

namespace App\Services;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Core\Session;

class WorkspaceService
{
    private Workspace $workspaceModel;
    private WorkspaceMember $memberModel;
    private ActivityLogService $activityLog;

    public function __construct()
    {
        $this->workspaceModel = new Workspace();
        $this->memberModel    = new WorkspaceMember();
        $this->activityLog    = new ActivityLogService();
    }

    public function createWorkspace(int $userId, array $data): int
    {
        // Tạo workspace
        $workspaceId = $this->workspaceModel->create([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'owner_id'    => $userId,
        ]);

        // Tự động thêm người tạo vào với role owner
        $this->memberModel->add($workspaceId, $userId, 'owner');

        return $workspaceId;
    }

    public function updateWorkspace(int $id, array $data): bool
    {
        return $this->workspaceModel->update($id, $data);
    }

    public function transferOwnership(int $workspaceId, int $fromUserId, int $toUserId): bool
    {
        // Đổi owner cũ thành admin
        $this->memberModel->updateRole($workspaceId, $fromUserId, 'admin');

        // Đổi người mới thành owner
        $this->memberModel->updateRole($workspaceId, $toUserId, 'owner');

        // Cập nhật owner_id trong bảng workspaces
        return $this->workspaceModel->update($workspaceId, [
            'owner_id' => $toUserId,
        ]);
    }

    public function deleteWorkspace(int $workspaceId): bool
    {
        return $this->workspaceModel->delete($workspaceId);
    }

    public function switchWorkspace(int $userId, int $workspaceId): bool
    {
        // Kiểm tra user có phải member không trước khi switch
        if (!$this->memberModel->isMember($workspaceId, $userId)) {
            return false;
        }

        Session::set('active_workspace_id', $workspaceId);
        return true;
    }
}