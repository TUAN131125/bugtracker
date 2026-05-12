<?php

namespace App\Controllers\Issue;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\WorkspaceMember;
use App\Services\CommentService;

class CommentController
{
    private CommentService $commentService;
    private WorkspaceMember $memberModel;

    public function __construct()
    {
        $this->commentService = new CommentService();
        $this->memberModel    = new WorkspaceMember();
    }

    // Thêm comment – POST /issues/{id}/comments
    public function store(int $issueId): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');

        $content  = Request::post('content');
        $parentId = Request::post('parent_id') ? (int)Request::post('parent_id') : null;

        try {
            $commentId = $this->commentService->addComment(
                $issueId, $userId, $content, $workspaceId, $parentId
            );
            Response::json([
                'success'    => true,
                'comment_id' => $commentId,
                'message'    => 'Bình luận đã được thêm.',
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // Sửa comment – PUT /comments/{id}
    public function update(int $commentId): void
    {
        $userId  = Session::get('user_id');
        $content = Request::post('content');

        try {
            $this->commentService->editComment($commentId, $userId, $content);
            Response::json(['success' => true, 'message' => 'Bình luận đã được cập nhật.']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // Xóa comment – DELETE /comments/{id}
    public function destroy(int $commentId): void
    {
        $userId      = Session::get('user_id');
        $workspaceId = Session::get('active_workspace_id');
        $userRole    = $this->memberModel->getRole($workspaceId, $userId) ?? 'guest';

        try {
            $this->commentService->deleteComment($commentId, $userId, $userRole);
            Response::json(['success' => true, 'message' => 'Bình luận đã được xóa.']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // Toggle reaction – POST /comments/{id}/reaction
    public function reaction(int $commentId): void
    {
        $userId = Session::get('user_id');
        $emoji  = Request::post('emoji');

        try {
            $this->commentService->toggleReaction($commentId, $userId, $emoji);
            Response::json(['success' => true, 'message' => 'Reaction đã được cập nhật.']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}
