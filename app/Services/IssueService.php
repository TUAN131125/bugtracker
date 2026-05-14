<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Project;
use App\Core\Session;

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
            ActivityLogService::ISSUE_STATUS_CHANGED ?? 'status_changed',
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
            ActivityLogService::ISSUE_CREATED ?? 'issue_created',
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
                ActivityLogService::ISSUE_ASSIGNED ?? 'issue_assigned',
                null,
                (string) $assigneeId
            );
        }

        return $result;
    }

    /**
     * Thêm liên kết giữa hai Issue.
     */
    public function addLink(int $sourceIssueId, int $targetIssueId, string $linkType, int $userId): void
    {
        $db = \App\Core\Database::getInstance();

        // 1. Lấy workspace_id từ source issue (để phục vụ việc ghi log)
        $stmt = $db->prepare("SELECT workspace_id FROM issues WHERE id = ? LIMIT 1");
        $stmt->execute([$sourceIssueId]);
        $workspaceId = $stmt->fetchColumn();

        if (!$workspaceId) {
            throw new \Exception("Issue nguồn không tồn tại.");
        }

        // 2. Kiểm tra tránh duplicate link (đã link loại này rồi thì không link lại)
        $checkStmt = $db->prepare("
            SELECT id FROM issue_links 
            WHERE source_issue_id = ? AND target_issue_id = ? AND link_type = ?
        ");
        $checkStmt->execute([$sourceIssueId, $targetIssueId, $linkType]);
        if ($checkStmt->fetch()) {
            throw new \Exception("Liên kết này đã tồn tại.");
        }

        // 3. Thực hiện Insert
        $insertStmt = $db->prepare("
            INSERT INTO issue_links (source_issue_id, target_issue_id, link_type, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $insertStmt->execute([$sourceIssueId, $targetIssueId, $linkType]);

        // 4. Ghi Activity Log
        $action = defined('App\Services\ActivityLogService::ISSUE_LINKED') 
            ? ActivityLogService::ISSUE_LINKED 
            : 'issue_linked';

        $this->activityLog->log(
            (int) $workspaceId,
            $userId,
            'issue',
            $sourceIssueId,
            $action,
            null,
            "Linked to ID: $targetIssueId ($linkType)"
        );
    }

    /**
     * Xóa liên kết giữa hai Issue.
     *
     * @param int $linkId      ID của bản ghi liên kết cần xóa
     * @param int $issueId     ID của issue thực hiện xóa (để validate)
     * @param int $workspaceId ID của workspace (chống IDOR)
     */
    public function removeLink(int $linkId, int $issueId, int $workspaceId): void
    {
        $db = \App\Core\Database::getInstance();

        // 1. Kiểm tra IDOR: Link này phải liên quan đến $issueId và Issue đó phải thuộc $workspaceId
        $stmt = $db->prepare("
            SELECT l.* FROM issue_links l
            JOIN issues i ON (l.source_issue_id = i.id OR l.target_issue_id = i.id)
            WHERE l.id = ? AND i.id = ? AND i.workspace_id = ?
            LIMIT 1
        ");
        $stmt->execute([$linkId, $issueId, $workspaceId]);
        $link = $stmt->fetch();

        if (!$link) {
            throw new \Exception("Không tìm thấy liên kết hoặc bạn không có quyền xóa.");
        }

        // 2. Thực hiện xóa
        $deleteStmt = $db->prepare("DELETE FROM issue_links WHERE id = ?");
        $deleteStmt->execute([$linkId]);

        // 3. Ghi Activity Log (Lấy userId từ Session vì tham số truyền vào của Controller không có userId)
        $userId = (int) Session::get('user_id'); 
        
        $action = defined('App\Services\ActivityLogService::ISSUE_UNLINKED') 
            ? ActivityLogService::ISSUE_UNLINKED 
            : 'issue_unlinked';

        $this->activityLog->log(
            $workspaceId,
            $userId,
            'issue',
            $issueId,
            $action,
            "Removed link ID: $linkId",
            null
        );
    }
}