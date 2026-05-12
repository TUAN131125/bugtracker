<?php

namespace App\Services;

class ActivityLogService
{
    const ISSUE_CREATED        = 'issue_created';
    const ISSUE_STATUS_CHANGED = 'issue_status_changed';
    const ISSUE_ASSIGNED       = 'issue_assigned';
    const COMMENT_ADDED        = 'comment_added';

    public function log(int $workspaceId, int $userId, string $entityType, int $entityId, string $action, mixed $oldValue = null, mixed $newValue = null): void
    {
        throw new \Exception("Not implemented");
    }

    public function getForIssue(int $issueId, int $workspaceId): array
    {
        throw new \Exception("Not implemented");
    }

    public function getForWorkspace(int $workspaceId, int $limit = 50): array
    {
        throw new \Exception("Not implemented");
    }
}