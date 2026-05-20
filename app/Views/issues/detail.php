<?php
/**
 * @var string $csrf_token
 * @var array $issue        Dữ liệu chi tiết Issue
 * @var array $comments     Danh sách bình luận
 * @var array $attachments  Danh sách tệp đính kèm
 * @var array $allowed_transitions Danh sách trạng thái hợp lệ có thể chuyển sang
 */
use App\Helpers\Sanitizer;
?>

<div class="page-container issue-detail-layout">
    <div class="page-header mb-4">
        <div class="header-title">
            <div class="text-muted mb-1 text-small">
                <a href="/projects/<?= Sanitizer::escape($issue['project_id']) ?>" class="link-secondary">Project</a> / 
                <span class="font-weight-bold text-primary"><?= Sanitizer::escape($issue['issue_key']) ?></span>
            </div>
            <h1 class="d-inline-block mr-2"><?= Sanitizer::escape($issue['title']) ?></h1>
            <span class="badge status-<?= Sanitizer::escape($issue['status']) ?> fs-6 align-text-bottom">
                <?= Sanitizer::escape(strtoupper(str_replace('_', ' ', $issue['status']))) ?>
            </span>
        </div>
    </div>

    <div class="grid-layout-main-sidebar">
        
        <div class="main-content">
            <div class="card mb-4">
                <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center">
                    <div class="user-info">
                        <img src="<?= Sanitizer::escape($issue['reporter_avatar'] ?? '/assets/img/avatar-default.svg') ?>" class="avatar-sm" alt="Reporter">
                        <span class="font-weight-bold"><?= Sanitizer::escape($issue['reporter_name']) ?></span>
                        <span class="text-muted text-small ml-2">đã báo cáo vào <?= Sanitizer::escape($issue['created_at']) ?></span>
                    </div>
                    <?php if ($issue['status'] !== 'archived'): ?>
                        <a href="/issues/edit/<?= Sanitizer::escape($issue['id']) ?>" class="btn btn-sm btn-outline-secondary">Sửa mô tả</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="markdown-body text-dark">
                        <?= $issue['description_html'] ?? '<em class="text-muted">Không có mô tả chi tiết.</em>' ?>
                    </div>
                </div>
            </div>

            <div class="comments-section mt-5" id="comments">
                <h3 class="border-bottom pb-2 mb-4">Bình luận (<?= count($comments) ?>)</h3>
                
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-item d-flex mb-4">
                        <img src="<?= Sanitizer::escape($comment['user_avatar'] ?? '/assets/img/avatar-default.svg') ?>" class="avatar-md mr-3" alt="Avatar">
                        <div class="comment-content card flex-grow-1">
                            <div class="card-header bg-light py-2 px-3 text-small">
                                <span class="font-weight-bold text-dark"><?= Sanitizer::escape($comment['user_name']) ?></span>
                                <span class="text-muted ml-2"><?= Sanitizer::escape($comment['created_at']) ?></span>
                                <?php if ($comment['is_edited']): ?>
                                    <span class="text-muted ml-1 font-italic">(đã chỉnh sửa)</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body py-3 px-3 markdown-body text-dark">
                                <?= $comment['content_html'] ?>
                            </div>
                            <div class="card-footer py-1 px-3 bg-white border-top-0">
                                <button class="btn btn-sm btn-light rounded-pill btn-reaction" data-comment-id="<?= Sanitizer::escape($comment['id']) ?>">👍 <span class="count">0</span></button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="comment-form-wrapper mt-4">
                    <form action="/issues/comment/<?= Sanitizer::escape($issue['id']) ?>" method="POST" id="commentForm">
                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                        <div class="form-group">
                            <textarea name="content" class="form-control" rows="4" placeholder="Viết bình luận... Hỗ trợ @mention để tag thành viên" required></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-small text-muted">Hỗ trợ định dạng Markdown</div>
                            <button type="submit" class="btn btn-primary">Gửi bình luận</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="sidebar-content">
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white font-weight-bold">
                    Cập nhật trạng thái
                </div>
                <div class="card-body p-3">
                    <form action="/issues/status/<?= Sanitizer::escape($issue['id']) ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                        
                        <div class="form-group mb-3">
                            <select name="new_status" id="new_status" class="form-control" required>
                                <option value="">-- Chọn trạng thái mới --</option>
                                <?php foreach ($allowed_transitions as $transition): ?>
                                    <option value="<?= Sanitizer::escape($transition) ?>"><?= Sanitizer::escape(strtoupper(str_replace('_', ' ', $transition))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group d-none" id="resolutionNoteGroup">
                            <label class="text-small font-weight-bold text-dark">Ghi chú giải quyết (Bắt buộc) <span class="text-danger">*</span></label>
                            <textarea name="resolution_note" id="resolution_note" class="form-control text-small" rows="3" placeholder="Ghi tóm tắt cách bạn đã sửa lỗi này..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-sm mt-2">Cập nhật ngay</button>
                    </form>
                </div>
            </div>

            <div class="card mb-4 meta-card">
                <div class="card-header bg-light font-weight-bold">Thông tin chi tiết</div>
                <ul class="list-group list-group-flush text-small">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Phụ trách</span>
                        <span class="font-weight-bold"><?= Sanitizer::escape($issue['assignee_name'] ?? 'Chưa gán') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Mức độ (Severity)</span>
                        <span class="badge badge-light border"><?= Sanitizer::escape(ucfirst($issue['severity'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Độ ưu tiên</span>
                        <span class="badge badge-light border"><?= Sanitizer::escape(ucfirst($issue['priority'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">Milestone</span>
                        <span class="text-dark"><?= Sanitizer::escape($issue['milestone_name'] ?? '-') ?></span>
                    </li>
                </ul>
            </div>

            <div class="card mb-4 attachments-card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="font-weight-bold">Tệp đính kèm (<?= count($attachments) ?>)</span>
                </div>
                <div class="card-body p-2">
                    <?php if (empty($attachments)): ?>
                        <div class="text-muted text-center text-small py-2">Không có tệp đính kèm.</div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($attachments as $file): ?>
                                <li class="p-2 border-bottom d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center text-truncate mr-2">
                                        <span class="icon text-muted mr-2">📄</span>
                                        <a href="/attachments/download/<?= Sanitizer::escape($file['id']) ?>" class="text-small link-primary text-truncate" title="<?= Sanitizer::escape($file['original_name']) ?>">
                                            <?= Sanitizer::escape($file['original_name']) ?>
                                        </a>
                                    </div>
                                    <span class="text-muted text-small"><?= round($file['file_size'] / 1024, 1) ?> KB</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card mb-4 issue-links-card">
                <div class="card-header bg-light font-weight-bold">Liên kết công việc</div>
                <div class="card-body p-3 text-center text-small">
                    <button class="btn btn-outline-secondary btn-sm" id="btnLinkIssue">🔗 Thêm liên kết Issue</button>
                </div>
            </div>

        </div>
    </div>
</div>