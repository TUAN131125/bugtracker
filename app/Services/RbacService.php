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
                'manage_members', 'delete_workspace', 'manage_tags', 'manage_milestones'
            ],
            'admin'  => [
                'manage_project', 'create_issue', 'assign_issue', 'close_issue',
                'manage_members', 'manage_tags', 'manage_milestones'
            ],
            'member' => [
                'create_issue', 'assign_issue', 'close_issue'
            ],
            'guest'  => [],
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
}