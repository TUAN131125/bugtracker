<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use App\Core\Database;
use App\Core\Logger;

/**
 * Attachment Model
 *
 * Chịu trách nhiệm toàn bộ tương tác với bảng `attachments`.
 * Mọi query đều dùng PDO Prepared Statements – TUYỆT ĐỐI không nối chuỗi SQL.
 * Mọi query có điều kiện workspace_id để đảm bảo data isolation (Multi-tenant).
 *
 * Phân biệt 2 loại attachment:
 *   - Attachment của Issue   → issue_id IS NOT NULL, comment_id IS NULL
 *   - Attachment của Comment → comment_id IS NOT NULL, issue_id có thể NULL
 *
 * @author  Dev 2
 * @version 1.0.0
 * @see     TDD Backend v1.0.0 – Phần 2.2.6 (Bảng attachments)
 * @see     SRS v1.0.0 – UC-029 (Upload), UC-030 (Download)
 * @see     Task Assignment v1.0.0 – D1-026 (FileUploadService tích hợp)
 */
class Attachment
{
    private PDO    $db;
    private Logger $logger;

    public function __construct()
    {
        $this->db     = Database::getInstance();
        $this->logger = new Logger();
    }

    // =========================================================================
    // WRITE OPERATIONS
    // =========================================================================

    /**
     * Tạo bản ghi attachment mới trong DB.
     *
     * Được gọi từ AttachmentController::store() SAU KHI FileUploadService::store()
     * đã move file thành công – nằm trong cùng DB transaction.
     *
     * @param  array{
     *   workspace_id:  int,
     *   issue_id:      int|null,
     *   comment_id:    int|null,
     *   uploader_id:   int,
     *   original_name: string,
     *   stored_name:   string,
     *   file_path:     string,
     *   mime_type:     string,
     *   file_size:     int
     * } $data
     * @return int  ID của bản ghi vừa insert
     * @throws \RuntimeException Khi query thất bại
     */
    public function create(array $data): int
    {
        $sql = '
            INSERT INTO attachments
                (workspace_id, issue_id, comment_id, uploader_id,
                 original_name, stored_name, file_path, mime_type, file_size,
                 created_at)
            VALUES
                (:workspace_id, :issue_id, :comment_id, :uploader_id,
                 :original_name, :stored_name, :file_path, :mime_type, :file_size,
                 NOW())
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':workspace_id'  => $data['workspace_id'],
                ':issue_id'      => $data['issue_id'],       // NULL-safe
                ':comment_id'    => $data['comment_id'],     // NULL-safe
                ':uploader_id'   => $data['uploader_id'],
                ':original_name' => $data['original_name'],
                ':stored_name'   => $data['stored_name'],
                ':file_path'     => $data['file_path'],
                ':mime_type'     => $data['mime_type'],
                ':file_size'     => $data['file_size'],
            ]);

            return (int) $this->db->lastInsertId();

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::create thất bại: ' . $e->getMessage(),
                'Attachment',
                "workspace_id={$data['workspace_id']}, stored_name={$data['stored_name']}"
            );
            throw new \RuntimeException('Không thể lưu thông tin file đính kèm vào database.');
        }
    }

    /**
     * Soft-delete attachment: ghi deleted_at = NOW().
     *
     * WHY soft-delete thay vì hard-delete:
     *   - Giữ lại audit trail trong activity_logs (vẫn reference được attachment_id)
     *   - TDD Phần 2.4: file vật lý xóa riêng bởi FileUploadService::delete()
     *   - Cho phép Admin khôi phục nếu xóa nhầm (future feature)
     *
     * Controller phải kiểm tra quyền TRƯỚC khi gọi method này.
     *
     * @param  int  $id  Primary key của attachment
     * @return bool true nếu xóa thành công (rowCount > 0)
     */
    public function softDelete(int $id): bool
    {
        // WHY không có workspace_id ở đây:
        // Controller đã gọi findById($id, $workspaceId) để verify attachment
        // thuộc đúng workspace trước khi gọi softDelete(). Tránh query thừa.
        $sql = '
            UPDATE attachments
            SET    deleted_at = NOW()
            WHERE  id = :id
              AND  deleted_at IS NULL
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::softDelete thất bại: ' . $e->getMessage(),
                'Attachment',
                "id={$id}"
            );
            return false;
        }
    }

    /**
     * Soft-delete toàn bộ attachment của một Issue.
     *
     * Dùng khi xóa Issue (cascade soft-delete) hoặc khi archive Project.
     * File vật lý phải được xóa riêng bởi caller (loop qua listByIssue trước).
     *
     * @param  int $issueId
     * @param  int $workspaceId  Bắt buộc – data isolation
     * @return int Số bản ghi bị soft-delete
     */
    public function softDeleteByIssue(int $issueId, int $workspaceId): int
    {
        $sql = '
            UPDATE attachments
            SET    deleted_at = NOW()
            WHERE  issue_id      = :issue_id
              AND  workspace_id  = :workspace_id
              AND  deleted_at   IS NULL
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':issue_id'     => $issueId,
                ':workspace_id' => $workspaceId,
            ]);

            return $stmt->rowCount();

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::softDeleteByIssue thất bại: ' . $e->getMessage(),
                'Attachment',
                "issue_id={$issueId}, workspace_id={$workspaceId}"
            );
            return 0;
        }
    }

    /**
     * Soft-delete toàn bộ attachment của một Workspace.
     *
     * Dùng khi Owner xóa Workspace (WorkspaceService::delete()).
     * File vật lý xóa riêng – PHP loop + unlink() từng file trước khi gọi method này.
     *
     * @param  int $workspaceId
     * @return int Số bản ghi bị soft-delete
     */
    public function softDeleteByWorkspace(int $workspaceId): int
    {
        $sql = '
            UPDATE attachments
            SET    deleted_at = NOW()
            WHERE  workspace_id = :workspace_id
              AND  deleted_at  IS NULL
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':workspace_id' => $workspaceId]);

            return $stmt->rowCount();

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::softDeleteByWorkspace thất bại: ' . $e->getMessage(),
                'Attachment',
                "workspace_id={$workspaceId}"
            );
            return 0;
        }
    }

    // =========================================================================
    // READ OPERATIONS – Single record
    // =========================================================================

    /**
     * Tìm attachment theo primary key với workspace_id guard.
     *
     * Dùng trong AttachmentController::destroy() để:
     *   1. Xác nhận attachment tồn tại
     *   2. Lấy thông tin (file_path, uploader_id) để kiểm tra quyền và xóa file
     *
     * workspace_id trong WHERE là bắt buộc để chống IDOR
     * (user không thể đoán ID của attachment workspace khác).
     *
     * @param  int $id
     * @param  int $workspaceId
     * @return array<string, mixed>|null  null nếu không tìm thấy hoặc bị xóa
     */
    public function findById(int $id, int $workspaceId): ?array
    {
        $sql = '
            SELECT a.*,
                   u.name AS uploader_name
            FROM   attachments a
            JOIN   users u ON u.id = a.uploader_id
            WHERE  a.id           = :id
              AND  a.workspace_id = :workspace_id
            LIMIT  1
        ';

        // WHY JOIN users: Controller cần uploader_name để hiển thị trong response
        // mà không cần query thêm lần nữa.

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id'           => $id,
                ':workspace_id' => $workspaceId,
            ]);

            $row = $stmt->fetch();
            return $row !== false ? $row : null;

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::findById thất bại: ' . $e->getMessage(),
                'Attachment',
                "id={$id}, workspace_id={$workspaceId}"
            );
            return null;
        }
    }

    /**
     * Tìm attachment theo stored_name (tên file vật lý trên server).
     *
     * Dùng trong AttachmentController::serve() để:
     *   1. Xác nhận file tồn tại và không bị xóa
     *   2. Lấy original_name và mime_type để set header đúng khi stream
     *
     * WHY cần cả workspaceId + issueId:
     *   stored_name được sinh ngẫu nhiên nhưng không phải globally unique
     *   (xác suất collision cực thấp nhưng cần đảm bảo đúng workspace/issue).
     *   Kết hợp 3 điều kiện = tìm chính xác file đó.
     *
     * @param  string $storedName
     * @param  int    $workspaceId
     * @param  int    $issueId
     * @return array<string, mixed>|null
     */
    public function findByStoredName(string $storedName, int $workspaceId, int $issueId): ?array
    {
        $sql = '
            SELECT *
            FROM   attachments
            WHERE  stored_name  = :stored_name
              AND  workspace_id = :workspace_id
              AND  issue_id     = :issue_id
            LIMIT  1
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':stored_name'  => $storedName,
                ':workspace_id' => $workspaceId,
                ':issue_id'     => $issueId,
            ]);

            $row = $stmt->fetch();
            return $row !== false ? $row : null;

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::findByStoredName thất bại: ' . $e->getMessage(),
                'Attachment',
                "stored_name={$storedName}, workspace_id={$workspaceId}"
            );
            return null;
        }
    }

    // =========================================================================
    // READ OPERATIONS – Collections
    // =========================================================================

    /**
     * Lấy danh sách tất cả attachment (chưa xóa) của một Issue.
     *
     * Dùng trong IssueController::show() để render gallery đính kèm.
     * Dev 3 nhận array này và render attachment list trong issue/detail.php.
     *
     * @param  int $issueId
     * @param  int $workspaceId  Bắt buộc – data isolation
     * @return array<int, array<string, mixed>>  Mảng rỗng nếu không có file nào
     */
    public function listByIssue(int $issueId, int $workspaceId): array
    {
        $sql = '
            SELECT a.id,
                   a.original_name,
                   a.stored_name,
                   a.file_path,
                   a.mime_type,
                   a.file_size,
                   a.created_at,
                   u.id   AS uploader_id,
                   u.name AS uploader_name
            FROM   attachments a
            JOIN   users u ON u.id = a.uploader_id
            WHERE  a.issue_id     = :issue_id
              AND  a.workspace_id = :workspace_id
              AND  a.comment_id   IS NULL
              AND  a.deleted_at   IS NULL
            ORDER BY a.created_at ASC
        ';

        // WHY comment_id IS NULL:
        // Lọc chỉ lấy attachment của Issue, không lấy attachment của Comment.
        // Dev 3 render 2 nhóm riêng biệt trong UI.

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':issue_id'     => $issueId,
                ':workspace_id' => $workspaceId,
            ]);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::listByIssue thất bại: ' . $e->getMessage(),
                'Attachment',
                "issue_id={$issueId}, workspace_id={$workspaceId}"
            );
            return [];
        }
    }

    /**
     * Lấy danh sách attachment của một Comment.
     *
     * Dùng trong CommentController khi render comment thread.
     * Phân biệt rõ với listByIssue() để tránh lẫn lộn giữa 2 nhóm.
     *
     * @param  int $commentId
     * @param  int $workspaceId
     * @return array<int, array<string, mixed>>
     */
    public function listByComment(int $commentId, int $workspaceId): array
    {
        $sql = '
            SELECT a.id,
                   a.original_name,
                   a.stored_name,
                   a.file_path,
                   a.mime_type,
                   a.file_size,
                   a.created_at,
                   u.id   AS uploader_id,
                   u.name AS uploader_name
            FROM   attachments a
            JOIN   users u ON u.id = a.uploader_id
            WHERE  a.comment_id   = :comment_id
              AND  a.workspace_id = :workspace_id
              AND  a.deleted_at   IS NULL
            ORDER BY a.created_at ASC
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':comment_id'   => $commentId,
                ':workspace_id' => $workspaceId,
            ]);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::listByComment thất bại: ' . $e->getMessage(),
                'Attachment',
                "comment_id={$commentId}, workspace_id={$workspaceId}"
            );
            return [];
        }
    }

    /**
     * Lấy danh sách tất cả attachment (kể cả đã xóa) của một Workspace.
     *
     * Dùng khi Owner xóa Workspace để lấy danh sách file_path cần unlink() trước.
     * WHY không filter deleted_at IS NULL:
     *   Cần xóa cả file vật lý của bản ghi đã soft-delete (chưa unlink thực sự).
     *
     * @param  int $workspaceId
     * @return array<int, array<string, mixed>>  Chỉ trả về id, file_path, stored_name
     */
    public function listAllByWorkspace(int $workspaceId): array
    {
        // WHY chỉ SELECT id, file_path, stored_name:
        // Caller chỉ cần path để unlink() – không cần toàn bộ columns.
        // Giảm data transfer trên InfinityFree shared hosting.
        $sql = '
            SELECT id, file_path, stored_name
            FROM   attachments
            WHERE  workspace_id = :workspace_id
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':workspace_id' => $workspaceId]);

            return $stmt->fetchAll();

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::listAllByWorkspace thất bại: ' . $e->getMessage(),
                'Attachment',
                "workspace_id={$workspaceId}"
            );
            return [];
        }
    }

    // =========================================================================
    // AGGREGATE / COUNT
    // =========================================================================

    /**
     * Đếm số attachment (chưa xóa) của một Issue.
     *
     * Dùng trong AttachmentController::store() để kiểm tra giới hạn UPLOAD_MAX_FILES
     * trước khi cho phép upload thêm.
     *
     * @param  int $issueId
     * @param  int $workspaceId
     * @return int
     */
    public function countByIssue(int $issueId, int $workspaceId): int
    {
        $sql = '
            SELECT COUNT(*) AS total
            FROM   attachments
            WHERE  issue_id     = :issue_id
              AND  workspace_id = :workspace_id
              AND  comment_id   IS NULL
              AND  deleted_at   IS NULL
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':issue_id'     => $issueId,
                ':workspace_id' => $workspaceId,
            ]);

            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::countByIssue thất bại: ' . $e->getMessage(),
                'Attachment',
                "issue_id={$issueId}, workspace_id={$workspaceId}"
            );
            // WHY trả về UPLOAD_MAX_FILES khi lỗi:
            // Fail-safe – nếu không đếm được thì chặn upload thay vì cho phép
            // vô hạn. Tránh tình huống lỗi DB dẫn đến bypass giới hạn file.
            return UPLOAD_MAX_FILES;
        }
    }

    /**
     * Đếm số attachment (chưa xóa) của một Comment.
     *
     * Dùng trong CommentService để kiểm tra giới hạn UPLOAD_MAX_FILES_COMMENT.
     *
     * @param  int $commentId
     * @param  int $workspaceId
     * @return int
     */
    public function countByComment(int $commentId, int $workspaceId): int
    {
        $sql = '
            SELECT COUNT(*) AS total
            FROM   attachments
            WHERE  comment_id   = :comment_id
              AND  workspace_id = :workspace_id
              AND  deleted_at   IS NULL
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':comment_id'   => $commentId,
                ':workspace_id' => $workspaceId,
            ]);

            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::countByComment thất bại: ' . $e->getMessage(),
                'Attachment',
                "comment_id={$commentId}, workspace_id={$workspaceId}"
            );
            return UPLOAD_MAX_FILES_COMMENT;
        }
    }

    /**
     * Thống kê tổng số file + tổng dung lượng của một Workspace.
     *
     * Dùng trong Admin Dashboard để hiển thị Storage Monitor
     * (TDD Phần 4.5 – "Storage Monitor: hiển thị ước tính số file trong /storage/").
     *
     * @param  int $workspaceId
     * @return array{total_files: int, total_size_bytes: int}
     */
    public function getStorageStats(int $workspaceId): array
    {
        $sql = '
            SELECT COUNT(*)     AS total_files,
                   SUM(file_size) AS total_size_bytes
            FROM   attachments
            WHERE  workspace_id = :workspace_id
              AND  deleted_at   IS NULL
        ';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':workspace_id' => $workspaceId]);

            $row = $stmt->fetch();

            return [
                'total_files'      => (int) ($row['total_files']      ?? 0),
                'total_size_bytes' => (int) ($row['total_size_bytes']  ?? 0),
            ];

        } catch (PDOException $e) {
            $this->logger->error(
                'Attachment::getStorageStats thất bại: ' . $e->getMessage(),
                'Attachment',
                "workspace_id={$workspaceId}"
            );
            return ['total_files' => 0, 'total_size_bytes' => 0];
        }
    }
}