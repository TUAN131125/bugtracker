<?php

declare(strict_types=1);

namespace App\Controllers\Workspace;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Helpers\Sanitizer;
use App\Helpers\SlugGenerator;
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
 *   GET    /workspace/settings        → edit()
 *   PUT    /workspace/settings        → update()
 *   DELETE /workspace/delete          → destroy()
 *   POST   /workspace/switch/{slug}   → switchTo()
 *
 * @package App\Controllers\Workspace
 * @version 1.0.1
 * @see     SRS v1.0.0 – UC-007, UC-010, UC-011, UC-012, UC-013
 *
 * CHANGELOG v1.0.1:
 *   - FIX: join() lấy email từ session qua Session::get('email') thay vì 'user_email'
 *     để nhất quán với key mà Session::loginUser() lưu.
 *   - FIX: edit() và update() dùng rbac_service->canManageWorkspace() thay vì
 *     canManageProject() — tên method phản ánh đúng quyền hạn đang kiểm tra.
 *   - IMPROVE: Thêm comment rõ ràng về session key contract với Session::loginUser().
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
     * Hiển thị trang Onboarding cho user mới.
     * GET /onboarding
     */
    public function onboarding(Request $request): void
    {
        Response::view('auth/onboarding', [
            'pageId'            => 'onboarding',
            'pageTitle'         => 'Bắt đầu nào!',
            'csrfToken'         => Csrf::generateToken(),
            'errors'            => Session::get('_validation_errors', []),
            'old_input'         => Response::getOldInput(),
            // Session key 'name' — nhất quán với Session::loginUser(['name' => ...])
            'current_user_name' => Session::get('name', ''),
        ]);

        Session::remove('_validation_errors');
    }

    /**
     * Hiển thị form tạo Workspace mới.
     * GET /workspace/create
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
     */
    public function store(Request $request): void
    {
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $user_id = Session::getUserId();

        $name        = trim($request->post('name', ''));
        $slug        = trim($request->post('slug', ''));
        $description = trim($request->post('description', ''));

        if (empty($name)) {
            Response::setFlash('error', 'Tên Workspace không được để trống.');
            Response::redirect('/onboarding');
            return;
        }

        if (empty($slug)) {
            $slug = SlugGenerator::makeUnique($name, 'workspaces', 'slug');
        }

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
        ];

        try {
            $workspace_id = $this->workspace_service->createWorkspace($user_id, $data);

            Session::setActiveWorkspace($workspace_id);
            Session::set('onboarding_completed', true);

            Response::setFlash('success', 'Workspace đã được tạo thành công!');
            Response::redirect('/dashboard');
        } catch (\Exception $e) {
            error_log('[WorkspaceController::store] ' . $e->getMessage());
            Response::setFlash('error', 'Không thể tạo Workspace. Vui lòng thử lại.');
            Response::redirect('/onboarding');
        }
    }

    /**
     * Xử lý tham gia Workspace bằng mã mời (Token).
     * POST /workspace/join
     *
     * FIX v1.0.1: Lấy email từ Session::get('email') — nhất quán với key
     * mà Session::loginUser(['email' => $user['email']]) lưu vào session.
     * Trước đây dùng 'user_email' → trả về '' → processPendingInvitation() thất bại.
     */
    public function join(Request $request): void
    {
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $inviteCode = trim($request->post('invite_code', ''));
        if (empty($inviteCode)) {
            Response::setFlash('error', 'Vui lòng nhập mã mời.');
            Response::redirect('/onboarding');
            return;
        }

        Session::set('pending_invite_token', $inviteCode);

        $invitationController = new \App\Controllers\Workspace\InvitationController();
        $user_id = Session::getUserId();

        // FIX: Dùng key 'email' — khớp với Session::loginUser(['email' => ...])
        // KHÔNG dùng 'user_email' vì Session::loginUser() không lưu key đó.
        $user_email = (string) Session::get('email', '');

        if (empty($user_email)) {
            // Phòng hờ: session email bị mất (hiếm gặp nhưng cần xử lý)
            error_log('[WorkspaceController::join] Session email missing for user_id=' . $user_id);
            Response::setFlash('error', 'Phiên làm việc không hợp lệ. Vui lòng đăng nhập lại.');
            Session::destroy();
            Response::redirect('/login');
            return;
        }

        $success = $invitationController->processPendingInvitation($user_id, $user_email);

        if (!$success) {
            Response::redirect('/onboarding');
        }
    }

    /**
     * Hiển thị form chỉnh sửa Workspace.
     * GET /workspace/settings
     *
     * FIX v1.0.1: Dùng canManageWorkspace() thay canManageProject() —
     * chỉnh sửa Workspace settings là quyền Workspace-level (Owner/Admin),
     * không phải quyền Project-level.
     */
    public function edit(Request $request): void
    {
        $workspace_id = Session::getActiveWorkspaceId();
        $user_id      = Session::getUserId();

        $workspace = $this->workspace_model->findById($workspace_id);
        if (!$workspace) {
            Response::redirect('/404');
        }

        // FIX: canManageWorkspace() thay vì canManageProject()
        if (!$this->rbac_service->canManageWorkspace($user_id, $workspace_id)) {
            Response::redirect('/403');
        }

        Response::view('workspace/settings', [
            'pageTitle' => 'Cài đặt Workspace',
            'workspace' => $workspace,
            'csrfToken' => Csrf::generateToken(),
        ]);
    }

    /**
     * Xử lý cập nhật thông tin Workspace.
     * PUT /workspace/settings
     */
    public function update(Request $request): void
    {
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $workspace_id = Session::getActiveWorkspaceId();
        $user_id      = Session::getUserId();

        $workspace = $this->workspace_model->findById($workspace_id);
        if (!$workspace) {
            Response::redirect('/404');
        }

        // FIX: canManageWorkspace() thay vì canManageProject()
        if (!$this->rbac_service->canManageWorkspace($user_id, $workspace_id)) {
            Response::setFlash('error', 'Bạn không có quyền thực hiện thao tác này.');
            Response::redirect('/workspace/settings');
        }

        $data = [
            'name'        => trim($request->post('name', '')),
            'description' => trim($request->post('description', '')),
        ];

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
     */
    public function destroy(Request $request): void
    {
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

        // Xóa toàn bộ workspace state khỏi session
        Session::remove('active_workspace_id');
        Session::remove('onboarding_completed');
        Session::remove('current_role');

        Response::setFlash('success', 'Workspace đã được xóa.');
        Response::redirect('/onboarding');
    }

    /**
     * Chuyển đổi sang Workspace khác (Workspace Switcher).
     * POST /workspace/switch/{slug}
     */
    public function switchTo(Request $request, string $slug): void
    {
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $user_id = Session::getUserId();

        $workspace = $this->workspace_model->findBySlug($slug);
        if (!$workspace) {
            Response::setFlash('error', 'Workspace không tồn tại.');
            Response::redirect('/dashboard');
        }

        // switchWorkspace() validate user có phải member không
        // trước khi set session — chống Horizontal Privilege Escalation (TDD Phần 2.4)
        $success = $this->workspace_service->switchWorkspace($user_id, (int) $workspace['id']);

        if (!$success) {
            Response::setFlash('error', 'Bạn không phải thành viên của Workspace này.');
            Response::redirect('/dashboard');
        }

        Response::redirect('/dashboard');
    }
}