<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Project Model
 *
 * Chứa toàn bộ query liên quan đến bảng projects.
 * Mọi query đều kèm workspace_id để đảm bảo data isolation
 * trong kiến trúc multi-tenant (TDD Phần 2.1).
 *
 * KHÔNG chứa business logic – chỉ chứa query thuần túy.
 * Business logic nằm trong ProjectService (D2-010).
 *
 * @package App\Models
 * @see     TDD Backend v1.0.0 – Phần 2.2.3 (Schema projects)
 * @see     Task Assignment v1.0.0 – D2-009
 */
class Project
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    /**
     * Tạo Project mới trong Workspace.
     *
     * @param  int                  $workspaceId
     * @param  array<string, mixed> $data  Gồm: name, key, description, color, created_by.
     * @return int  ID của Project vừa tạo.
     */
    public function create(int $workspaceId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO projects
                 (workspace_id, name, key, description, color,
                  status, last_issue_number, created_by, created_at, updated_at)
             VALUES
                 (:workspace_id, :name, :key, :description, :color,
                  :status, 0, :created_by, NOW(), NOW())'
        );

        $stmt->execute([
            ':workspace_id' => $workspaceId,
            ':name'         => $data['name'],
            ':key'          => strtoupper($data['key']),
            ':description'  => $data['description'] ?? null,
            ':color'        => $data['color']        ?? '#2563EB',
            ':status'       => $data['status']       ?? 'active',
            ':created_by'   => $data['created_by'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Tìm Project theo ID trong phạm vi Workspace.
     *
     * WHY luôn kèm workspaceId: Chống IDOR – đảm bảo chỉ truy cập
     * Project thuộc Workspace hiện tại (TDD Phần 2.1 – data isolation).
     *
     * @param  int                       $id
     * @param  int                       $workspaceId
     * @return array<string, mixed>|null  null nếu không tìm thấy.
     */
    public function findById(int $id, int $workspaceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.name AS creator_name
             FROM projects p
             LEFT JOIN users u ON u.id = p.created_by
             WHERE p.id           = :id
               AND p.workspace_id = :workspace_id
               AND p.deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            ':id'           => $id,
            ':workspace_id' => $workspaceId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Tìm Project theo Project Key trong Workspace.
     * Dùng cho URL routing (/projects/{key}).
     *
     * @param  string                    $key          Ví dụ: 'BT', 'SHOP'.
     * @param  int                       $workspaceId
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key, int $workspaceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM projects
             WHERE key          = :key
               AND workspace_id = :workspace_id
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            ':key'          => strtoupper($key),
            ':workspace_id' => $workspaceId,
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lấy danh sách Project trong Workspace kèm thống kê Issue.
     * Dùng để render Project card trên trang danh sách.
     *
     * @param  int  $workspaceId
     * @param  bool $includeArchived  Mặc định false – chỉ lấy active.
     * @return array<int, array<string, mixed>>
     */
    public function listByWorkspace(int $workspaceId, bool $includeArchived = false): array
    {
        // WHY build điều kiện status động thay vì 2 query riêng:
        // Tránh lặp code, dễ bảo trì khi thêm status mới.
        $statusCondition = $includeArchived ? '' : "AND p.status = 'active'";

        $stmt = $this->db->prepare(
            "SELECT
                 p.*,
                 u.name AS creator_name,
                 COUNT(CASE WHEN i.status NOT IN ('closed','wont_fix','duplicate') THEN 1 END) AS open_issues,
                 COUNT(CASE WHEN i.status IN ('closed','wont_fix','duplicate')     THEN 1 END) AS closed_issues,
                 COUNT(i.id) AS total_issues
             FROM projects p
             LEFT JOIN users  u ON u.id = p.created_by
             LEFT JOIN issues i ON i.project_id = p.id AND i.deleted_at IS NULL
             WHERE p.workspace_id = :workspace_id
               AND p.deleted_at IS NULL
               {$statusCondition}
             GROUP BY p.id
             ORDER BY p.created_at DESC"
        );

        $stmt->execute([':workspace_id' => $workspaceId]);
        return $stmt->fetchAll();
    }

    /**
     * Kiểm tra Project Key đã tồn tại trong Workspace chưa.
     *
     * Được gọi từ ProjectService::createProject() trước khi INSERT
     * để trả về lỗi nghiệp vụ thay vì để DB throw UNIQUE constraint.
     *
     * WHY tách thành method riêng thay vì catch SQLSTATE[23000]:
     * Catch constraint exception để báo lỗi nghiệp vụ là anti-pattern.
     * DB exception nên dùng cho lỗi kỹ thuật, không phải validation.
     *
     * @param  string $key          Đã uppercase từ caller (ProjectService).
     * @param  int    $workspaceId
     * @param  int    $excludeId    Khi update: loại trừ chính project đó khỏi check.
     * @return bool   true nếu key đã bị dùng (trùng).
     */
    public function keyExistsInWorkspace(
        string $key,
        int    $workspaceId,
        int    $excludeId = 0
    ): bool {
        // WHY build điều kiện exclude động: Tái sử dụng method này
        // cho cả create (excludeId=0) lẫn update (excludeId=projectId) sau này.
        $excludeCondition = $excludeId > 0 ? 'AND id != :exclude_id' : '';

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt
             FROM projects
             WHERE key          = :key
               AND workspace_id = :workspace_id
               AND deleted_at IS NULL
               {$excludeCondition}"
        );

        $params = [
            ':key'          => $key,
            ':workspace_id' => $workspaceId,
        ];

        if ($excludeId > 0) {
            $params[':exclude_id'] = $excludeId;
        }

        $stmt->execute($params);
        $row = $stmt->fetch();

        return ((int) ($row['cnt'] ?? 0)) > 0;
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    /**
     * Cập nhật thông tin Project.
     *
     * Project Key KHÔNG được phép thay đổi sau khi tạo vì ảnh hưởng
     * đến Issue ID (BT-001 sẽ mất tham chiếu nếu key thay đổi).
     * Dùng whitelist để chặn mass assignment.
     *
     * @param  int                  $id
     * @param  array<string, mixed> $data  Field cho phép: name, description, color.
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['name', 'description', 'color'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]              = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = 'UPDATE projects SET '
             . implode(', ', $sets)
             . ', updated_at = NOW() WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Chuyển Project sang trạng thái archived (read-only).
     *
     * Sau khi archive, IssueController phải kiểm tra project.status
     * trước khi cho phép tạo Issue mới hoặc đổi trạng thái Issue.
     * (SRS Phần 3.2.2 – Project Archive)
     *
     * @param  int  $id
     * @return bool
     */
    public function archive(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE projects
             SET status = 'archived', updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL"
        );

        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Khôi phục Project từ archived về active.
     *
     * Cặp đôi với archive(). Sau khi unarchive, Issue trong Project
     * trở lại có thể chỉnh sửa và tạo mới bình thường.
     *
     * @param  int  $id
     * @return bool
     */
    public function unarchive(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE projects
             SET status = 'active', updated_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL"
        );

        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    /**
     * Soft delete Project.
     * Ghi deleted_at, không xóa record khỏi DB để giữ audit trail.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE projects
             SET deleted_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // ISSUE SEQUENCE
    // =========================================================================

    /**
     * Lấy số thứ tự Issue kế tiếp trong Project và tăng counter atomic.
     *
     * Dùng UPDATE atomic trên cột last_issue_number thay vì
     * SELECT MAX(issue_number)+1 để tránh race condition khi concurrent requests
     * cùng tạo Issue trong một Project.
     *
     * WHY KHÔNG có transaction nội bộ ở đây:
     * ProjectService::createProject() đã wrap transaction bên ngoài.
     * PDO không hỗ trợ nested transaction thật — gọi beginTransaction()
     * lần 2 sẽ throw exception hoặc tự động commit transaction đang chạy.
     * Câu UPDATE đơn lẻ đã là atomic ở tầng DB, không cần transaction riêng.
     *
     * @param  int $projectId
     * @return int Số thứ tự kế tiếp (bắt đầu từ 1).
     * @throws \RuntimeException Nếu project không tồn tại.
     */
    public function getNextIssueSequence(int $projectId): int
    {
        // Tăng counter và đọc giá trị mới trong một lần round-trip
        $updateStmt = $this->db->prepare(
            'UPDATE projects
             SET last_issue_number = last_issue_number + 1
             WHERE id = :id
               AND deleted_at IS NULL'
        );

        $updateStmt->execute([':id' => $projectId]);

        if ($updateStmt->rowCount() === 0) {
            throw new \RuntimeException(
                "Project ID {$projectId} không tồn tại hoặc đã bị xóa."
            );
        }

        // Đọc giá trị vừa tăng
        $selectStmt = $this->db->prepare(
            'SELECT last_issue_number FROM projects WHERE id = :id LIMIT 1'
        );

        $selectStmt->execute([':id' => $projectId]);
        $row = $selectStmt->fetch();

        return (int) $row['last_issue_number'];
    }
}