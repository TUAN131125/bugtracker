<?php

namespace App\Services;

class NotificationService
{
    public function notifyAssigned(int $issueId, int $assigneeId): void
    {
        throw new \Exception("Not implemented");
    }

    public function notifyStatusChanged(int $issueId, int $actorId): void
    {
        throw new \Exception("Not implemented");
    }

    public function notifyCommented(int $issueId, int $commenterId): void
    {
        throw new \Exception("Not implemented");
    }

    public function notifyMentioned(int $issueId, int $mentionedUserId): void
    {
        throw new \Exception("Not implemented");
    }
}