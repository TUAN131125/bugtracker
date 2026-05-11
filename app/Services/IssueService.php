<?php

namespace App\Services;

class IssueService
{
    public function createIssue(int $projectId, int $userId, array $data): int
    {
        throw new \Exception("Not implemented");
    }

    public function changeStatus(int $issueId, string $newStatus, int $userId): bool
    {
        throw new \Exception("Not implemented");
    }

    public function assignIssue(int $issueId, int $assigneeId, int $actorId): bool
    {
        throw new \Exception("Not implemented");
    }

    public function getValidTransitions(string $currentStatus, string $userRole): array
    {
        throw new \Exception("Not implemented");
    }

    public function getStateMachine(): array
    {
        throw new \Exception("Not implemented");
    }
}