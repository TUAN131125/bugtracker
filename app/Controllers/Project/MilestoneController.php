<?php

declare(strict_types=1);

namespace App\Controllers\Project;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Csrf;
use App\Models\Milestone;
use App\Models\Project;

/**
 * MilestoneController
 *
 * Xử lý CRUD Milestone trong một Project.
 * Mọi thao tác đều validate workspace isolation để chống IDOR.
 *
 * Routes (đăng ký trong routes.php – Dev 1):
 *   GET    /projects/{key}/milestones          → index()
 *   POST   /projects/{key}/milestones          → store()
 *   PUT    /projects/{key}/milestones/{id}     → update()
 *   DELETE /projects/{key}/milestones/{id}     → destroy()
 *
 * @package App\Controllers\Project
 * @version 1.0.0
 * @see     SRS v1.0.0 – UC-018
 * @see     Task Assignment v1.0.0 – D2-010, D2-011
 */
class MilestoneController
{
    private Milestone $milestone_model;
    private Project   $project_model;

    public function __construct()
    {
        $this->milestone_model = new Milestone();
        $this->project_model   = new Project();
    }

    /**
     * Hiển thị danh sách Milestone của một Project.
     * GET /projects/{key}/milestones
     *
     * @param  Request $request     Inject từ Router.
     * @param  string  $projectKey  VD: 'BT' – từ URL parameter.
     * @return void
     */
    public function index(Request $request, string $projectKey): void
    {
        $workspace_id = Session::getActiveWorkspaceId();
        $project      = $this->project_model->findByKey($projectKey, $workspace_id);

        if (!$project) {
            Response::redirect('/404');
        }

        $milestones = $this->milestone_model->listByProject($project['id']);

        Response::view('projects/milestones', [
            'pageTitle'  => 'Milestones – ' . $project['name'],
            'project'    => $project,
            'milestones' => $milestones,
        ]);
    }

    /**
     * Tạo Milestone mới trong Project.
     * POST /projects/{key}/milestones
     *
     * @param  Request $request
     * @param  string  $projectKey
     * @return void
     */
    public function store(Request $request, string $projectKey): void
    {
        // Validate CSRF – bắt buộc cho mọi POST (TDD Phần 4.7)
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $workspace_id = Session::getActiveWorkspaceId();
        $project      = $this->project_model->findByKey($projectKey, $workspace_id);

        if (!$project) {
            Response::redirect('/404');
        }

        // Lấy data từ Request instance – KHÔNG gọi Request::post() kiểu static
        $data = [
            'workspace_id' => $workspace_id,
            'name'         => $request->post('name', ''),
            'description'  => $request->post('description', ''),
            'start_date'   => $request->post('start_date') ?: null,
            'due_date'     => $request->post('due_date') ?: null,
        ];

        // Validate name bắt buộc
        if (empty(trim((string) $data['name']))) {
            Response::setFlash('error', 'Tên Milestone không được để trống.');
            Response::redirect('/projects/' . $projectKey . '/milestones');
        }

        $this->milestone_model->create($project['id'], $data);

        Response::setFlash('success', 'Milestone đã được tạo!');
        Response::redirect('/projects/' . $projectKey . '/milestones');
    }

    /**
     * Cập nhật thông tin Milestone.
     * PUT /projects/{key}/milestones/{id}
     * AJAX endpoint – trả về JSON.
     *
     * @param  Request $request
     * @param  string  $projectKey
     * @param  int     $id          Milestone ID.
     * @return void
     */
    public function update(Request $request, string $projectKey, int $id): void
    {
        // Validate CSRF
        Csrf::validateOrFail($request->post('csrf_token', ''));

        $workspace_id = Session::getActiveWorkspaceId();

        // IDOR check: verify milestone thuộc workspace đang active
        // Không tin tưởng $id từ URL mà không kiểm tra ownership
        $milestone = $this->milestone_model->findById($id, $workspace_id);

        if (!$milestone) {
            Response::json([
                'success' => false,
                'message' => 'Milestone không tồn tại hoặc bạn không có quyền truy cập.',
            ], 404);
            return;
        }

        // Verify milestone thuộc đúng project trong URL
        $project = $this->project_model->findByKey($projectKey, $workspace_id);

        if (!$project || (int) $milestone['project_id'] !== (int) $project['id']) {
            Response::json([
                'success' => false,
                'message' => 'Milestone không thuộc Project này.',
            ], 403);
            return;
        }

        $data = [
            'name'        => $request->post('name', ''),
            'description' => $request->post('description', ''),
            'start_date'  => $request->post('start_date') ?: null,
            'due_date'    => $request->post('due_date') ?: null,
            'status'      => $request->post('status', 'open'),
        ];

        // Validate name bắt buộc
        if (empty(trim((string) $data['name']))) {
            Response::json([
                'success' => false,
                'message' => 'Tên Milestone không được để trống.',
            ], 422);
            return;
        }

        // Validate status chỉ nhận giá trị hợp lệ theo schema
        $allowed_statuses = ['open', 'closed'];
        if (!in_array($data['status'], $allowed_statuses, true)) {
            $data['status'] = 'open';
        }

        $this->milestone_model->update($id, $data);

        Response::json([
            'success' => true,
            'message' => 'Milestone đã được cập nhật.',
        ]);
    }

    /**
     * Xóa Milestone.
     * DELETE /projects/{key}/milestones/{id}
     * AJAX endpoint – trả về JSON.
     *
     * @param  Request $request
     * @param  string  $projectKey
     * @param  int     $id
     * @return void
     */
    public function destroy(Request $request, string $projectKey, int $id): void
    {
        $workspace_id = Session::getActiveWorkspaceId();

        // IDOR check: verify milestone thuộc workspace đang active
        $milestone = $this->milestone_model->findById($id, $workspace_id);

        if (!$milestone) {
            Response::json([
                'success' => false,
                'message' => 'Milestone không tồn tại hoặc bạn không có quyền truy cập.',
            ], 404);
            return;
        }

        // Verify milestone thuộc đúng project trong URL
        $project = $this->project_model->findByKey($projectKey, $workspace_id);

        if (!$project || (int) $milestone['project_id'] !== (int) $project['id']) {
            Response::json([
                'success' => false,
                'message' => 'Milestone không thuộc Project này.',
            ], 403);
            return;
        }

        $this->milestone_model->delete($id);

        Response::json([
            'success' => true,
            'message' => 'Milestone đã được xóa.',
        ]);
    }
}