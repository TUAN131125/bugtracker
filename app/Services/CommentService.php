<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\CommentReaction;

class CommentService
{
    private Comment $commentModel;
    private CommentReaction $reactionModel;
    private ActivityLogService $activityLog;

    public function __construct()
    {
        $this->commentModel  = new Comment();
        $this->reactionModel = new CommentReaction();
        $this->activityLog   = new ActivityLogService();
    }

    public function addComment(int $issueId, int $userId, string $content, int $workspaceId, ?int $parentId = null): int
    {
        if (empty(trim($content))) {
            throw new \Exception("Nội dung bình luận không được để trống.");
        }

        $commentId = $this->commentModel->create($issueId, $userId, $content, $workspaceId, $parentId);

        // Ghi Activity Log
        $this->activityLog->log(
            $workspaceId,
            $userId,
            'issue',
            $issueId,
            ActivityLogService::COMMENT_ADDED,
            null,
            $commentId
        );

        return $commentId;
    }

    public function editComment(int $commentId, int $userId, string $content): bool
    {
        $comment = $this->commentModel->findById($commentId);

        if (!$comment) {
            throw new \Exception("Bình luận không tồn tại.");
        }

        // Chỉ chủ sở hữu mới sửa được
        if ((int)$comment['user_id'] !== $userId) {
            throw new \Exception("Bạn không có quyền sửa bình luận này.");
        }

        // Chỉ sửa được trong 30 phút
        if (!$this->commentModel->isWithin30Minutes($commentId)) {
            throw new \Exception("Chỉ có thể sửa bình luận trong vòng 30 phút.");
        }

        return $this->commentModel->update($commentId, $content);
    }

    public function deleteComment(int $commentId, int $userId, string $requesterRole): bool
    {
        $comment = $this->commentModel->findById($commentId);

        if (!$comment) {
            throw new \Exception("Bình luận không tồn tại.");
        }

        $isOwner  = (int)$comment['user_id'] === $userId;
        $isAdmin  = in_array($requesterRole, ['owner', 'admin']);

        // Chủ sở hữu hoặc admin/owner mới xóa được
        if (!$isOwner && !$isAdmin) {
            throw new \Exception("Bạn không có quyền xóa bình luận này.");
        }

        return $this->commentModel->delete($commentId);
    }

    public function toggleReaction(int $commentId, int $userId, string $emoji): bool
    {
        $allowed = ['thumbs_up', 'check', 'fire', 'question'];
        if (!in_array($emoji, $allowed)) {
            throw new \Exception("Emoji không hợp lệ.");
        }

        return $this->reactionModel->toggle($commentId, $userId, $emoji);
    }
}
