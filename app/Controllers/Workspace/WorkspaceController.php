<?php

declare(strict_types=1);

namespace App\Controllers\Workspace;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Helpers\Sanitizer;
use App\Services\WorkspaceService;
use App\Services\RbacService;
use App\Models\Workspace;

/**
 * WorkspaceController
 *
 * Xử lý CRUD Workspace và chức năng chuyển đổi Workspace.
 *
 * Routes (routes.php):
 *   GET    /workspace/create          → create()
 *   POST   /workspace/create          → store()
 *   GET    /workspace/settings        → edit()   [dùng active_workspace_id từ session]
 *   PUT    /workspace/settings        → update()
 *   DELETE /workspace/delete          → destroy()
 *   POST   /workspace/switch/{slug}   → switchTo()
 *
 * @package App\Controllers\Workspace
 * @version 1.0.0
 * @see     SRS v1.0.0 – UC-007, UC-010, UC-011, UC-012, UC-013
 * @see     Task Assignment v1.0.0 – D2-007
 */
class WorkspaceController
{
    private WorkspaceService $workspace_service;
    private RbacService      $rbac_service;
    private Workspace        $workspace_model;

    public function __construct()
    {
        $this->workspace_service = new WorkspaceService();
        $this->rbac_service      = new RbacService();
        $this->workspace_model   = new Workspace();
    }

    /**
     * Hiển thị form tạo Workspace mới.
     * GET /workspace/create
     *
     * @param  Request $request  Inject từ Router.
     * @return void
     */
    public function create(Request $request): void
    {
        Response::view('workspace/create', [
            'pageTitle' => 'Tạo Workspace mới',
            'csrfToken' => Csrf::generateToken(),
        ]);
    }

    /**
     * Xử lý tạo Workspace mới.
     * POST /workspace/create
     *
     * @param  Request $request
     * @return void
     */
    public function store(Request $request): void
    {
        // Validate CSRF — bắt buộc cho mọi POST (TDD Phần 4.7)
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $user_id = Session::getUserId();

        // Lấy data từ Request instance — KHÔNG gọi Request::post() kiểu static
        $name        = trim($request->post('name', ''));
        $slug        = trim($request->post('slug', ''));
        $description = trim($request->post('description', ''));

        // Validate tối thiểu: tên workspace bắt buộc
        if (empty($name)) {
            Response::setFlash('error', 'Tên Workspace không được để trống.');
            Response::redirect('/workspace/create');
        }

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
        ];

        $workspace_id = $this->workspace_service->createWorkspace($user_id, $data);

        // Cập nhật active workspace trong session
        Session::setActiveWorkspace($workspace_id);

        Response::setFlash('success', 'Workspace đã được tạo thành công!');
        Response::redirect('/dashboard');
    }

    /**
     * Hiển thị form chỉnh sửa Workspace.
     * GET /workspace/settings
     * Dùng active_workspace_id từ session — không cần slug trong URL.
     *
     * @param  Request $request
     * @return void
     */
    public function edit(Request $request): void
    {
        $workspace_id = Session::getActiveWorkspaceId();
        $user_id      = Session::getUserId();

        $workspace = $this->workspace_model->findById($workspace_id);
        if (!$workspace) {
            Response::redirect('/404');
        }

        if (!$this->rbac_service->canManageProject($user_id, $workspace_id)) {
            Response::redirect('/403');
        }

        Response::view('workspace/settings', [
            'pageTitle'  => 'Cài đặt Workspace',
            'workspace'  => $workspace,
            'csrfToken'  => Csrf::generateToken(),
        ]);
    }

    /**
     * Xử lý cập nhật thông tin Workspace.
     * PUT /workspace/settings
     *
     * @param  Request $request
     * @return void
     */
    public function update(Request $request): void
    {
        // Validate CSRF
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $workspace_id = Session::getActiveWorkspaceId();
        $user_id      = Session::getUserId();

        $workspace = $this->workspace_model->findById($workspace_id);
        if (!$workspace) {
            Response::redirect('/404');
        }

        if (!$this->rbac_service->canManageProject($user_id, $workspace_id)) {
            Response::setFlash('error', 'Bạn không có quyền thực hiện thao tác này.');
            Response::redirect('/workspace/settings');
        }

        // Lấy data từ Request instance — KHÔNG gọi Request::post() kiểu static
        $data = [
            'name'        => trim($request->post('name', '')),
            'description' => trim($request->post('description', '')),
        ];

        // Validate tên bắt buộc
        if (empty($data['name'])) {
            Response::setFlash('error', 'Tên Workspace không được để trống.');
            Response::redirect('/workspace/settings');
        }

        $this->workspace_service->updateWorkspace($workspace_id, $data);

        Response::setFlash('success', 'Cập nhật Workspace thành công!');
        Response::redirect('/workspace/settings');
    }

    /**
     * Xóa Workspace (chỉ Owner).
     * DELETE /workspace/delete
     *
     * @param  Request $request
     * @return void
     */
    public function destroy(Request $request): void
    {
        // Validate CSRF
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $workspace_id = Session::getActiveWorkspaceId();
        $user_id      = Session::getUserId();

        $workspace = $this->workspace_model->findById($workspace_id);
        if (!$workspace) {
            Response::redirect('/404');
        }

        if (!$this->rbac_service->canDeleteWorkspace($user_id, $workspace_id)) {
            Response::setFlash('error', 'Chỉ Owner mới có thể xóa Workspace.');
            Response::redirect('/workspace/settings');
        }

        $this->workspace_service->deleteWorkspace($workspace_id);

        // Xóa active_workspace_id khỏi session — WorkspaceMiddleware sẽ
        // redirect về onboarding ở request tiếp theo nếu còn sót
        Session::remove('active_workspace_id');
        Session::remove('onboarding_completed');
        Session::remove('current_role');

        Response::setFlash('success', 'Workspace đã được xóa.');
        Response::redirect('/onboarding');
    }

    /**
     * Chuyển đổi sang Workspace khác (Workspace Switcher).
     * POST /workspace/switch/{slug}
     *
     * @param  Request $request
     * @param  string  $slug    Slug của workspace muốn chuyển sang.
     * @return void
     */
    public function switchTo(Request $request, string $slug): void
    {
        // Validate CSRF — POST request thay đổi session state
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $user_id = Session::getUserId();

        $workspace = $this->workspace_model->findBySlug($slug);
        if (!$workspace) {
            Response::setFlash('error', 'Workspace không tồn tại.');
            Response::redirect('/dashboard');
        }

        // WorkspaceService::switchWorkspace() validate user có phải member không
        // trước khi set session — chống Horizontal Privilege Escalation (TDD Phần 2.4)
        $success = $this->workspace_service->switchWorkspace($user_id, (int) $workspace['id']);

        if (!$success) {
            Response::setFlash('error', 'Bạn không phải thành viên của Workspace này.');
            Response::redirect('/dashboard');
        }

        Response::redirect('/dashboard');
    }
}