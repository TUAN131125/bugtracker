<?php

namespace App\Services;

class WorkspaceService
{
    public function createWorkspace(int $userId, array $data): int
    {
        throw new \Exception("Not implemented");
    }

    public function updateWorkspace(int $id, array $data): bool
    {
        throw new \Exception("Not implemented");
    }

    public function transferOwnership(int $workspaceId, int $fromUserId, int $toUserId): bool
    {
        throw new \Exception("Not implemented");
    }

    public function deleteWorkspace(int $workspaceId): bool
    {
        throw new \Exception("Not implemented");
    }

    public function switchWorkspace(int $userId, int $workspaceId): bool
    {
        throw new \Exception("Not implemented");
    }
}