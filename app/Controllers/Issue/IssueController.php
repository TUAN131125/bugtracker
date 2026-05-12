<?php

namespace App\Controllers\Issue;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Issue;
use App\Models\Project;
use App\Models\WorkspaceMember;
use App\Services\IssueService;
use App\Services\RbacService;

class IssueController
{
    private Issue $issueModel;
    private Project $projectModel;
    private IssueService $issueService;
    private RbacService $rbacService;
    private WorkspaceMember $memberModel;

    public function __construct()
    {
        $this->issueModel   = new Issue();
        $this->projectModel = new Project();
        $this->issueService = new IssueService();
        $this->rbacService  = new RbacService();
        $this->memberModel  = new WorkspaceMember();
    }

    // Danh sách Issue
    public function index(string $projectKey): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $project     = $this->projectModel->findByKey($projectKey, $workspaceId);

        if (!$project) Response::redirect('/404');

        $filters = [
            'status'      => Request::get('status'),
            'priority'    => Request::get('priority'),
            'assignee_id' => Request::get('assignee_id'),
            'milestone_id'=> Request::get('milestone_id'),
            'keyword'     => Request::get('q'),
            'limit'       => 20,
            'offset'      => ((int)(Request::get('page') ?? 1) - 1) * 20,
        ];

        $issues = $this->issueModel->listByProject($project['id'], $workspaceId, $filters);

        Response::view('issues/list', [
            'project' => $project,
            'issues'  => $issues,
            'filters' => $filters,
        ]);
    }

    // Form tạo Issue
    public function create(string $projectKey): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $project     = $this->projectModel->findByKey($projectKey, $workspaceId);

        if (!$project) Response::redirect('/404');

        Response::view('issues/form', [
            'project' => $project,
            'issue'   => null,
        ]);
    }

    // Xử lý tạo Issue mới
    public function store(string $projectKey): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');
        $project     = $this->projectModel->findByKey($projectKey, $workspaceId);

        if (!$project) Response::redirect('/404');

        if (!$this->rbacService->canCreateIssue($userId, $workspaceId)) {
            Response::setFlash('error', 'Bạn không có quyền tạo Issue.');
            Response::redirect('/projects/' . $projectKey);
        }

        $data = [
            'title'        => Request::post('title'),
            'description'  => Request::post('description'),
            'type'         => Request::post('type') ?? 'bug',
            'severity'     => Request::post('severity') ?? 'major',
            'priority'     => Request::post('priority') ?? 'medium',
            'assignee_id'  => Request::post('assignee_id') ?: null,
            'milestone_id' => Request::post('milestone_id') ?: null,
        ];

        $this->issueService->createIssue($project['id'], $workspaceId, $userId, $data);

        Response::setFlash('success', 'Issue đã được tạo thành công!');
        Response::redirect('/projects/' . $projectKey . '/issues');
    }

    // Chi tiết Issue
    public function show(string $projectKey, string $issueKey): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $userId      = Session::get('user_id');
        $issue       = $this->issueModel->findById($issueKey, $workspaceId);

        if (!$issue) Response::redirect('/404');

        // Lấy role của user để biết transitions nào hợp lệ
        $userRole         = $this->memberModel->getRole($workspaceId, $userId) ?? 'guest';
        $validTransitions = $this->issueService->getValidTransitions($issue['status'], $userRole);

        Response::view('issues/detail', [
            'issue'            => $issue,
            'valid_transitions'=> $validTransitions,
            'user_role'        => $userRole,
        ]);
    }

    // Form sửa Issue
    public function edit(string $projectKey, string $issueKey): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $issue       = $this->issueModel->findById($issueKey, $workspaceId);

        if (!$issue) Response::redirect('/404');

        Response::view('issues/form', ['issue' => $issue]);
    }

    // Cập nhật Issue
    public function update(string $projectKey, string $issueKey): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $issue       = $this->issueModel->findById($issueKey, $workspaceId);

        if (!$issue) Response::redirect('/404');

        $data = [
            'title'        => Request::post('title'),
            'description'  => Request::post('description'),
            'type'         => Request::post('type') ?? 'bug',
            'severity'     => Request::post('severity') ?? 'major',
            'priority'     => Request::post('priority') ?? 'medium',
            'assignee_id'  => Request::post('assignee_id') ?: null,
            'milestone_id' => Request::post('milestone_id') ?: null,
        ];

        $this->issueModel->update($issue['id'], $data);

        Response::setFlash('success', 'Issue đã được cập nhật!');
        Response::redirect('/projects/' . $projectKey . '/issues/' . $issueKey);
    }

    // Đổi status – AJAX endpoint
    public function updateStatus(string $issueKey): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');
        $userRole    = $this->memberModel->getRole($workspaceId, $userId) ?? 'guest';

        $issue = $this->issueModel->findById($issueKey, $workspaceId);
        if (!$issue) {
            Response::json(['success' => false, 'message' => 'Issue không tồn tại.'], 404);
        }

        $newStatus = Request::post('new_status');

        try {
            $this->issueService->changeStatus(
                $issue['id'],
                $newStatus,
                $userId,
                $workspaceId,
                $userRole
            );

            Response::json([
                'success'    => true,
                'new_status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // Gán Assignee
    public function assign(string $issueKey): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');
        $issue       = $this->issueModel->findById($issueKey, $workspaceId);

        if (!$issue) {
            Response::json(['success' => false, 'message' => 'Issue không tồn tại.'], 404);
        }

        $assigneeId = (int) Request::post('assignee_id');

        $this->issueService->assignIssue($issue['id'], $assigneeId, $userId, $workspaceId);

        Response::json(['success' => true, 'message' => 'Đã gán Assignee thành công.']);
    }
}