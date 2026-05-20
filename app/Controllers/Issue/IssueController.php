<?php

declare(strict_types=1);

namespace App\Controllers\Issue;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Models\Issue;
use App\Models\Project;
use App\Models\WorkspaceMember;
use App\Services\IssueService;
use App\Services\RbacService;

/**
 * IssueController
 *
 * Xử lý toàn bộ HTTP layer cho Issue:
 * CRUD, đổi trạng thái (AJAX), gán Assignee (AJAX).
 *
 * KHÔNG chứa business logic – mọi nghiệp vụ delegate sang IssueService.
 * Request và Response được inject vào từng method (không dùng static call).
 *
 * @author  Dev 2
 * @version 1.0.1 – sửa lỗi non-static call Request/Response
 * @see     TDD Backend v1.0.0 Phần 3.3 (Request lifecycle)
 * @see     Task D2-013, D2-014, D2-015
 */
class IssueController
{
    private Issue           $issue_model;
    private Project         $project_model;
    private IssueService    $issue_service;
    private RbacService     $rbac_service;
    private WorkspaceMember $member_model;

    public function __construct()
    {
        $this->issue_model   = new Issue();
        $this->project_model = new Project();
        $this->issue_service = new IssueService();
        $this->rbac_service  = new RbacService();
        $this->member_model  = new WorkspaceMember();
    }

    // =========================================================================
    // INDEX – Danh sách Issue
    // GET /issues  (projectKey từ route param)
    // =========================================================================

    /**
     * Hiển thị danh sách Issue của một Project, có filter và phân trang.
     *
     * Filter state được giữ qua URL query params để URL shareable
     * theo ViewLayer Guide Phần 6.3.
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $projectKey  Project KEY (VD: BT, SHOP)
     */
    public function index(Request $request, Response $response, string $projectKey): void
    {
        $workspace_id = (int) Session::get('active_workspace_id');
        $project      = $this->project_model->findByKey($projectKey, $workspace_id);

        if ($project === null) {
            $response->redirect('/404');
            return;
        }

        $page   = max(1, (int) ($request->get('page') ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $filters = [
            'status'       => $request->get('status'),
            'priority'     => $request->get('priority'),
            'severity'     => $request->get('severity'),
            'assignee_id'  => $request->get('assignee_id'),
            'milestone_id' => $request->get('milestone_id'),
            'tag_id'       => $request->get('tag_id'),
            'keyword'      => $request->get('q'),
            'limit'        => $limit,
            'offset'       => $offset,
        ];

        $issues = $this->issue_model->listByProject(
            (int) $project['id'],
            $workspace_id,
            $filters
        );

        $response->view('issues/list', [
            'page_title' => $project['name'] . ' – Danh sách Issue',
            'page_id'    => 'issue-list',
            'project'    => $project,
            'issues'     => $issues,
            'filters'    => $filters,
            'page'       => $page,
            'limit'      => $limit,
        ]);
    }

    // =========================================================================
    // CREATE – Form tạo Issue mới
    // GET /issues/create
    // =========================================================================

    /**
     * Hiển thị form tạo Issue mới.
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $projectKey
     */
    public function create(Request $request, Response $response, string $projectKey): void
    {
        $workspace_id = (int) Session::get('active_workspace_id');
        $project      = $this->project_model->findByKey($projectKey, $workspace_id);

        if ($project === null) {
            $response->redirect('/404');
            return;
        }

        // Project archived → không cho tạo Issue mới (SRS Phần 3.2.2)
        if ($project['status'] === 'archived') {
            $response->setFlash('error', 'Project này đã bị Archive. Không thể tạo Issue mới.');
            $response->redirect('/projects/' . $projectKey);
            return;
        }

        $response->view('issues/form', [
            'page_title' => 'Tạo Issue mới – ' . $project['name'],
            'page_id'    => 'issue-form',
            'project'    => $project,
            'issue'      => null,         // null = chế độ tạo mới
            'old_input'  => [],
            'errors'     => [],
            'csrf_token' => Csrf::generateToken(),
        ]);
    }

    // =========================================================================
    // STORE – Lưu Issue mới
    // POST /issues
    // =========================================================================

    /**
     * Xử lý POST tạo Issue mới, kèm file đính kèm (nếu có).
     *
     * Luồng theo SRS UC-019:
     * 1. Validate CSRF
     * 2. Kiểm tra quyền tạo Issue
     * 3. Delegate sang IssueService::createIssue()
     * 4. Redirect với flash message
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $projectKey
     */
    public function store(Request $request, Response $response, string $projectKey): void
    {
        // 1. Validate CSRF trước tiên (TDD Phần 4.7)
        if (!Csrf::validateToken($request->post('csrf_token'))) {
            $response->setFlash('error', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
            $response->redirect('/projects/' . $projectKey . '/issues/create');
            return;
        }

        $user_id      = (int) Session::get('user_id');
        $workspace_id = (int) Session::get('active_workspace_id');
        $project      = $this->project_model->findByKey($projectKey, $workspace_id);

        if ($project === null) {
            $response->redirect('/404');
            return;
        }

        // Project archived – kiểm tra lại phía server (không chỉ dựa vào UI)
        if ($project['status'] === 'archived') {
            $response->setFlash('error', 'Project này đã bị Archive. Không thể tạo Issue mới.');
            $response->redirect('/projects/' . $projectKey);
            return;
        }

        // 2. Kiểm tra quyền (SRS Phần 1.3 – Guest không tạo được Issue có assignee)
        if (!$this->rbac_service->canCreateIssue($user_id, $workspace_id)) {
            $response->setFlash('error', 'Bạn không có quyền tạo Issue trong Workspace này.');
            $response->redirect('/projects/' . $projectKey);
            return;
        }

        $data = [
            'title'        => $request->post('title'),
            'description'  => $request->post('description'),
            'type'         => $request->post('type')         ?? 'bug',
            'severity'     => $request->post('severity')     ?? 'major',
            'priority'     => $request->post('priority')     ?? 'medium',
            'assignee_id'  => $request->post('assignee_id')  ?: null,
            'milestone_id' => $request->post('milestone_id') ?: null,
            'tags'         => $request->post('tags')         ?? [],  // array tag IDs
        ];

        // 3. File đính kèm – truyền $_FILES sang Service xử lý (D1-026)
        $files = $request->file('attachments') ?? [];

        try {
            $this->issue_service->createIssue(
                (int) $project['id'],
                $workspace_id,
                $user_id,
                $data,
                $files
            );

            $response->setFlash('success', 'Issue đã được tạo thành công!');
            $response->redirect('/projects/' . $projectKey . '/issues');

        } catch (\InvalidArgumentException $e) {
            // Lỗi validation từ Service (title trống, file quá lớn...)
            $response->setFlash('error', $e->getMessage());
            $response->redirect('/projects/' . $projectKey . '/issues/create');

        } catch (\RuntimeException $e) {
            // Lỗi hệ thống (DB, file system...)
            $response->setFlash('error', 'Đã xảy ra lỗi khi tạo Issue. Vui lòng thử lại.');
            $response->redirect('/projects/' . $projectKey . '/issues/create');
        }
    }

    // =========================================================================
    // SHOW – Chi tiết Issue
    // GET /issues/{issueKey}
    // =========================================================================

    /**
     * Hiển thị trang chi tiết Issue.
     *
     * Truyền $valid_transitions vào View để Dev 3 render đúng
     * các nút chuyển trạng thái theo ViewLayer Guide Phần 6.4.
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $projectKey
     * @param string   $issueKey   VD: BT-001
     */
    public function show(
        Request  $request,
        Response $response,
        string   $projectKey,
        string   $issueKey
    ): void {
        $workspace_id = (int) Session::get('active_workspace_id');
        $user_id      = (int) Session::get('user_id');

        $issue = $this->issue_model->findByKey($issueKey, $workspace_id);

        if ($issue === null) {
            $response->redirect('/404');
            return;
        }

        // Lấy role để biết transitions nào hợp lệ (ViewLayer Guide Phần 6.4)
        $user_role        = $this->member_model->getRole($workspace_id, $user_id) ?? 'guest';
        $valid_transitions = $this->issue_service->getValidTransitions(
            $issue['status'],
            $user_role
        );

        $response->view('issues/detail', [
            'page_title'        => '[' . $issueKey . '] ' . $issue['title'],
            'page_id'           => 'issue-detail',
            'issue'             => $issue,
            'valid_transitions' => $valid_transitions,
            'user_role'         => $user_role,
            'csrf_token'        => Csrf::generateToken(),
        ]);
    }

    // =========================================================================
    // EDIT – Form chỉnh sửa Issue
    // GET /issues/{issueKey}/edit
    // =========================================================================

    /**
     * Hiển thị form chỉnh sửa Issue.
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $projectKey
     * @param string   $issueKey
     */
    public function edit(
        Request  $request,
        Response $response,
        string   $projectKey,
        string   $issueKey
    ): void {
        $workspace_id = (int) Session::get('active_workspace_id');
        $issue        = $this->issue_model->findByKey($issueKey, $workspace_id);

        if ($issue === null) {
            $response->redirect('/404');
            return;
        }

        $response->view('issues/form', [
            'page_title' => 'Sửa Issue – ' . $issueKey,
            'page_id'    => 'issue-form',
            'issue'      => $issue,   // khác null = chế độ chỉnh sửa
            'old_input'  => $issue,   // prefill form bằng dữ liệu hiện tại
            'errors'     => [],
            'csrf_token' => Csrf::generateToken(),
        ]);
    }

    // =========================================================================
    // UPDATE – Lưu chỉnh sửa Issue
    // PUT /issues/{issueKey}
    // =========================================================================

    /**
     * Xử lý PUT cập nhật thông tin Issue.
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $projectKey
     * @param string   $issueKey
     */
    public function update(
        Request  $request,
        Response $response,
        string   $projectKey,
        string   $issueKey
    ): void {
        if (!Csrf::validateToken($request->post('csrf_token'))) {
            $response->setFlash('error', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
            $response->redirect('/projects/' . $projectKey . '/issues/' . $issueKey . '/edit');
            return;
        }

        $workspace_id = (int) Session::get('active_workspace_id');
        $user_id      = (int) Session::get('user_id');
        $issue        = $this->issue_model->findByKey($issueKey, $workspace_id);

        if ($issue === null) {
            $response->redirect('/404');
            return;
        }

        // Kiểm tra quyền: chỉ Assignee, Admin, Owner mới sửa được (SRS Phần 1.3)
        $user_role = $this->member_model->getRole($workspace_id, $user_id) ?? 'guest';
        $is_assignee = ((int) $issue['assignee_id']) === $user_id;

        if (!$is_assignee && !in_array($user_role, ['owner', 'admin'], true)) {
            $response->setFlash('error', 'Bạn không có quyền chỉnh sửa Issue này.');
            $response->redirect('/projects/' . $projectKey . '/issues/' . $issueKey);
            return;
        }

        $data = [
            'title'        => $request->post('title'),
            'description'  => $request->post('description'),
            'type'         => $request->post('type')         ?? 'bug',
            'severity'     => $request->post('severity')     ?? 'major',
            'priority'     => $request->post('priority')     ?? 'medium',
            'assignee_id'  => $request->post('assignee_id')  ?: null,
            'milestone_id' => $request->post('milestone_id') ?: null,
            'tags'         => $request->post('tags')         ?? [],
        ];

        try {
            $this->issue_model->update((int) $issue['id'], $data);

            $response->setFlash('success', 'Issue đã được cập nhật!');
            $response->redirect('/projects/' . $projectKey . '/issues/' . $issueKey);

        } catch (\Exception $e) {
            $response->setFlash('error', 'Cập nhật thất bại. Vui lòng thử lại.');
            $response->redirect('/projects/' . $projectKey . '/issues/' . $issueKey . '/edit');
        }
    }

    // =========================================================================
    // DESTROY – Xóa Issue (soft delete)
    // DELETE /issues/{issueKey}
    // =========================================================================

    /**
     * Soft delete Issue. Chỉ Owner/Admin (enforce bởi RbacMiddleware trong routes).
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $projectKey
     * @param string   $issueKey
     */
    public function destroy(
        Request  $request,
        Response $response,
        string   $projectKey,
        string   $issueKey
    ): void {
        if (!Csrf::validateToken($request->post('csrf_token'))) {
            $response->setFlash('error', 'Phiên làm việc không hợp lệ.');
            $response->redirect('/projects/' . $projectKey . '/issues/' . $issueKey);
            return;
        }

        $workspace_id = (int) Session::get('active_workspace_id');
        $issue        = $this->issue_model->findByKey($issueKey, $workspace_id);

        if ($issue === null) {
            $response->redirect('/404');
            return;
        }

        $this->issue_model->delete((int) $issue['id']);

        $response->setFlash('success', 'Issue ' . $issueKey . ' đã được xóa.');
        $response->redirect('/projects/' . $projectKey . '/issues');
    }

    // =========================================================================
    // UPDATE STATUS – AJAX endpoint
    // POST /issues/{issueKey}/status
    // =========================================================================

    /**
     * Đổi trạng thái Issue qua AJAX.
     *
     * Request body JSON: { "new_status": "in_progress", "csrf_token": "..." }
     * Response JSON    : { "success": true, "new_status": "...", "updated_at": "..." }
     *
     * State machine validate trong IssueService::changeStatus() (D2-014).
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $issueKey
     */
    public function updateStatus(
        Request  $request,
        Response $response,
        string   $issueKey
    ): void {
        if (!Csrf::validateToken($request->post('csrf_token'))) {
            $response->json(['success' => false, 'message' => 'CSRF token không hợp lệ.'], 403);
            return;
        }

        $user_id      = (int) Session::get('user_id');
        $workspace_id = (int) Session::get('active_workspace_id');
        $user_role    = $this->member_model->getRole($workspace_id, $user_id) ?? 'guest';

        $issue = $this->issue_model->findByKey($issueKey, $workspace_id);

        if ($issue === null) {
            $response->json(['success' => false, 'message' => 'Issue không tồn tại.'], 404);
            return;
        }

        // Chỉ Assignee, Admin, Owner mới được đổi status (SRS Phần 1.3)
        $is_assignee = ((int) $issue['assignee_id']) === $user_id;
        if (!$is_assignee && !in_array($user_role, ['owner', 'admin'], true)) {
            $response->json([
                'success' => false,
                'message' => 'Bạn không có quyền thay đổi trạng thái Issue này.',
            ], 403);
            return;
        }

        $new_status = (string) ($request->post('new_status') ?? '');

        try {
            $this->issue_service->changeStatus(
                (int) $issue['id'],
                $new_status,
                $user_id,
                $workspace_id,
                $user_role
            );

            $response->json([
                'success'    => true,
                'new_status' => $new_status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        } catch (\InvalidArgumentException $e) {
            // Transition không hợp lệ (state machine từ chối)
            $response->json(['success' => false, 'message' => $e->getMessage()], 422);

        } catch (\Exception $e) {
            $response->json(['success' => false, 'message' => 'Đã xảy ra lỗi. Vui lòng thử lại.'], 500);
        }
    }

    // =========================================================================
    // ASSIGN – Gán Assignee qua AJAX
    // POST /issues/{issueKey}/assign
    // =========================================================================

    /**
     * Gán hoặc thay đổi Assignee cho Issue qua AJAX.
     *
     * Request body: { "assignee_id": 5, "csrf_token": "..." }
     * Response    : { "success": true, "message": "..." }
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $issueKey
     */
    public function assign(
        Request  $request,
        Response $response,
        string   $issueKey
    ): void {
        if (!Csrf::validateToken($request->post('csrf_token'))) {
            $response->json(['success' => false, 'message' => 'CSRF token không hợp lệ.'], 403);
            return;
        }

        $user_id      = (int) Session::get('user_id');
        $workspace_id = (int) Session::get('active_workspace_id');
        $issue        = $this->issue_model->findByKey($issueKey, $workspace_id);

        if ($issue === null) {
            $response->json(['success' => false, 'message' => 'Issue không tồn tại.'], 404);
            return;
        }

        $assignee_id = (int) ($request->post('assignee_id') ?? 0);

        try {
            $this->issue_service->assignIssue(
                (int) $issue['id'],
                $assignee_id,
                $user_id,
                $workspace_id
            );

            $response->json([
                'success' => true,
                'message' => 'Đã gán Assignee thành công.',
            ]);

        } catch (\Exception $e) {
            $response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // =========================================================================
    // ADD LINK / REMOVE LINK – Issue Linking (SRS Phần 3.4.8)
    // POST   /issues/{issueKey}/links
    // DELETE /issues/{issueKey}/links/{linkId}
    // =========================================================================

    /**
     * Tạo liên kết giữa hai Issue (SRS UC-033).
     *
     * Request body: { "link_type": "blocks", "target_issue_key": "BT-002", "csrf_token": "..." }
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $issueKey  Issue nguồn
     */
    public function addLink(
        Request  $request,
        Response $response,
        string   $issueKey
    ): void {
        if (!Csrf::validateToken($request->post('csrf_token'))) {
            $response->json(['success' => false, 'message' => 'CSRF token không hợp lệ.'], 403);
            return;
        }

        $user_id      = (int) Session::get('user_id');
        $workspace_id = (int) Session::get('active_workspace_id');

        $source_issue = $this->issue_model->findByKey($issueKey, $workspace_id);
        if ($source_issue === null) {
            $response->json(['success' => false, 'message' => 'Issue nguồn không tồn tại.'], 404);
            return;
        }

        $target_key = (string) ($request->post('target_issue_key') ?? '');
        $link_type  = (string) ($request->post('link_type') ?? '');

        $target_issue = $this->issue_model->findByKey($target_key, $workspace_id);
        if ($target_issue === null) {
            $response->json([
                'success' => false,
                'message' => 'Issue đích không tồn tại hoặc bạn không có quyền truy cập.',
            ], 404);
            return;
        }

        // Không cho liên kết với chính nó (SRS UC-033 – luồng ngoại lệ 6b)
        if ((int) $source_issue['id'] === (int) $target_issue['id']) {
            $response->json([
                'success' => false,
                'message' => 'Không thể liên kết Issue với chính nó.',
            ], 422);
            return;
        }

        try {
            $this->issue_service->addLink(
                (int) $source_issue['id'],
                (int) $target_issue['id'],
                $link_type,
                $user_id
            );

            $response->json(['success' => true, 'message' => 'Đã tạo liên kết thành công.']);

        } catch (\Exception $e) {
            $response->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Xóa liên kết giữa hai Issue (xóa cả 2 chiều theo SRS UC-033).
     *
     * @param Request  $request
     * @param Response $response
     * @param string   $issueKey
     * @param string   $linkId    ID bản ghi trong bảng issue_links
     */
    public function removeLink(
        Request  $request,
        Response $response,
        string   $issueKey,
        string   $linkId
    ): void {
        if (!Csrf::validateToken($request->post('csrf_token'))) {
            $response->json(['success' => false, 'message' => 'CSRF token không hợp lệ.'], 403);
            return;
        }

        $workspace_id = (int) Session::get('active_workspace_id');

        // Validate Issue tồn tại và thuộc workspace này
        $issue = $this->issue_model->findByKey($issueKey, $workspace_id);
        if ($issue === null) {
            $response->json(['success' => false, 'message' => 'Issue không tồn tại.'], 404);
            return;
        }

        try {
            $this->issue_service->removeLink((int) $linkId, (int) $issue['id'], $workspace_id);
            $response->json(['success' => true, 'message' => 'Đã xóa liên kết.']);

        } catch (\Exception $e) {
            $response->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}