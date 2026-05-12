<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Project;

class IssueService
{
    private Issue $issueModel;
    private ActivityLogService $activityLog;

    public function __construct()
    {
        $this->issueModel  = new Issue();
        $this->activityLog = new ActivityLogService();
    }

    // -------------------------------------------------------
    // STATE MACHINE – Ma trận trạng thái hợp lệ
    // -------------------------------------------------------
    public function getStateMachine(): array
    {
        return [
            'open' => [
                'owner'  => ['in_triage', 'in_progress', 'wont_fix', 'duplicate'],
                'admin'  => ['in_triage', 'in_progress', 'wont_fix', 'duplicate'],
                'member' => ['in_triage', 'in_progress'],
                'guest'  => [],
            ],
            'in_triage' => [
                'owner'  => ['in_progress', 'wont_fix', 'duplicate'],
                'admin'  => ['in_progress', 'wont_fix', 'duplicate'],
                'member' => ['in_progress'],
                'guest'  => [],
            ],
            'in_progress' => [
                'owner'  => ['resolved', 'wont_fix'],
                'admin'  => ['resolved', 'wont_fix'],
                'member' => ['resolved'],
                'guest'  => [],
            ],
            'resolved' => [
                'owner'  => ['closed', 'reopened'],
                'admin'  => ['closed', 'reopened'],
                'member' => ['closed', 'reopened'],
                'guest'  => [],
            ],
            'closed' => [
                'owner'  => ['reopened'],
                'admin'  => ['reopened'],
                'member' => [],
                'guest'  => [],
            ],
            'reopened' => [
                'owner'  => ['in_progress', 'wont_fix'],
                'admin'  => ['in_progress', 'wont_fix'],
                'member' => ['in_progress'],
                'guest'  => [],
            ],
            'wont_fix'  => [],
            'duplicate' => [],
        ];
    }

    public function getValidTransitions(string $currentStatus, string $userRole): array
    {
        $machine = $this->getStateMachine();
        $transitions = $machine[$currentStatus] ?? [];

        if (empty($transitions)) return [];

        return $transitions[$userRole] ?? [];
    }

    public function changeStatus(int $issueId, string $newStatus, int $userId, int $workspaceId, string $userRole): bool
    {
        // Lấy issue hiện tại
        $db   = \App\Core\Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM issues WHERE id = ? AND workspace_id = ?");
        $stmt->execute([$issueId, $workspaceId]);
        $issue = $stmt->fetch();

        if (!$issue) {
            throw new \Exception("Issue không tồn tại.");
        }

        // Kiểm tra transition hợp lệ
        $validTransitions = $this->getValidTransitions($issue['status'], $userRole);
        if (!in_array($newStatus, $validTransitions)) {
            throw new \Exception("Không thể chuyển từ '{$issue['status']}' sang '{$newStatus}'.");
        }

        // Cập nhật status
        $this->issueModel->updateStatus($issueId, $newStatus, $userId);

        // Ghi Activity Log
        $this->activityLog->log(
            $workspaceId,
            $userId,
            'issue',
            $issueId,
            ActivityLogService::ISSUE_STATUS_CHANGED,
            $issue['status'],
            $newStatus
        );

        return true;
    }

    public function createIssue(int $projectId, int $workspaceId, int $userId, array $data): int
    {
        // Kiểm tra project không bị archived
        $projectModel = new Project();
        $project      = $projectModel->findById($projectId, $workspaceId);

        if (!$project) {
            throw new \Exception("Project không tồn tại.");
        }
        if ($project['status'] === 'archived') {
            throw new \Exception("Không thể tạo Issue trong Project đã Archive.");
        }

        $data['reporter_id'] = $userId;
        $issueId = $this->issueModel->create($projectId, $workspaceId, $data);

        // Ghi Activity Log
        $this->activityLog->log(
            $workspaceId,
            $userId,
            'issue',
            $issueId,
            ActivityLogService::ISSUE_CREATED,
            null,
            null
        );

        return $issueId;
    }

    public function assignIssue(int $issueId, int $assigneeId, int $actorId, int $workspaceId): bool
    {
        $db   = \App\Core\Database::getInstance();
        $stmt = $db->prepare("
            UPDATE issues SET assignee_id = ?, updated_at = NOW()
            WHERE id = ? AND workspace_id = ?
        ");
        $result = $stmt->execute([$assigneeId, $issueId, $workspaceId]);

        if ($result) {
            $this->activityLog->log(
                $workspaceId,
                $actorId,
                'issue',
                $issueId,
                ActivityLogService::ISSUE_ASSIGNED,
                null,
                $assigneeId
            );
        }

        return $result;
    }
}