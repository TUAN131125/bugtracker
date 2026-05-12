<?php
// /app/Controllers/Issue/AttachmentController.php
// Phiên bản đầy đủ – bao gồm store(), serve(), destroy()

declare(strict_types=1);

namespace App\Controllers\Issue;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FileUploadService;
use App\Services\RbacService;
use App\Services\ActivityLogService;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\WorkspaceMember;
use App\Core\Logger;
use App\Core\Database;

/**
 * AttachmentController
 *
 * Xử lý 3 hành động liên quan đến file đính kèm của Issue:
 *   store()   – Upload file bổ sung vào Issue đã tồn tại (AJAX POST)
 *   serve()   – Kiểm tra quyền rồi stream file ra browser (GET)
 *   destroy() – Kiểm tra quyền rồi soft-delete + xóa file vật lý (AJAX DELETE)
 *
 * WHY tách store() thành route riêng (không chỉ upload lúc tạo Issue):
 *   SRS UC-029 cho phép upload file vào Issue đã tồn tại bất kỳ lúc nào.
 *   IssueController::store() xử lý upload inline khi TẠO Issue lần đầu.
 *   AttachmentController::store() xử lý upload BỔ SUNG sau đó.
 */
class AttachmentController
{
    public function __construct(
        private Request             $request,
        private Response            $response,
        private Session             $session,
        private FileUploadService   $fileUploadService,
        private RbacService         $rbacService,
        private ActivityLogService  $activityLogService,
        private Attachment          $attachmentModel,
        private Issue               $issueModel,
        private WorkspaceMember     $memberModel,
        private Logger              $logger,
        private Database            $db
    ) {}

    // =========================================================================
    // store() – POST /issues/{issueKey}/attachments
    // Upload file bổ sung vào Issue đã tồn tại
    // =========================================================================

    /**
     * @param string $issueKey  VD: "BT-001" – từ URL parameter
     */
    public function store(string $issueKey): void
    {
        // Đảm bảo đây là AJAX request
        if (!$this->request->isAjax()) {
            $this->response->json(['success' => false, 'message' => 'Invalid request.'], 400);
            return;
        }

        $currentUserId     = $this->session->get('user_id');
        $activeWorkspaceId = $this->session->get('active_workspace_id');

        // --- Bước 1: Tìm Issue theo issueKey trong workspace đang active ---
        $issue = $this->issueModel->findByKey($issueKey, $activeWorkspaceId);

        if ($issue === null) {
            $this->response->json([
                'success' => false,
                'message' => "Issue {$issueKey} không tồn tại."
            ], 404);
            return;
        }

        // --- Bước 2: Kiểm tra Project có bị archived không ---
        // WHY: SRS Phần 3.2.2 – Issue trong Project archived là read-only
        if ($issue['project_status'] === 'archived') {
            $this->response->json([
                'success' => false,
                'message' => 'Project đã bị archived. Không thể thêm file đính kèm.'
            ], 403);
            return;
        }

        // --- Bước 3: Kiểm tra quyền upload ---
        // Được phép: Owner, Admin, Member được giao Issue (Assignee), Reporter
        // WHY cho Reporter upload: họ có thể cần bổ sung screenshot/log sau khi tạo Issue
        $canUpload = $this->rbacService->canCommentOnIssue($currentUserId, $activeWorkspaceId)
            || $issue['reporter_id'] === $currentUserId
            || $issue['assignee_id'] === $currentUserId;

        if (!$canUpload) {
            $this->response->json([
                'success' => false,
                'message' => 'Bạn không có quyền đính kèm file vào Issue này.'
            ], 403);
            return;
        }

        // --- Bước 4: Lấy danh sách file từ request ---
        $uploadedFiles = $this->request->files('attachments');

        // Chuẩn hóa: $_FILES có thể là single file hoặc array file
        // WHY cần normalize: PHP xử lý $_FILES['attachments'] khác nhau
        // tùy theo input name là "attachments" hay "attachments[]"
        $normalizedFiles = $this->normalizeFilesArray($uploadedFiles);

        if (empty($normalizedFiles)) {
            $this->response->json([
                'success' => false,
                'message' => 'Không có file nào được gửi lên.'
            ], 400);
            return;
        }

        // --- Bước 5: Kiểm tra giới hạn số lượng file hiện có của Issue ---
        $existingCount = $this->attachmentModel->countByIssue(
            $issue['id'],
            $activeWorkspaceId
        );

        $newCount = count($normalizedFiles);

        if (($existingCount + $newCount) > MAX_FILES_PER_ISSUE) {
            $remaining = MAX_FILES_PER_ISSUE - $existingCount;
            $this->response->json([
                'success' => false,
                'message' => "Issue này chỉ còn có thể đính kèm thêm {$remaining} file "
                           . "(giới hạn " . MAX_FILES_PER_ISSUE . " file/Issue)."
            ], 422);
            return;
        }

        // --- Bước 6: Validate + Upload từng file trong DB transaction ---
        // WHY transaction: Nếu file thứ 3/3 lỗi, rollback bản ghi DB của 2 file trước.
        // File vật lý đã move vào storage sẽ được cleanup bởi Admin Storage Monitor.
        // (Không thể "unmove" file vật lý trong transaction – đây là giới hạn của PHP)
        $this->db->beginTransaction();

        $savedAttachments = [];
        $errors           = [];

        try {
            foreach ($normalizedFiles as $index => $file) {
                try {
                    // Validate + move file vào /storage/attachments/{ws_id}/{issue_id}/
                    $fileData = $this->fileUploadService->store(
                        $file,
                        $activeWorkspaceId,
                        $issue['id']
                    );

                    // Insert bản ghi vào bảng attachments
                    $attachmentId = $this->attachmentModel->create([
                        'workspace_id'  => $activeWorkspaceId,
                        'issue_id'      => $issue['id'],
                        'comment_id'    => null, // Đây là attachment của Issue, không phải Comment
                        'uploader_id'   => $currentUserId,
                        'original_name' => $fileData['original_name'],
                        'stored_name'   => $fileData['stored_name'],
                        'file_path'     => $fileData['file_path'],
                        'mime_type'     => $fileData['mime_type'],
                        'file_size'     => $fileData['file_size'],
                    ]);

                    // Chuẩn bị response data cho từng file thành công
                    $savedAttachments[] = [
                        'id'            => $attachmentId,
                        'original_name' => $fileData['original_name'],
                        'file_size'     => $fileData['file_size'],
                        'mime_type'     => $fileData['mime_type'],
                        // URL để serve file qua PHP proxy
                        'url'           => "/files/{$activeWorkspaceId}/{$issue['id']}/{$fileData['stored_name']}",
                        // URL thumbnail (chỉ có nếu là ảnh)
                        'thumbnail_url' => $this->buildThumbnailUrl(
                            $fileData['mime_type'],
                            $activeWorkspaceId,
                            $issue['id'],
                            $fileData['stored_name']
                        ),
                    ];

                } catch (\RuntimeException $e) {
                    // Ghi nhận lỗi của file này nhưng tiếp tục xử lý file khác
                    $errors[] = [
                        'file'    => $file['name'] ?? "File #{$index}",
                        'message' => $e->getMessage(),
                    ];
                }
            }

            // Nếu KHÔNG có file nào thành công → rollback và báo lỗi
            if (empty($savedAttachments)) {
                $this->db->rollBack();
                $this->response->json([
                    'success' => false,
                    'message' => 'Không có file nào được upload thành công.',
                    'errors'  => $errors,
                ], 422);
                return;
            }

            // Ít nhất 1 file thành công → commit
            $this->db->commit();

        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error(
                'Lỗi không mong đợi khi upload attachment: ' . $e->getMessage(),
                'AttachmentController::store',
                $e->getTraceAsString()
            );
            $this->response->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi xử lý file. Vui lòng thử lại.'
            ], 500);
            return;
        }

        // --- Bước 7: Ghi Activity Log ---
        // Ghi 1 log chung cho cả batch upload (không ghi từng file)
        // WHY: Tránh spam Activity Log khi upload nhiều file cùng lúc
        $fileCount = count($savedAttachments);
        $this->activityLogService->log(
            workspaceId: $activeWorkspaceId,
            userId:      $currentUserId,
            entityType:  'issue',
            entityId:    $issue['id'],
            actionType:  'attachment_added',
            metadata:    [
                'issue_key'  => $issueKey,
                'file_count' => $fileCount,
                'file_names' => array_column($savedAttachments, 'original_name'),
            ]
        );

        // --- Bước 8: Trả về response ---
        // Trả về cả file thành công và file lỗi để Dev 3 hiển thị partial success UI
        $message = $fileCount === count($normalizedFiles)
            ? "Đã đính kèm {$fileCount} file thành công."
            : "Đã đính kèm {$fileCount}/{$newCount} file. "
              . count($errors) . " file bị lỗi.";

        $this->response->json([
            'success'     => true,
            'message'     => $message,
            'attachments' => $savedAttachments,
            // Trả về errors của file thất bại để JS hiển thị inline
            'errors'      => $errors,
        ]);
    }

    // =========================================================================
    // serve() – GET /files/{workspaceId}/{issueId}/{filename}
    // =========================================================================

    public function serve(int $workspaceId, int $issueId, string $filename): void
    {
        $currentUserId = $this->session->get('user_id');

        // --- Kiểm tra IDOR: user phải là member của workspace trong URL ---
        // WHY không dùng active_workspace_id từ session:
        // URL /files/{workspaceId}/... có thể khác workspace đang active.
        // Đây là vector tấn công IDOR điển hình – phải check trực tiếp với DB.
        if (!$this->memberModel->isMember($workspaceId, $currentUserId)) {
            $this->logger->warning(
                "IDOR attempt: User {$currentUserId} truy cập file của workspace {$workspaceId}",
                'AttachmentController::serve'
            );
            http_response_code(403);
            exit('Bạn không có quyền truy cập file này.');
        }

        // --- Tìm bản ghi attachment theo stored_name + workspaceId + issueId ---
        // WHY tìm theo cả 3 trường: chống brute-force stored_name để
        // truy cập file của workspace khác
        $attachment = $this->attachmentModel->findByStoredName(
            $filename,
            $workspaceId,
            $issueId
        );

        if ($attachment === null || $attachment['deleted_at'] !== null) {
            http_response_code(404);
            exit('File không tồn tại hoặc đã bị xóa.');
        }

        // Delegate serve cho FileUploadService (path traversal check + readfile)
        $this->fileUploadService->serve(
            $attachment['file_path'],
            $attachment['original_name'],
            $attachment['mime_type']
        );
    }

    // =========================================================================
    // destroy() – DELETE /issues/{issueKey}/attachments/{id}
    // =========================================================================

    /**
     * @param string $issueKey  VD: "BT-001" – từ URL (để verify ownership)
     * @param int    $id        Attachment ID trong DB
     */
    public function destroy(string $issueKey, int $id): void
    {
        if (!$this->request->isAjax()) {
            $this->response->json(['success' => false, 'message' => 'Invalid request.'], 400);
            return;
        }

        $currentUserId     = $this->session->get('user_id');
        $activeWorkspaceId = $this->session->get('active_workspace_id');

        // --- Tìm attachment theo id VÀ workspace_id ---
        // WHY thêm workspace_id: chống IDOR – user không thể xóa file
        // của workspace khác bằng cách đoán attachment ID
        $attachment = $this->attachmentModel->findById($id, $activeWorkspaceId);

        if ($attachment === null || $attachment['deleted_at'] !== null) {
            $this->response->json([
                'success' => false,
                'message' => 'File không tồn tại hoặc đã bị xóa.'
            ], 404);
            return;
        }

        // --- Verify attachment thuộc đúng issue trong URL ---
        // WHY: Ngăn user xóa attachment của Issue khác bằng cách
        // ghép URL /issues/BT-001/attachments/{id_của_BT-002}
        $issue = $this->issueModel->findByKey($issueKey, $activeWorkspaceId);

        if ($issue === null || $attachment['issue_id'] !== $issue['id']) {
            $this->response->json([
                'success' => false,
                'message' => 'File không thuộc Issue này.'
            ], 403);
            return;
        }

        // --- Kiểm tra quyền xóa ---
        // Được phép: uploader chính họ, hoặc Admin/Owner
        $isUploader     = ((int) $attachment['uploader_id']) === $currentUserId;
        $isAdminOrOwner = $this->rbacService->canManageIssue($currentUserId, $activeWorkspaceId);

        if (!$isUploader && !$isAdminOrOwner) {
            $this->response->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa file này.'
            ], 403);
            return;
        }

        // --- Soft-delete bản ghi trong DB trước ---
        // WHY soft-delete trước: nếu xóa file vật lý trước rồi DB fail,
        // DB vẫn còn bản ghi trỏ đến file không còn tồn tại → broken link.
        // Soft-delete trước an toàn hơn: file vật lý còn đó nhưng ẩn khỏi UI.
        $deleted = $this->attachmentModel->softDelete($id);

        if (!$deleted) {
            $this->logger->error(
                "Soft-delete attachment {$id} thất bại",
                'AttachmentController::destroy'
            );
            $this->response->json([
                'success' => false,
                'message' => 'Không thể xóa file. Vui lòng thử lại.'
            ], 500);
            return;
        }

        // --- Xóa file vật lý (best-effort) ---
        // Không rollback DB nếu xóa file fail.
        // File vật lý còn trên disk sẽ được Admin dọn qua Storage Monitor.
        $physicalDeleted = $this->fileUploadService->delete($attachment['file_path']);

        if (!$physicalDeleted) {
            $this->logger->warning(
                "Soft-delete DB thành công nhưng không xóa được file vật lý: "
                . $attachment['file_path'],
                'AttachmentController::destroy'
            );
        }

        // --- Ghi Activity Log ---
        $this->activityLogService->log(
            workspaceId: $activeWorkspaceId,
            userId:      $currentUserId,
            entityType:  'issue',
            entityId:    $issue['id'],
            actionType:  'attachment_deleted',
            metadata:    [
                'issue_key'     => $issueKey,
                'original_name' => $attachment['original_name'],
            ]
        );

        $this->response->json([
            'success' => true,
            'message' => "Đã xóa file \"{$attachment['original_name']}\"."
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Chuẩn hóa $_FILES array về dạng list of files nhất quán.
     *
     * PHP trả về $_FILES theo 2 format khác nhau tùy theo HTML:
     *
     * Format 1 – Single file (name="attachments"):
     *   ['name'=>'a.jpg', 'type'=>'image/jpeg', 'tmp_name'=>'/tmp/x', ...]
     *
     * Format 2 – Multiple files (name="attachments[]"):
     *   ['name'=>['a.jpg','b.png'], 'type'=>['image/jpeg','image/png'],
     *    'tmp_name'=>['/tmp/x','/tmp/y'], ...]
     *
     * WHY cần normalize: FileUploadService::store() chỉ nhận single file array.
     * Method này convert cả 2 format về: [['name'=>..,'tmp_name'=>..], ...]
     */
    private function normalizeFilesArray(mixed $files): array
    {
        if (empty($files) || !isset($files['tmp_name'])) {
            return [];
        }

        // Format 1: single file
        if (!is_array($files['tmp_name'])) {
            // Bỏ qua nếu không có file được chọn (tmp_name rỗng)
            if ($files['error'] === UPLOAD_ERR_NO_FILE) {
                return [];
            }
            return [$files];
        }

        // Format 2: multiple files – transpose array
        $normalized = [];
        $count = count($files['tmp_name']);

        for ($i = 0; $i < $count; $i++) {
            // Bỏ qua slot rỗng trong mảng
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
     * Thumbnail được tạo bởi FileUploadService::createThumbnail()
     * với tên format: {stored_name_without_ext}_thumb.jpg
     *
     * WHY trả về null cho non-image:
     * Dev 3 dùng null để biết hiển thị file icon thay vì <img> tag.
     */
    private function buildThumbnailUrl(
        string $mimeType,
        int    $workspaceId,
        int    $issueId,
        string $storedName
    ): ?string {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif'];

        if (!in_array($mimeType, $imageTypes, true)) {
            return null;
        }

        $nameWithoutExt = pathinfo($storedName, PATHINFO_FILENAME);
        $thumbName      = $nameWithoutExt . '_thumb.jpg';

        return "/files/{$workspaceId}/{$issueId}/{$thumbName}";
    }
}