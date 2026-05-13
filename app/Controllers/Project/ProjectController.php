<?php

declare(strict_types=1);

namespace App\Controllers\Project;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Models\Project;
use App\Services\ProjectService;
use App\Services\RbacService;

/**
 * ProjectController
 *
 * Xử lý toàn bộ CRUD và Archive cho Project trong Workspace.
 * Mọi thao tác thay đổi dữ liệu (store, update, archive) đều:
 *   1. Validate CSRF token
 *   2. Kiểm tra quyền qua RbacService
 *   3. Gọi ProjectService / Model – KHÔNG viết logic DB trực tiếp ở đây
 *
 * Route mapping (routes.php):
 *   GET    /projects              → index()
 *   GET    /projects/create       → create()
 *   POST   /projects              → store()
 *   GET    /projects/{key}        → show($key)
 *   GET    /projects/{key}/edit   → edit($key)
 *   PUT    /projects/{key}        → update($key)
 *   POST   /projects/{key}/archive→ archive($key)
 *
 * @see TDD Backend v1.0.0  – Phần 3.3 (Request lifecycle), Phần 3.4 (RBAC)
 * @see SRS v1.0.0          – UC-015 (Tạo Project), UC-016 (Sửa), UC-017 (Archive)
 * @see Task Assignment     – D2-011 (Dev 2 – ProjectController)
 *
 * @author  Dev 2
 * @version 1.0.0
 */
class ProjectController
{
    private Project        $projectModel;
    private ProjectService $projectService;
    private RbacService    $rbacService;
    private Request        $request;

    public function __construct()
    {
        $this->projectModel   = new Project();
        $this->projectService = new ProjectService();
        $this->rbacService    = new RbacService();

        // WHY inject Request instance:
        // TDD D1-007 định nghĩa Request là instance object, không phải static class.
        // Gọi Request::post() kiểu static là sai – phải dùng $this->request->post().
        $this->request = new Request();
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Hiển thị danh sách Project trong Workspace đang active.
     *
     * GET /projects
     *
     * @return void
     */
    public function index(): void
    {
        $workspaceId = (int) Session::get('active_workspace_id');
        $projects    = $this->projectModel->listByWorkspace($workspaceId);

        Response::view('projects/list', [
            'pageId'    => 'project-list',
            'pageTitle' => 'Danh sách Project',
            'projects'  => $projects,
        ]);
    }

    /**
     * Hiển thị form tạo Project mới.
     *
     * GET /projects/create
     *
     * @return void
     */
    public function create(): void
    {
        Response::view('projects/form', [
            'pageId'    => 'project-form',
            'pageTitle' => 'Tạo Project mới',
            'project'   => null,
            'oldInput'  => [],
            'errors'    => [],
        ]);
    }

    /**
     * Hiển thị chi tiết một Project theo key.
     *
     * GET /projects/{key}
     *
     * @param  string $key Project key (VD: BT, SHOP)
     * @return void
     */
    public function show(string $key): void
    {
        $workspaceId = (int) Session::get('active_workspace_id');
        $project     = $this->projectModel->findByKey($key, $workspaceId);

        if (!$project) {
            Response::view('errors/404', ['pageId' => 'error', 'pageTitle' => 'Không tìm thấy']);
            return;
        }

        $stats = $this->projectService->getProjectStats((int) $project['id'], $workspaceId);

        Response::view('projects/show', [
            'pageId'    => 'project-detail',
            'pageTitle' => $project['name'],
            'project'   => $project,
            'stats'     => $stats,
        ]);
    }

    /**
     * Hiển thị form chỉnh sửa Project.
     *
     * GET /projects/{key}/edit
     *
     * @param  string $key
     * @return void
     */
    public function edit(string $key): void
    {
        $workspaceId = (int) Session::get('active_workspace_id');
        $project     = $this->projectModel->findByKey($key, $workspaceId);

        if (!$project) {
            Response::view('errors/404', ['pageId' => 'error', 'pageTitle' => 'Không tìm thấy']);
            return;
        }

        Response::view('projects/form', [
            'pageId'    => 'project-form',
            'pageTitle' => 'Chỉnh sửa Project: ' . $project['name'],
            'project'   => $project,
            'oldInput'  => [],
            'errors'    => [],
        ]);
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    /**
     * Xử lý tạo Project mới.
     *
     * POST /projects
     * Form phải có CSRF hidden input (ViewLayer Guide Phần 8.2).
     *
     * @return void
     */
    public function store(): void
    {
        // --- 1. CSRF Validation (bắt buộc mọi POST – TDD Phần 4.7 mục 5) ---
        if (!Csrf::validateToken($this->request->post('csrf_token') ?? '')) {
            Response::json(['success' => false, 'message' => 'CSRF token không hợp lệ.'], 403);
            return;
        }

        $userId      = (int) Session::get('user_id');
        $workspaceId = (int) Session::get('active_workspace_id');

        // --- 2. Kiểm tra quyền (SRS Phần 1.3: chỉ Owner/Admin tạo Project) ---
        if (!$this->rbacService->canManageProject($userId, $workspaceId)) {
            Response::setFlash('error', 'Bạn không có quyền tạo Project.');
            Response::redirect('/projects');
            return;
        }

        // --- 3. Lấy input qua Request instance (KHÔNG dùng $_POST trực tiếp) ---
        $data = [
            'name'        => $this->request->post('name')        ?? '',
            'key'         => $this->request->post('key')         ?? '',
            'description' => $this->request->post('description') ?? '',
            'color'       => $this->request->post('color')       ?? '#2E86AB',
        ];

        // --- 4. Gọi Service – logic nghiệp vụ nằm hoàn toàn trong Service ---
        $this->projectService->createProject($workspaceId, $userId, $data);

        Response::setFlash('success', 'Project đã được tạo thành công!');
        Response::redirect('/projects');
    }

    /**
     * Xử lý cập nhật thông tin Project.
     *
     * PUT /projects/{key}
     *
     * @param  string $key
     * @return void
     */
    public function update(string $key): void
    {
        // --- 1. CSRF ---
        if (!Csrf::validateToken($this->request->post('csrf_token') ?? '')) {
            Response::json(['success' => false, 'message' => 'CSRF token không hợp lệ.'], 403);
            return;
        }

        $userId      = (int) Session::get('user_id');
        $workspaceId = (int) Session::get('active_workspace_id');

        // --- 2. Quyền ---
        if (!$this->rbacService->canManageProject($userId, $workspaceId)) {
            Response::setFlash('error', 'Bạn không có quyền sửa Project.');
            Response::redirect('/projects');
            return;
        }

        // --- 3. Tìm Project – phải có workspace_id để tránh sửa nhầm WS khác ---
        $project = $this->projectModel->findByKey($key, $workspaceId);
        if (!$project) {
            Response::view('errors/404', ['pageId' => 'error', 'pageTitle' => 'Không tìm thấy']);
            return;
        }

        // --- 4. Input – key KHÔNG cho phép sửa sau khi tạo (SRS UC-015) ---
        $data = [
            'name'        => $this->request->post('name')        ?? '',
            'description' => $this->request->post('description') ?? '',
            'color'       => $this->request->post('color')       ?? '#2E86AB',
        ];

        $this->projectModel->update((int) $project['id'], $data);

        Response::setFlash('success', 'Cập nhật Project thành công!');
        Response::redirect('/projects/' . $key);
    }

    /**
     * Archive một Project (chuyển status → archived, Issue thành read-only).
     *
     * POST /projects/{key}/archive
     * WHY POST thay vì DELETE: Archive không xóa data, chỉ đổi trạng thái.
     * DELETE ngụ ý xóa vĩnh viễn – gây nhầm lẫn về ý nghĩa (SRS Phần 3.2.2).
     *
     * @param  string $key
     * @return void
     */
    public function archive(string $key): void
    {
        // --- 1. CSRF ---
        if (!Csrf::validateToken($this->request->post('csrf_token') ?? '')) {
            Response::json(['success' => false, 'message' => 'CSRF token không hợp lệ.'], 403);
            return;
        }

        $userId      = (int) Session::get('user_id');
        $workspaceId = (int) Session::get('active_workspace_id');

        // --- 2. Quyền ---
        if (!$this->rbacService->canManageProject($userId, $workspaceId)) {
            Response::setFlash('error', 'Bạn không có quyền archive Project.');
            Response::redirect('/projects');
            return;
        }

        // --- 3. Tìm Project trong đúng Workspace ---
        $project = $this->projectModel->findByKey($key, $workspaceId);
        if (!$project) {
            Response::view('errors/404', ['pageId' => 'error', 'pageTitle' => 'Không tìm thấy']);
            return;
        }

        // --- 4. Delegate sang Service – Service set status=archived + ghi ActivityLog ---
        $this->projectService->archiveProject((int) $project['id'], $workspaceId);

        Response::setFlash('success', 'Project đã được archive. Các Issue hiện ở chế độ chỉ đọc.');
        Response::redirect('/projects');
    }
}