<?php

namespace App\Controllers\Project;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Project;
use App\Services\ProjectService;
use App\Services\RbacService;

class ProjectController
{
    private Project $projectModel;
    private ProjectService $projectService;
    private RbacService $rbacService;

    public function __construct()
    {
        $this->projectModel   = new Project();
        $this->projectService = new ProjectService();
        $this->rbacService    = new RbacService();
    }

    public function index(): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $projects    = $this->projectModel->listByWorkspace($workspaceId);

        Response::view('projects/list', ['projects' => $projects]);
    }

    public function create(): void
    {
        Response::view('projects/form', ['project' => null]);
    }

    public function store(): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');

        if (!$this->rbacService->canManageProject($userId, $workspaceId)) {
            Response::setFlash('error', 'Bạn không có quyền tạo Project.');
            Response::redirect('/projects');
        }

        $data = [
            'name'        => Request::post('name'),
            'key'         => Request::post('key'),
            'description' => Request::post('description'),
            'color'       => Request::post('color') ?? '#2E86AB',
        ];

        $this->projectService->createProject($workspaceId, $userId, $data);

        Response::setFlash('success', 'Project đã được tạo thành công!');
        Response::redirect('/projects');
    }

    public function show(string $key): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $project     = $this->projectModel->findByKey($key, $workspaceId);

        if (!$project) {
            Response::redirect('/404');
        }

        $stats = $this->projectService->getProjectStats($project['id'], $workspaceId);

        Response::view('projects/show', [
            'project' => $project,
            'stats'   => $stats,
        ]);
    }

    public function edit(string $key): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $project     = $this->projectModel->findByKey($key, $workspaceId);

        if (!$project) {
            Response::redirect('/404');
        }

        Response::view('projects/form', ['project' => $project]);
    }

    public function update(string $key): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');

        if (!$this->rbacService->canManageProject($userId, $workspaceId)) {
            Response::setFlash('error', 'Bạn không có quyền sửa Project.');
            Response::redirect('/projects');
        }

        $project = $this->projectModel->findByKey($key, $workspaceId);
        if (!$project) {
            Response::redirect('/404');
        }

        $data = [
            'name'        => Request::post('name'),
            'description' => Request::post('description'),
            'color'       => Request::post('color') ?? '#2E86AB',
        ];

        $this->projectModel->update($project['id'], $data);

        Response::setFlash('success', 'Cập nhật Project thành công!');
        Response::redirect('/projects/' . $key);
    }

    public function archive(string $key): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');

        if (!$this->rbacService->canManageProject($userId, $workspaceId)) {
            Response::setFlash('error', 'Bạn không có quyền archive Project.');
            Response::redirect('/projects');
        }

        $project = $this->projectModel->findByKey($key, $workspaceId);
        if (!$project) {
            Response::redirect('/404');
        }

        $this->projectService->archiveProject($project['id']);

        Response::setFlash('success', 'Project đã được archive.');
        Response::redirect('/projects');
    }
}