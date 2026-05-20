<?php

namespace App\Services;

use App\Models\Notification;
use App\Core\Database;

class NotificationService
{
    private Notification $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new Notification();
    }

    public function notifyAssigned(int $issueId, int $assigneeId, int $workspaceId): void
    {
        $issue = $this->getIssue($issueId, $workspaceId);
        if (!$issue) return;

        $this->notificationModel->create([
            'user_id'      => $assigneeId,
            'workspace_id' => $workspaceId,
            'type'         => 'issue_assigned',
            'entity_type'  => 'issue',
            'entity_id'    => $issueId,
            'message'      => "Bạn được giao Issue {$issue['issue_key']}: {$issue['title']}",
            'url'          => "/issues/{$issue['issue_key']}",
        ]);
    }

    public function notifyStatusChanged(int $issueId, int $actorId, int $workspaceId, string $newStatus): void
    {
        $issue = $this->getIssue($issueId, $workspaceId);
        if (!$issue) return;

        // Thông báo cho reporter và assignee (trừ người thực hiện)
        $targets = array_filter(
            [$issue['reporter_id'], $issue['assignee_id']],
            fn($id) => $id && (int)$id !== $actorId
        );

        foreach ($targets as $userId) {
            $this->notificationModel->create([
                'user_id'      => $userId,
                'workspace_id' => $workspaceId,
                'type'         => 'status_changed',
                'entity_type'  => 'issue',
                'entity_id'    => $issueId,
                'message'      => "Issue {$issue['issue_key']} đã chuyển sang trạng thái {$newStatus}",
                'url'          => "/issues/{$issue['issue_key']}",
            ]);
        }
    }

    public function notifyCommented(int $issueId, int $commenterId, int $workspaceId): void
    {
        $issue = $this->getIssue($issueId, $workspaceId);
        if (!$issue) return;

        $targets = array_filter(
            [$issue['reporter_id'], $issue['assignee_id']],
            fn($id) => $id && (int)$id !== $commenterId
        );

        foreach ($targets as $userId) {
            $this->notificationModel->create([
                'user_id'      => $userId,
                'workspace_id' => $workspaceId,
                'type'         => 'issue_commented',
                'entity_type'  => 'issue',
                'entity_id'    => $issueId,
                'message'      => "Có bình luận mới trong Issue {$issue['issue_key']}",
                'url'          => "/issues/{$issue['issue_key']}",
            ]);
        }
    }

    public function notifyMentioned(int $issueId, int $mentionedUserId, int $workspaceId): void
    {
        $issue = $this->getIssue($issueId, $workspaceId);
        if (!$issue) return;

        $this->notificationModel->create([
            'user_id'      => $mentionedUserId,
            'workspace_id' => $workspaceId,
            'type'         => 'mentioned',
            'entity_type'  => 'issue',
            'entity_id'    => $issueId,
            'message'      => "Bạn được nhắc đến trong Issue {$issue['issue_key']}",
            'url'          => "/issues/{$issue['issue_key']}",
        ]);
    }

    private function getIssue(int $issueId, int $workspaceId): array|false
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM issues WHERE id = ? AND workspace_id = ?
        ");
        $stmt->execute([$issueId, $workspaceId]);
        return $stmt->fetch();
    }
}