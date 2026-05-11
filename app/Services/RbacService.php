<?php

namespace App\Services;

class RbacService
{
    public function hasPermission(int $userId, int $workspaceId, string $action): bool
    {
        throw new \Exception("Not implemented");
    }

    public function canManageProject(int $userId, int $workspaceId): bool
    {
        throw new \Exception("Not implemented");
    }

    public function canCreateIssue(int $userId, int $workspaceId): bool
    {
        throw new \Exception("Not implemented");
    }

    public function canAssignIssue(int $userId, int $workspaceId): bool
    {
        throw new \Exception("Not implemented");
    }

    public function canCloseIssue(int $userId, int $workspaceId): bool
    {
        throw new \Exception("Not implemented");
    }

    public function canManageMembers(int $userId, int $workspaceId): bool
    {
        throw new \Exception("Not implemented");
    }

    public function canDeleteWorkspace(int $userId, int $workspaceId): bool
    {
        throw new \Exception("Not implemented");
    }
}