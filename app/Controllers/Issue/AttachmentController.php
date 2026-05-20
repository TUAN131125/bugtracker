<?php

declare(strict_types=1);

namespace App\Controllers\Issue;

use PDO;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FileUploadService;
use App\Services\RbacService;
use App\Services\ActivityLogService;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\WorkspaceMember;

/**
 * AttachmentController
 *
 * Xử lý 3 hành động liên quan đến file đính kèm của Issue:
 *   store()   – Upload file bổ sung vào Issue đã tồn tại (AJAX POST)
 *   serve()   – Kiểm tra quyền rồi stream file ra browser (GET)
 *   destroy() – Kiểm tra quyền rồi soft-delete + xóa file vật lý (AJAX DELETE)
 *
 * @author  Dev 1
 * @version 1.0.1
 * @see     SRS v1.0.0 – UC-029, UC-030
 * @see     TDD Backend v1.0.0 – Phần 3.1 (Bảo mật thư mục), Phần 3.3
 * @see     Task Assignment v1.0.0 – D1-026, D1-027
 */
class AttachmentController
{
    private PDO                $db;
    private FileUploadService  $file_upload_service;
    private RbacService        $rbac_service;
    private ActivityLogService $activity_log_service;
    private Attachment         $attachment_model;
    private Issue              $issue_model;
    private WorkspaceMember    $member_model;

    public function __construct()
    {
        $this->db                   = Database::getInstance();

        // FileUploadService tự khởi tạo Logger bên trong – không cần truyền argument.
        // WHY: Nhất quán với pattern new ClassName() của toàn bộ codebase.
        $this->file_upload_service  = new FileUploadService();

        $this->rbac_service         = new RbacService();
        $this->activity_log_service = new ActivityLogService();
        $this->attachment_model     = new Attachment();
        $this->issue_model          = new Issue();
        $this->member_model         = new WorkspaceMember();
    }

    // =========================================================================
    // store() – POST /issues/{issueKey}/attachments
    // =========================================================================

    /**
     * Upload file bổ sung vào Issue đã tồn tại.
     *
     * Luồng: validate request → tìm Issue → kiểm tra quyền →
     *        normalize files → kiểm tra quota → transaction upload → log.
     *
     * @param  Request $request   Inject từ Router.
     * @param  string  $issueKey  VD: "BT-001" – từ URL parameter.
     * @return void
     */
    public function store(Request $request, string $issueKey): void
    {
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Invalid request.'], 400);
            return;
        }

        $current_user_id     = Session::getUserId();
        $active_workspace_id = Session::getActiveWorkspaceId();

        // --- Bước 1: Tìm Issue ---
        $issue = $this->issue_model->findByKey($issueKey, $active_workspace_id);

        if ($issue === null) {
            Response::json([
                'success' => false,
                'message' => "Issue {$issueKey} không tồn tại.",
            ], 404);
            return;
        }

        // --- Bước 2: Kiểm tra Project archived ---
        // Issue trong archived project là read-only (SRS Phần 3.2.2)
        if ($issue['project_status'] === 'archived') {
            Response::json([
                'success' => false,
                'message' => 'Project đã bị archived. Không thể thêm file đính kèm.',
            ], 403);
            return;
        }

        // --- Bước 3: Kiểm tra quyền upload ---
        // Có quyền nếu: là member có quyền comment, hoặc là reporter/assignee của Issue.
        // WHY dùng canUploadAttachment thay vì canCommentOnIssue:
        //   canCommentOnIssue không tồn tại trong RbacService.
        //   canUploadAttachment được định nghĩa đúng trong RbacService
        //   theo SRS Phần 1.3 (RBAC Matrix): Owner/Admin/Member/Guest đều
        //   có thể đính kèm file vào Issue.
        $can_upload = $this->rbac_service->canUploadAttachment($current_user_id, $active_workspace_id)
            || (int) $issue['reporter_id'] === $current_user_id
            || (int) $issue['assignee_id'] === $current_user_id;

        if (!$can_upload) {
            Response::json([
                'success' => false,
                'message' => 'Bạn không có quyền đính kèm file vào Issue này.',
            ], 403);
            return;
        }

        // --- Bước 4: Lấy và normalize files ---
        $uploaded_files   = $request->file('attachments');
        $normalized_files = $this->normalizeFilesArray($uploaded_files);

        if (empty($normalized_files)) {
            Response::json([
                'success' => false,
                'message' => 'Không có file nào được gửi lên.',
            ], 400);
            return;
        }

        // --- Bước 5: Kiểm tra giới hạn số lượng file ---
        // UPLOAD_MAX_FILES = 5 theo SRS UC-019, define trong config.php Section 4.
        // WHY kiểm tra ở đây thay vì chỉ trong DB constraint:
        //   Trả về error message rõ ràng cho user trước khi tốn I/O upload.
        $existing_count = $this->attachment_model->countByIssue($issue['id'], $active_workspace_id);
        $new_count      = count($normalized_files);

        if (($existing_count + $new_count) > UPLOAD_MAX_FILES) {
            $remaining = max(0, UPLOAD_MAX_FILES - $existing_count);
            Response::json([
                'success' => false,
                'message' => "Issue này chỉ còn có thể đính kèm thêm {$remaining} file "
                           . '(giới hạn ' . UPLOAD_MAX_FILES . ' file/Issue).',
            ], 422);
            return;
        }

        // --- Bước 6: Validate + Upload trong DB transaction ---
        // WHY transaction: file vật lý và bản ghi DB phải đồng bộ.
        // Nếu insert DB thất bại → rollback, file vật lý đã move cần được
        // clean up (best-effort, không critical vì file trong /storage/ không
        // accessible từ web).
        $this->db->beginTransaction();

        $saved_attachments = [];
        $errors            = [];

        try {
            foreach ($normalized_files as $index => $file) {
                try {
                    $file_data = $this->file_upload_service->store(
                        $file,
                        $active_workspace_id,
                        $issue['id']
                    );

                    $attachment_id = $this->attachment_model->create([
                        'workspace_id'  => $active_workspace_id,
                        'issue_id'      => $issue['id'],
                        'comment_id'    => null,
                        'uploader_id'   => $current_user_id,
                        'original_name' => $file_data['original_name'],
                        'stored_name'   => $file_data['stored_name'],
                        'file_path'     => $file_data['file_path'],
                        'mime_type'     => $file_data['mime_type'],
                        'file_size'     => $file_data['file_size'],
                    ]);

                    $saved_attachments[] = [
                        'id'            => $attachment_id,
                        'original_name' => $file_data['original_name'],
                        'file_size'     => $file_data['file_size'],
                        'mime_type'     => $file_data['mime_type'],
                        'url'           => "/files/{$active_workspace_id}/{$issue['id']}/{$file_data['stored_name']}",
                        'thumbnail_url' => $this->buildThumbnailUrl(
                            $file_data['mime_type'],
                            $active_workspace_id,
                            $issue['id'],
                            $file_data['stored_name']
                        ),
                    ];

                } catch (\RuntimeException $e) {
                    // File này lỗi nhưng tiếp tục xử lý các file còn lại
                    $errors[] = [
                        'file'    => $file['name'] ?? "File #{$index}",
                        'message' => $e->getMessage(),
                    ];
                }
            }

            // Nếu không có file nào thành công thì rollback toàn bộ
            if (empty($saved_attachments)) {
                $this->db->rollBack();
                Response::json([
                    'success' => false,
                    'message' => 'Không có file nào được upload thành công.',
                    'errors'  => $errors,
                ], 422);
                return;
            }

            $this->db->commit();

        } catch (\Throwable $e) {
            $this->db->rollBack();

            // TODO: Replace bằng Logger::error() sau khi D1-021 hoàn thành (Ngày 3)
            error_log(sprintf(
                '[AttachmentController::store] Unexpected error | Workspace: %d | Issue: %s | Error: %s',
                $active_workspace_id,
                $issueKey,
                $e->getMessage()
            ));

            Response::json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi xử lý file. Vui lòng thử lại.',
            ], 500);
            return;
        }

        // --- Bước 7: Ghi Activity Log ---
        $file_count = count($saved_attachments);

        $this->activity_log_service->log(
            $active_workspace_id,
            $current_user_id,
            'issue',
            $issue['id'],
            'attachment_added',
            [
                'issue_key'  => $issueKey,
                'file_count' => $file_count,
                'file_names' => array_column($saved_attachments, 'original_name'),
            ]
        );

        // --- Bước 8: Response ---
        $message = ($file_count === $new_count)
            ? "Đã đính kèm {$file_count} file thành công."
            : "Đã đính kèm {$file_count}/{$new_count} file. " . count($errors) . ' file bị lỗi.';

        Response::json([
            'success'     => true,
            'message'     => $message,
            'attachments' => $saved_attachments,
            'errors'      => $errors,
        ]);
    }

    // =========================================================================
    // serve() – GET /files/{workspaceId}/{issueId}/{filename}
    // =========================================================================

    /**
     * Stream file ra browser sau khi kiểm tra quyền.
     *
     * WHY lấy workspaceId từ URL thay vì session:
     *   User có thể bookmark link file của workspace A trong khi đang active
     *   workspace B. Validate trực tiếp với DB theo workspaceId từ URL để
     *   chống IDOR, không dùng active_workspace_id từ session.
     *
     * @param  Request $request      Inject từ Router (luôn là argument đầu tiên).
     * @param  int     $workspaceId  Từ URL parameter.
     * @param  int     $issueId      Từ URL parameter.
     * @param  string  $filename     Stored name của file (không phải original name).
     * @return void
     */
    public function serve(Request $request, int $workspaceId, int $issueId, string $filename): void
    {
        $current_user_id = Session::getUserId();

        // Kiểm tra user có là member của workspace trong URL không.
        // WHY không dùng RbacService ở đây: isMember() là check đơn giản nhất,
        // đủ để authorize download — không cần check role cụ thể.
        if (!$this->member_model->isMember($workspaceId, $current_user_id)) {
            // TODO: Replace bằng Logger::warning() sau khi D1-021 hoàn thành (Ngày 3)
            error_log(sprintf(
                '[AttachmentController::serve] Unauthorized access | User: %d | Workspace: %d | File: %s',
                $current_user_id,
                $workspaceId,
                $filename
            ));

            http_response_code(403);
            exit('Bạn không có quyền truy cập file này.');
        }

        $attachment = $this->attachment_model->findByStoredName($filename, $workspaceId, $issueId);

        if ($attachment === null || $attachment['deleted_at'] !== null) {
            http_response_code(404);
            exit('File không tồn tại hoặc đã bị xóa.');
        }

        $this->file_upload_service->serve(
            $attachment['file_path'],
            $attachment['original_name'],
            $attachment['mime_type']
        );
    }

    // =========================================================================
    // destroy() – DELETE /issues/{issueKey}/attachments/{id}
    // =========================================================================

    /**
     * Soft-delete attachment sau khi kiểm tra quyền.
     *
     * Quyền xóa (SRS Phần 1.3 RBAC Matrix):
     *   - Uploader: được xóa file của chính mình
     *   - Admin / Owner: được xóa file của bất kỳ ai trong workspace
     *
     * WHY soft-delete DB trước, xóa file vật lý sau:
     *   Nếu xóa file vật lý trước rồi DB fail → file mất nhưng DB còn bản ghi
     *   → broken reference. Chiều ngược lại an toàn hơn: DB xóa xong, file
     *   vật lý còn cũng không accessible từ web (nằm ngoài public_html).
     *
     * @param  Request $request   Inject từ Router.
     * @param  string  $issueKey  VD: "BT-001".
     * @param  int     $id        Primary key của attachment.
     * @return void
     */
    public function destroy(Request $request, string $issueKey, int $id): void
    {
        if (!$request->isAjax()) {
            Response::json(['success' => false, 'message' => 'Invalid request.'], 400);
            return;
        }

        $current_user_id     = Session::getUserId();
        $active_workspace_id = Session::getActiveWorkspaceId();

        // --- Bước 1: Tìm attachment và verify workspace guard ---
        $attachment = $this->attachment_model->findById($id, $active_workspace_id);

        if ($attachment === null || $attachment['deleted_at'] !== null) {
            Response::json([
                'success' => false,
                'message' => 'File không tồn tại hoặc đã bị xóa.',
            ], 404);
            return;
        }

        // --- Bước 2: Verify attachment thuộc đúng Issue ---
        $issue = $this->issue_model->findByKey($issueKey, $active_workspace_id);

        if ($issue === null || (int) $attachment['issue_id'] !== (int) $issue['id']) {
            Response::json([
                'success' => false,
                'message' => 'File không thuộc Issue này.',
            ], 403);
            return;
        }

        // --- Bước 3: Kiểm tra quyền xóa ---
        $is_uploader       = (int) $attachment['uploader_id'] === $current_user_id;

        // WHY dùng canDeleteAttachment thay vì canManageIssue:
        //   canManageIssue không tồn tại trong RbacService.
        //   canDeleteAttachment map đúng với RBAC Matrix SRS Phần 1.3:
        //   Admin/Owner có quyền xóa attachment của người khác.
        $is_admin_or_owner = $this->rbac_service->canDeleteAttachment($current_user_id, $active_workspace_id, $attachment['uploader_id']);

        if (!$is_uploader && !$is_admin_or_owner) {
            Response::json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa file này.',
            ], 403);
            return;
        }

        // --- Bước 4: Soft-delete trong DB ---
        $deleted = $this->attachment_model->softDelete($id);

        if (!$deleted) {
            // TODO: Replace bằng Logger::error() sau khi D1-021 hoàn thành (Ngày 3)
            error_log(sprintf(
                '[AttachmentController::destroy] Soft-delete failed | Attachment: %d | Workspace: %d',
                $id,
                $active_workspace_id
            ));

            Response::json([
                'success' => false,
                'message' => 'Không thể xóa file. Vui lòng thử lại.',
            ], 500);
            return;
        }

        // --- Bước 5: Xóa file vật lý – best-effort ---
        $physical_deleted = $this->file_upload_service->delete($attachment['file_path']);

        if (!$physical_deleted) {
            // TODO: Replace bằng Logger::warning() sau khi D1-021 hoàn thành (Ngày 3)
            error_log(sprintf(
                '[AttachmentController::destroy] DB soft-deleted but physical file remains: %s',
                $attachment['file_path']
            ));
            // Không return lỗi — DB đã xóa thành công, đây chỉ là orphan file
        }

        // --- Bước 6: Ghi Activity Log ---
        $this->activity_log_service->log(
            $active_workspace_id,
            $current_user_id,
            'issue',
            $issue['id'],
            'attachment_deleted',
            [
                'issue_key'     => $issueKey,
                'original_name' => $attachment['original_name'],
            ]
        );

        Response::json([
            'success' => true,
            'message' => "Đã xóa file \"{$attachment['original_name']}\".",
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Chuẩn hóa $_FILES array về dạng list of files nhất quán.
     *
     * PHP trả về $_FILES theo 2 format tùy theo HTML input name:
     *   "attachments"   → single file: ['name'=>'...', 'tmp_name'=>'...', ...]
     *   "attachments[]" → multi file transposed: ['name'=>[...], 'tmp_name'=>[...], ...]
     *
     * Method này normalize cả 2 dạng về array of single-file arrays.
     *
     * @param  mixed $files  Giá trị từ $request->file('attachments').
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFilesArray(mixed $files): array
    {
        if (empty($files) || !isset($files['tmp_name'])) {
            return [];
        }

        // Single file format
        if (!is_array($files['tmp_name'])) {
            if ($files['error'] === UPLOAD_ERR_NO_FILE) {
                return [];
            }
            return [$files];
        }

        // Multi file format – transpose lại thành array of single-file arrays
        $normalized = [];
        $count      = count($files['tmp_name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }

        return $normalized;
    }

    /**
     * Xây dựng URL thumbnail cho ảnh.
     *
     * Trả về null cho non-image để Dev 3 hiển thị file-type icon thay vì <img>.
     * URL trỏ đến AttachmentController::serve() – không phải URL trực tiếp đến file.
     *
     * @param  string $mime_type
     * @param  int    $workspace_id
     * @param  int    $issue_id
     * @param  string $stored_name  Tên file gốc (không phải thumbnail name).
     * @return string|null
     */
    private function buildThumbnailUrl(
        string $mime_type,
        int    $workspace_id,
        int    $issue_id,
        string $stored_name
    ): ?string {
        // WHY dùng IMAGE_MIME_TYPES từ config.php thay vì hardcode array:
        //   Nhất quán với FileUploadService::store() – cùng whitelist.
        if (!in_array($mime_type, IMAGE_MIME_TYPES, true)) {
            return null;
        }

        $name_without_ext = pathinfo($stored_name, PATHINFO_FILENAME);
        $thumb_name       = $name_without_ext . '_thumb.jpg';

        return "/files/{$workspace_id}/{$issue_id}/{$thumb_name}";
    }
}