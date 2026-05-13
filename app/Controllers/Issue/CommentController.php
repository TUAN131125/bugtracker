<?php

declare(strict_types=1);

namespace App\Controllers\Issue;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\WorkspaceMember;
use App\Services\CommentService;

/**
 * CommentController – Xử lý các thao tác bình luận trên Issue.
 *
 * Tất cả endpoint đều là AJAX (JSON response).
 * Response format thống nhất: {"success": bool, "data": {}, "message": "..."}
 * theo quy ước trong Task Assignment Phần 5.1.
 *
 * WHY inject Request/Response/Session thay vì gọi static:
 * Request, Response là instance class theo thiết kế TDD D1-007.
 * Gọi static sai với kiến trúc và gây lỗi Intelephense P1036.
 * Inject qua constructor giúp dễ test và đúng MVC pattern.
 */
class CommentController
{
    private CommentService  $commentService;
    private WorkspaceMember $memberModel;
    private Request         $request;
    private Response        $response;
    private Session         $session;

    public function __construct(
        Request         $request,
        Response        $response,
        Session         $session,
        CommentService  $commentService,
        WorkspaceMember $memberModel
    ) {
        $this->request        = $request;
        $this->response       = $response;
        $this->session        = $session;
        $this->commentService = $commentService;
        $this->memberModel    = $memberModel;
    }

    /**
     * Thêm bình luận vào Issue.
     * POST /issues/{id}/comments
     *
     * @param int $issueId ID của Issue cần bình luận.
     */
    public function store(int $issueId): void
    {
        $userId      = (int) $this->session->get('user_id');
        $workspaceId = (int) $this->session->get('active_workspace_id');

        $content  = $this->request->post('content');
        $parentId = $this->request->post('parent_id')
                        ? (int) $this->request->post('parent_id')
                        : null;

        try {
            $commentId = $this->commentService->addComment(
                $issueId,
                $userId,
                $content,
                $workspaceId,
                $parentId
            );

            $this->response->json([
                'success'    => true,
                'data'       => ['comment_id' => $commentId],
                'message'    => 'Bình luận đã được thêm.',
            ]);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'data'    => [],
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Chỉnh sửa bình luận.
     * PUT /comments/{id}
     *
     * Chỉ tác giả mới được sửa và trong giới hạn 30 phút (validate trong Service).
     *
     * @param int $commentId ID của comment cần sửa.
     */
    public function update(int $commentId): void
    {
        $userId  = (int) $this->session->get('user_id');
        $content = $this->request->post('content');

        try {
            $this->commentService->editComment($commentId, $userId, $content);

            $this->response->json([
                'success' => true,
                'data'    => [],
                'message' => 'Bình luận đã được cập nhật.',
            ]);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'data'    => [],
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Xóa bình luận.
     * DELETE /comments/{id}
     *
     * Tác giả xóa được trong 30 phút. Admin/Owner xóa được bất kỳ lúc nào.
     * Role được lấy từ workspace_members để Service kiểm tra quyền.
     *
     * @param int $commentId ID của comment cần xóa.
     */
    public function destroy(int $commentId): void
    {
        $userId      = (int) $this->session->get('user_id');
        $workspaceId = (int) $this->session->get('active_workspace_id');

        // Lấy role từ DB để Service kiểm tra quyền xóa comment của người khác
        $userRole = $this->memberModel->getRole($workspaceId, $userId) ?? 'guest';

        try {
            $this->commentService->deleteComment($commentId, $userId, $userRole);

            $this->response->json([
                'success' => true,
                'data'    => [],
                'message' => 'Bình luận đã được xóa.',
            ]);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'data'    => [],
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Toggle emoji reaction trên bình luận.
     * POST /comments/{id}/reaction
     *
     * Click lần 1: thêm reaction. Click lần 2: bỏ reaction (toggle).
     * Logic xử lý trong CommentService::toggleReaction().
     *
     * @param int $commentId ID của comment cần react.
     */
    public function reaction(int $commentId): void
    {
        $userId = (int) $this->session->get('user_id');
        $emoji  = $this->request->post('emoji');

        try {
            $this->commentService->toggleReaction($commentId, $userId, $emoji);

            $this->response->json([
                'success' => true,
                'data'    => [],
                'message' => 'Reaction đã được cập nhật.',
            ]);
        } catch (\Exception $e) {
            $this->response->json([
                'success' => false,
                'data'    => [],
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}