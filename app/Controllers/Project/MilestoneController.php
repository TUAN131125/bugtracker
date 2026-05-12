<?php

namespace App\Controllers\Project;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Milestone;
use App\Models\Project;

class MilestoneController
{
    private Milestone $milestoneModel;
    private Project $projectModel;

    public function __construct()
    {
        $this->milestoneModel = new Milestone();
        $this->projectModel   = new Project();
    }

    public function index(string $projectKey): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $project     = $this->projectModel->findByKey($projectKey, $workspaceId);

        if (!$project) {
            Response::redirect('/404');
        }

        $milestones = $this->milestoneModel->listByProject($project['id']);

        Response::view('projects/milestones', [
            'project'    => $project,
            'milestones' => $milestones,
        ]);
    }

    public function store(string $projectKey): void
    {
        $workspaceId = Session::get('active_workspace_id');
        $project     = $this->projectModel->findByKey($projectKey, $workspaceId);

        if (!$project) {
            Response::redirect('/404');
        }

        $data = [
            'workspace_id' => $workspaceId,
            'name'         => Request::post('name'),
            'description'  => Request::post('description'),
            'start_date'   => Request::post('start_date') ?: null,
            'due_date'     => Request::post('due_date') ?: null,
        ];

        $this->milestoneModel->create($project['id'], $data);

        Response::setFlash('success', 'Milestone đã được tạo!');
        Response::redirect('/projects/' . $projectKey . '/milestones');
    }

    public function update(int $id): void
    {
        $data = [
            'name'        => Request::post('name'),
            'description' => Request::post('description'),
            'start_date'  => Request::post('start_date') ?: null,
            'due_date'    => Request::post('due_date') ?: null,
            'status'      => Request::post('status') ?? 'open',
        ];

        $this->milestoneModel->update($id, $data);

        Response::json(['success' => true, 'message' => 'Milestone đã được cập nhật.']);
    }

    public function delete(int $id): void
    {
        $this->milestoneModel->delete($id);
        Response::json(['success' => true, 'message' => 'Milestone đã được xóa.']);
    }
}