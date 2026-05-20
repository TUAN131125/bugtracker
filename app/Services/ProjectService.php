<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Core\Database;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Helpers\SlugGenerator;

/**
 * ProjectService
 *
 * Tầng Service chứa toàn bộ business logic liên quan đến Project.
 * Controller Project chỉ được gọi Service này, KHÔNG viết logic trực tiếp.
 *
 * Trách nhiệm:
 *  - createProject()     : tạo Project mới + gán thành viên
 *  - updateProject()     : cập nhật thông tin Project
 *  - archiveProject()    : chuyển Project sang trạng thái archived (read-only)
 *  - unarchiveProject()  : khôi phục Project về trạng thái active
 *  - deleteProject()     : soft delete Project
 *  - getProjectStats()   : thống kê số Issue theo status trong Project
 *  - syncMembers()       : đồng bộ danh sách thành viên của Project
 *
 * @package App\Services
 * @version 1.0.0
 * @see     SRS v1.0.0 – UC-015, UC-016, UC-017
 * @see     TDD Backend v1.0.0 – Phần 2.2.3 (Schema projects, project_members)
 * @see     Task Assignment v1.0.0 – D2-009, D2-010, D2-011
 */
class ProjectService
{
    private Project       $project_model;
    private PDO           $db;

    public function __construct()
    {
        $this->project_model = new Project();
        $this->db            = Database::getInstance();
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    /**
     * Tạo Project mới trong Workspace.
     *
     * Luồng theo SRS UC-015:
     *   1. Validate tên và Project Key (2-6 ký tự A-Z, unique per workspace)
     *   2. Insert bảng projects
     *   3. Gán thành viên vào project_members
     *   4. Ghi ActivityLog
     *
     * @param  int                  $workspace_id
     * @param  int                  $created_by    User ID người tạo.
     * @param  array<string, mixed> $data          Gồm: name, key, description, color, member_ids[].
     * @return array{success: bool, errors: array, project_id: int|null}
     */
    public function createProject(int $workspace_id, int $created_by, array $data): array
    {
        // --- Validate ---
        $errors = $this->validateProjectData($data, $workspace_id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'project_id' => null];
        }

        // Normalize key: uppercase, trim
        $key = strtoupper(trim($data['key']));

        // Kiểm tra Project Key unique trong workspace
        if ($this->project_model->keyExistsInWorkspace($key, $workspace_id)) {
            return [
                'success'    => false,
                'errors'     => ['key' => "Project Key '{$key}' đã được sử dụng trong Workspace này."],
                'project_id' => null,
            ];
        }

        // --- Insert project trong transaction ---
        $this->db->beginTransaction();

        try {
            $project_id = $this->project_model->create($workspace_id, [
                'name'        => trim($data['name']),
                'key'         => $key,
                'description' => trim($data['description'] ?? ''),
                'color'       => $data['color'] ?? '#2563EB',
                'status'      => 'active',
                'created_by'  => $created_by,
            ]);

            // Gán thành viên được chọn vào project_members
            // created_by luôn được thêm vào dù không có trong member_ids
            $member_ids = $data['member_ids'] ?? [];
            if (!in_array($created_by, $member_ids, true)) {
                $member_ids[] = $created_by;
            }

            $this->syncMembers($project_id, $member_ids);

            // Ghi Activity Log
            $this->logActivity(
                $workspace_id,
                $created_by,
                'project',
                $project_id,
                'project_created',
                ['project_name' => trim($data['name']), 'project_key' => $key]
            );

            $this->db->commit();

        } catch (\PDOException $e) {
            $this->db->rollBack();

            // TODO: Replace bằng Logger instance sau khi D1-021 hoàn thành (Ngày 3)
            error_log(sprintf(
                '[ProjectService::createProject] DB error | Workspace: %d | Error: %s',
                $workspace_id,
                $e->getMessage()
            ));

            return ['success' => false, 'errors' => ['general' => 'Tạo Project thất bại. Vui lòng thử lại.'], 'project_id' => null];
        }

        return ['success' => true, 'errors' => [], 'project_id' => $project_id];
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    /**
     * Cập nhật thông tin Project.
     *
     * Project Key KHÔNG được phép thay đổi sau khi tạo vì ảnh hưởng
     * đến Issue ID (BT-001 sẽ bị mất tham chiếu nếu key thay đổi).
     *
     * @param  int                  $project_id
     * @param  int                  $workspace_id   Dùng để đảm bảo ownership.
     * @param  int                  $updated_by
     * @param  array<string, mixed> $data           Gồm: name, description, color, member_ids[].
     * @return array{success: bool, errors: array}
     */
    public function updateProject(
        int   $project_id,
        int   $workspace_id,
        int   $updated_by,
        array $data
    ): array {
        // Verify project tồn tại và thuộc workspace — chống IDOR
        $project = $this->project_model->findById($project_id, $workspace_id);
        if (!$project) {
            return ['success' => false, 'errors' => ['general' => 'Project không tồn tại.']];
        }

        // Project archived không thể chỉnh sửa
        if ($project['status'] === 'archived') {
            return ['success' => false, 'errors' => ['general' => 'Project đã bị archived. Không thể chỉnh sửa.']];
        }

        // Validate tên
        if (empty(trim($data['name'] ?? ''))) {
            return ['success' => false, 'errors' => ['name' => 'Tên Project không được để trống.']];
        }

        $this->db->beginTransaction();

        try {
            $this->project_model->update($project_id, [
                'name'        => trim($data['name']),
                'description' => trim($data['description'] ?? ''),
                'color'       => $data['color'] ?? $project['color'],
            ]);

            // Đồng bộ thành viên nếu member_ids được cung cấp
            if (isset($data['member_ids']) && is_array($data['member_ids'])) {
                $this->syncMembers($project_id, $data['member_ids']);
            }

            $this->logActivity(
                $workspace_id,
                $updated_by,
                'project',
                $project_id,
                'project_updated',
                ['project_name' => trim($data['name']), 'project_key' => $project['key']]
            );

            $this->db->commit();

        } catch (\PDOException $e) {
            $this->db->rollBack();
            error_log(sprintf(
                '[ProjectService::updateProject] DB error | Project: %d | Error: %s',
                $project_id,
                $e->getMessage()
            ));

            return ['success' => false, 'errors' => ['general' => 'Cập nhật Project thất bại. Vui lòng thử lại.']];
        }

        return ['success' => true, 'errors' => []];
    }

    // =========================================================================
    // ARCHIVE / UNARCHIVE
    // =========================================================================

    /**
     * Archive Project — chuyển sang trạng thái read-only.
     *
     * Theo SRS Phần 3.2.2: khi Archive, toàn bộ Issue chuyển read-only.
     * Không thể tạo Issue mới trong Project đã Archive.
     * IssueController phải kiểm tra project.status trước khi cho phép action.
     *
     * @param  int $project_id
     * @param  int $workspace_id
     * @param  int $archived_by
     * @return array{success: bool, errors: array}
     */
    public function archiveProject(int $project_id, int $workspace_id, int $archived_by): array
    {
        $project = $this->project_model->findById($project_id, $workspace_id);
        if (!$project) {
            return ['success' => false, 'errors' => ['general' => 'Project không tồn tại.']];
        }

        if ($project['status'] === 'archived') {
            return ['success' => false, 'errors' => ['general' => 'Project đã ở trạng thái archived.']];
        }

        $this->project_model->archive($project_id);

        $this->logActivity(
            $workspace_id,
            $archived_by,
            'project',
            $project_id,
            'project_archived',
            ['project_name' => $project['name']]
        );

        return ['success' => true, 'errors' => []];
    }

    /**
     * Khôi phục Project từ archived về active.
     *
     * @param  int $project_id
     * @param  int $workspace_id
     * @param  int $restored_by
     * @return array{success: bool, errors: array}
     */
    public function unarchiveProject(int $project_id, int $workspace_id, int $restored_by): array
    {
        $project = $this->project_model->findById($project_id, $workspace_id);
        if (!$project) {
            return ['success' => false, 'errors' => ['general' => 'Project không tồn tại.']];
        }

        if ($project['status'] === 'active') {
            return ['success' => false, 'errors' => ['general' => 'Project đang ở trạng thái active.']];
        }

        $this->project_model->unarchive($project_id);

        $this->logActivity(
            $workspace_id,
            $restored_by,
            'project',
            $project_id,
            'project_unarchived',
            ['project_name' => $project['name']]
        );

        return ['success' => true, 'errors' => []];
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    /**
     * Soft delete Project.
     *
     * @param  int $project_id
     * @param  int $workspace_id
     * @param  int $deleted_by
     * @return array{success: bool, errors: array}
     */
    public function deleteProject(int $project_id, int $workspace_id, int $deleted_by): array
    {
        $project = $this->project_model->findById($project_id, $workspace_id);
        if (!$project) {
            return ['success' => false, 'errors' => ['general' => 'Project không tồn tại.']];
        }

        $this->project_model->delete($project_id);

        $this->logActivity(
            $workspace_id,
            $deleted_by,
            'project',
            $project_id,
            'project_deleted',
            ['project_name' => $project['name'], 'project_key' => $project['key']]
        );

        return ['success' => true, 'errors' => []];
    }

    // =========================================================================
    // STATS
    // =========================================================================

    /**
     * Thống kê số Issue theo status trong Project.
     * Dùng cho Project card trên danh sách (progress bar %).
     *
     * @param  int   $project_id
     * @param  int   $workspace_id
     * @return array{total: int, open: int, in_progress: int, closed: int, by_status: array}
     */
    public function getProjectStats(int $project_id, int $workspace_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) AS count
             FROM issues
             WHERE project_id   = :project_id
               AND workspace_id = :workspace_id
               AND deleted_at IS NULL
             GROUP BY status'
        );
        $stmt->execute([
            ':project_id'   => $project_id,
            ':workspace_id' => $workspace_id,
        ]);
        $rows = $stmt->fetchAll();

        // Tổng hợp theo nhóm
        $by_status  = [];
        $total      = 0;
        $open       = 0;
        $in_progress = 0;
        $closed     = 0;

        foreach ($rows as $row) {
            $by_status[$row['status']] = (int) $row['count'];
            $total += (int) $row['count'];

            if (in_array($row['status'], ['open', 'in_triage', 'reopened'], true)) {
                $open += (int) $row['count'];
            } elseif ($row['status'] === 'in_progress') {
                $in_progress += (int) $row['count'];
            } elseif (in_array($row['status'], ['closed', 'resolved', 'wont_fix', 'duplicate'], true)) {
                $closed += (int) $row['count'];
            }
        }

        return [
            'total'       => $total,
            'open'        => $open,
            'in_progress' => $in_progress,
            'closed'      => $closed,
            'by_status'   => $by_status,
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Đồng bộ danh sách thành viên của Project.
     * Xóa thành viên cũ không còn trong danh sách, thêm thành viên mới.
     *
     * WHY dùng sync thay vì add/remove riêng lẻ: đảm bảo project_members
     * luôn phản ánh chính xác $member_ids sau mỗi lần update, tránh orphan records.
     *
     * @param  int        $project_id
     * @param  array<int> $member_ids  Danh sách user_id cần có trong project.
     * @return void
     */
    private function syncMembers(int $project_id, array $member_ids): void
    {
        if (empty($member_ids)) {
            return;
        }

        // Xóa tất cả thành viên hiện tại của project
        $delete_stmt = $this->db->prepare(
            'DELETE FROM project_members WHERE project_id = :project_id'
        );
        $delete_stmt->execute([':project_id' => $project_id]);

        // Insert lại danh sách mới
        $insert_stmt = $this->db->prepare(
            'INSERT IGNORE INTO project_members (project_id, user_id, added_at)
             VALUES (:project_id, :user_id, NOW())'
        );

        foreach ($member_ids as $user_id) {
            $insert_stmt->execute([
                ':project_id' => $project_id,
                ':user_id'    => (int) $user_id,
            ]);
        }
    }

    /**
     * Validate dữ liệu Project theo SRS UC-015.
     *
     * @param  array<string, mixed> $data
     * @param  int                  $workspace_id
     * @return array<string, string> Mảng lỗi keyed by field, rỗng nếu hợp lệ.
     */
    private function validateProjectData(array $data, int $workspace_id): array
    {
        $errors = [];

        // Tên bắt buộc
        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = 'Tên Project không được để trống.';
        } elseif (mb_strlen(trim($data['name'])) > 150) {
            $errors['name'] = 'Tên Project không được vượt quá 150 ký tự.';
        }

        // Project Key bắt buộc, 2-6 ký tự A-Z (TDD Phần 2.2.3 + SRS UC-015)
        $key = strtoupper(trim($data['key'] ?? ''));
        if (empty($key)) {
            $errors['key'] = 'Project Key không được để trống.';
        } elseif (!preg_match('/^[A-Z]{2,6}$/', $key)) {
            $errors['key'] = 'Project Key phải có 2–6 ký tự in hoa (A-Z). Ví dụ: BT, SHOP.';
        }

        return $errors;
    }

    /**
     * Ghi Activity Log cho hành động liên quan đến Project.
     * Dùng raw query tạm thời — sẽ replace bằng ActivityLogService khi Dev 2 hoàn thành.
     *
     * @param  int    $workspace_id
     * @param  int    $user_id
     * @param  string $entity_type
     * @param  int    $entity_id
     * @param  string $action_type
     * @param  array<string, mixed> $metadata
     * @return void
     */
    private function logActivity(
        int    $workspace_id,
        int    $user_id,
        string $entity_type,
        int    $entity_id,
        string $action_type,
        array  $metadata = []
    ): void {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO activity_logs
                    (workspace_id, user_id, action_type, entity_type, entity_id, metadata, created_at)
                 VALUES
                    (:workspace_id, :user_id, :action_type, :entity_type, :entity_id, :metadata, NOW())'
            );
            $stmt->execute([
                ':workspace_id' => $workspace_id,
                ':user_id'      => $user_id,
                ':action_type'  => $action_type,
                ':entity_type'  => $entity_type,
                ':entity_id'    => $entity_id,
                ':metadata'     => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\PDOException $e) {
            // Silent fail cho Activity Log — không ảnh hưởng luồng chính
            error_log(sprintf(
                '[ProjectService::logActivity] Failed | Action: %s | Entity: %d | Error: %s',
                $action_type,
                $entity_id,
                $e->getMessage()
            ));
        }
    }
}