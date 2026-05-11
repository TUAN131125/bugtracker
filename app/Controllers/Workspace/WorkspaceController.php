<?php

namespace App\Controllers\Workspace;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\WorkspaceService;
use App\Services\RbacService;
use App\Models\Workspace;
use App\Helpers\Sanitizer;

class WorkspaceController
{
    private WorkspaceService $workspaceService;
    private RbacService $rbacService;
    private Workspace $workspaceModel;

    public function __construct()
    {
        $this->workspaceService = new WorkspaceService();
        $this->rbacService      = new RbacService();
        $this->workspaceModel   = new Workspace();
    }

    // Hiển thị form tạo workspace
    public function create(): void
    {
        Response::view('workspace/create');
    }

    // Xử lý tạo workspace mới
    public function store(): void
    {
        $userId = Session::get('user_id');

        $data = [
            'name'        => Request::post('name'),
            'slug'        => Request::post('slug'),
            'description' => Request::post('description'),
        ];

        $workspaceId = $this->workspaceService->createWorkspace($userId, $data);

        Session::set('active_workspace_id', $workspaceId);
        Response::setFlash('success', 'Workspace đã được tạo thành công!');
        Response::redirect('/dashboard');
    }

    // Hiển thị form chỉnh sửa workspace
    public function edit(string $slug): void
    {
        $workspace = $this->workspaceModel->findBySlug($slug);
        if (!$workspace) {
            Response::redirect('/404');
        }

        $userId = Session::get('user_id');
        if (!$this->rbacService->canManageProject($userId, $workspace['id'])) {
            Response::redirect('/403');
        }

        Response::view('workspace/settings', ['workspace' => $workspace]);
    }

    // Xử lý cập nhật workspace
    public function update(string $slug): void
    {
        $workspace = $this->workspaceModel->findBySlug($slug);
        if (!$workspace) {
            Response::redirect('/404');
        }

        $userId = Session::get('user_id');
        if (!$this->rbacService->canManageProject($userId, $workspace['id'])) {
            Response::setFlash('error', 'Bạn không có quyền thực hiện thao tác này.');
            Response::redirect("/workspace/{$slug}/settings");
        }

        $data = [
            'name'        => Request::post('name'),
            'description' => Request::post('description'),
        ];

        $this->workspaceService->updateWorkspace($workspace['id'], $data);

        Response::setFlash('success', 'Cập nhật workspace thành công!');
        Response::redirect("/workspace/{$slug}/settings");
    }

    // Xóa workspace
    public function delete(string $slug): void
    {
        $workspace = $this->workspaceModel->findBySlug($slug);
        if (!$workspace) {
            Response::redirect('/404');
        }

        $userId = Session::get('user_id');
        if (!$this->rbacService->canDeleteWorkspace($userId, $workspace['id'])) {
            Response::setFlash('error', 'Chỉ Owner mới có thể xóa workspace.');
            Response::redirect("/workspace/{$slug}/settings");
        }

        $this->workspaceService->deleteWorkspace($workspace['id']);

        Session::remove('active_workspace_id');
        Response::setFlash('success', 'Workspace đã được xóa.');
        Response::redirect('/onboarding');
    }

    // Chuyển đổi workspace
    public function switchTo(string $slug): void
    {
        $workspace = $this->workspaceModel->findBySlug($slug);
        if (!$workspace) {
            Response::redirect('/404');
        }

        $userId = Session::get('user_id');
        $success = $this->workspaceService->switchWorkspace($userId, $workspace['id']);

        if (!$success) {
            Response::setFlash('error', 'Bạn không phải thành viên của workspace này.');
            Response::redirect('/dashboard');
        }

        Response::redirect('/dashboard');
    }
}