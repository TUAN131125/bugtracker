<?php
/**
 * Issue Detail View
 *
 * Hiển thị chi tiết một Issue: mô tả, attachment, comment thread,
 * metadata sidebar, status transitions, activity log.
 *
 * Data nhận từ IssueController::show() sau extract():
 *   $issue             – array thông tin Issue đầy đủ
 *   $comments          – array nested (parent + children threads)
 *   $attachments       – array file đính kèm của Issue
 *   $valid_transitions – array trạng thái được phép chuyển từ status hiện tại
 *   $activity_log      – array hoạt động gần đây của Issue
 *   $tags              – array tất cả Tag của Workspace (cho dropdown assign tag)
 *   $members           – array Member trong Workspace (cho Assignee dropdown)
 *   $milestones        – array Milestone của Project
 *   $can               – array quyền: ['update_status', 'assign', 'delete', 'comment', ...]
 *   $pageId            – string 'issue-detail'
 *   $pageTitle         – string
 *   $csrfToken         – string
 *
 * @see SRS v1.0.0 – UC-021, UC-023, UC-024, UC-026
 * @see ViewLayer Guide v1.0.0 – Phần 6.4, 3.1, 6.1
 * @see Task Assignment v1.0.0 – D3-017
 */

$layout = 'app';

// Giá trị mặc định khi Dev 2 chưa implement Controller
$issue             = $issue             ?? [];
$comments          = $comments          ?? [];
$attachments       = $attachments       ?? [];
$valid_transitions = $valid_transitions ?? [];
$activity_log      = $activity_log      ?? [];
$tags              = $tags              ?? [];
$members           = $members           ?? [];
$milestones        = $milestones        ?? [];
$can               = $can               ?? [];

// Status label mapping (nhất quán với list.php)
$status_labels = [
    'open'        => 'Mới',
    'in_triage'   => 'Đang xem xét',
    'in_progress' => 'Đang xử lý',
    'resolved'    => 'Đã giải quyết',
    'closed'      => 'Đã đóng',
    'reopened'    => 'Mở lại',
    'wont_fix'    => 'Không sửa',
    'duplicate'   => 'Trùng lặp',
];

$status_classes = [
    'open'        => 'badge--open',
    'in_triage'   => 'badge--triage',
    'in_progress' => 'badge--in-progress',
    'resolved'    => 'badge--resolved',
    'closed'      => 'badge--closed',
    'reopened'    => 'badge--reopened',
    'wont_fix'    => 'badge--wontfix',
    'duplicate'   => 'badge--duplicate',
];

$priority_icons = [
    'urgent' => '↑↑',
    'high'   => '↑',
    'medium' => '→',
    'low'    => '↓',
];

$current_status       = $issue['status']   ?? 'open';
$current_status_label = $status_labels[$current_status] ?? $current_status;
$current_status_class = $status_classes[$current_status] ?? 'badge--open';
$issue_key            = htmlspecialchars($issue['issue_key'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>

<div class="issue-detail-page">

    <!-- Truyền dữ liệu cho issue-detail.js (KHÔNG viết JS inline) -->
    <script type="application/json" id="page-data">
        <?= json_encode([
            'issueId'          => $issue['id'] ?? null,
            'issueKey'         => $issue['issue_key'] ?? '',
            'currentStatus'    => $current_status,
            'validTransitions' => $valid_transitions,
            'can'              => $can,
            'csrfToken'        => $csrfToken ?? '',
        ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
    </script>

    <!-- ================================================================
         BREADCRUMB
         ================================================================ -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= url('projects') ?>" class="breadcrumb__item">Projects</a>
        <span class="breadcrumb__sep" aria-hidden="true">/</span>
        <a
            href="<?= url('issues?project_id=' . htmlspecialchars((string) ($issue['project_id'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>"
            class="breadcrumb__item"
        >
            <?= htmlspecialchars($issue['project_name'] ?? 'Project', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
        </a>
        <span class="breadcrumb__sep" aria-hidden="true">/</span>
        <span class="breadcrumb__item breadcrumb__item--current" aria-current="page">
            <?= $issue_key ?>
        </span>
    </nav>

    <div class="issue-detail-layout">

        <!-- ================================================================
             MAIN CONTENT (trái 65%)
             ================================================================ -->
        <div class="issue-detail-main">

            <!-- Issue Header -->
            <div class="issue-detail-header">
                <div class="issue-detail-header__meta">
                    <span class="issue-key-badge" title="Sao chép ID" data-copy-text="<?= $issue_key ?>">
                        <?= $issue_key ?>
                    </span>
                    <span class="badge <?= htmlspecialchars($current_status_class, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                          aria-label="Trạng thái: <?= htmlspecialchars($current_status_label, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                        <?= htmlspecialchars($current_status_label, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </span>
                </div>

                <h1 class="issue-detail-header__title" id="issue-title">
                    <?= htmlspecialchars($issue['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                </h1>
            </div>

            <!-- ============================================================
                 STATUS TRANSITION BUTTONS
                 Chỉ render các trạng thái HỢP LỆ từ state machine (SRS 3.3.1)
                 $valid_transitions do IssueController cung cấp, tính theo
                 (current_status + user_role). Không tự hardcode transitions.
                 ============================================================ -->
            <?php if (!empty($valid_transitions) && ($can['update_status'] ?? false)): ?>
            <div class="issue-transitions" aria-label="Chuyển trạng thái">
                <span class="issue-transitions__label">Chuyển sang:</span>
                <div class="issue-transitions__buttons" role="group">
                    <?php foreach ($valid_transitions as $transition): ?>
                    <button
                        type="button"
                        class="btn btn--sm btn--outline transition-btn"
                        data-transition-status="<?= htmlspecialchars($transition['status'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        data-transition-label="<?= htmlspecialchars($transition['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        data-requires-note="<?= isset($transition['requires_note']) && $transition['requires_note'] ? 'true' : 'false' ?>"
                        data-requires-duplicate="<?= isset($transition['requires_duplicate']) && $transition['requires_duplicate'] ? 'true' : 'false' ?>"
                    >
                        <?= htmlspecialchars($transition['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================================
                 DESCRIPTION (Markdown – rendered bởi marked.js client-side)
                 Server lưu raw markdown. Client parse bằng marked.js.
                 WHY không render server-side: tránh phụ thuộc thư viện PHP
                 nặng trên InfinityFree. marked.js chỉ load trang này.
                 ============================================================ -->
            <div class="issue-detail-section">
                <h2 class="issue-detail-section__title">Mô tả</h2>

                <?php if (!empty($issue['description'])): ?>
                <!-- Raw markdown lưu trong data attribute để JS đọc và parse -->
                <!-- JSON_HEX_TAG escape < > để chống XSS -->
                <div
                    class="markdown-body"
                    id="issue-description"
                    data-markdown="<?= htmlspecialchars($issue['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                    aria-label="Mô tả Issue"
                >
                    <!-- marked.js inject HTML vào đây sau khi parse markdown -->
                    <p class="markdown-body__loading">Đang tải nội dung...</p>
                </div>
                <?php else: ?>
                <p class="issue-detail-section__empty">Chưa có mô tả.</p>
                <?php endif; ?>
            </div>

            <!-- ============================================================
                 ATTACHMENTS
                 ============================================================ -->
            <?php if (!empty($attachments)): ?>
            <div class="issue-detail-section">
                <h2 class="issue-detail-section__title">
                    File đính kèm
                    <span class="badge badge--neutral"><?= htmlspecialchars((string) count($attachments), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                </h2>
                <div class="attachment-gallery">
                    <?php foreach ($attachments as $att): ?>
                    <div class="attachment-item" data-attachment-id="<?= htmlspecialchars((string) $att['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

                        <?php if (!empty($att['thumbnail_url'])): ?>
                        <!-- Ảnh: hiển thị thumbnail, click mở full-size -->
                        <a
                            href="<?= htmlspecialchars($att['url'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            class="attachment-item__thumb-link"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Xem ảnh <?= htmlspecialchars($att['original_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        >
                            <img
                                src="<?= htmlspecialchars($att['thumbnail_url'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($att['original_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                class="attachment-item__thumb"
                                loading="lazy"
                            >
                        </a>
                        <?php else: ?>
                        <!-- File không phải ảnh: hiển thị icon + tên file -->
                        <a
                            href="<?= htmlspecialchars($att['url'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            class="attachment-item__file-link"
                            download
                            aria-label="Tải xuống <?= htmlspecialchars($att['original_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        >
                            <svg class="attachment-item__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </a>
                        <?php endif; ?>

                        <div class="attachment-item__info">
                            <span class="attachment-item__name">
                                <?= htmlspecialchars($att['original_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </span>
                            <span class="attachment-item__size">
                                <?= htmlspecialchars(format_bytes((int) ($att['file_size'] ?? 0)), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </span>
                        </div>

                        <?php if ($can['delete_attachment'] ?? false): ?>
                        <button
                            type="button"
                            class="attachment-item__delete"
                            data-delete-attachment="<?= htmlspecialchars((string) $att['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            aria-label="Xóa file <?= htmlspecialchars($att['original_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        >
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Upload attachment thêm vào Issue -->
            <?php if ($can['upload_attachment'] ?? false): ?>
            <div class="issue-detail-section">
                <h2 class="issue-detail-section__title">Thêm file đính kèm</h2>
                <form
                    id="attachment-upload-form"
                    data-upload-form
                    data-issue-key="<?= $issue_key ?>"
                    enctype="multipart/form-data"
                >
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                    <div class="file-drop-zone" data-file-drop-zone>
                        <svg class="file-drop-zone__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="file-drop-zone__text">
                            Kéo file vào đây hoặc
                            <label for="attachment-input" class="link" style="cursor:pointer">chọn file</label>
                        </p>
                        <p class="file-drop-zone__hint">JPG, PNG, GIF, PDF, TXT, ZIP – tối đa 2MB/file, 5 file</p>
                        <input
                            type="file"
                            id="attachment-input"
                            name="attachments[]"
                            multiple
                            accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.zip"
                            class="visually-hidden"
                            data-file-input
                        >
                    </div>
                    <div id="attachment-preview-list" class="attachment-preview-list" aria-live="polite"></div>
                    <button type="submit" class="btn btn--secondary btn--sm" data-submit-btn disabled>
                        <span class="btn__text">Tải lên</span>
                        <span class="btn__spinner" aria-hidden="true" hidden></span>
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- ============================================================
                 COMMENT THREAD
                 ============================================================ -->
            <div class="issue-detail-section" id="comments-section">
                <h2 class="issue-detail-section__title">
                    Bình luận
                    <?php if (!empty($comments)): ?>
                    <span class="badge badge--neutral"><?= htmlspecialchars((string) count($comments), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                    <?php endif; ?>
                </h2>

                <!-- Comment list -->
                <div class="comment-thread" id="comment-thread" aria-live="polite" aria-label="Thread bình luận">
                    <?php if (empty($comments)): ?>
                    <p class="issue-detail-section__empty">Chưa có bình luận nào. Hãy là người đầu tiên!</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                        <?php
                        // Include partial _comment.php
                        // Partial nhận $comment (array có key: id, user, content,
                        // created_at, is_edited, replies, reactions)
                        $comment_data     = $comment;
                        $comment_can      = $can;
                        include VIEWS_PATH . '/partials/_comment.php';
                        ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Comment form (chỉ render nếu có quyền comment) -->
                <?php if ($can['comment'] ?? false): ?>
                <div class="comment-form-wrapper">
                    <form
                        id="comment-form"
                        data-comment-form
                        data-issue-id="<?= htmlspecialchars((string) ($issue['id'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                    >
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                        <input type="hidden" name="parent_comment_id" id="parent-comment-id" value="">

                        <!-- Reply indicator (ẩn mặc định, hiện khi đang reply) -->
                        <div class="comment-reply-indicator is-hidden" id="reply-indicator">
                            <span>Đang trả lời: <strong id="reply-to-name"></strong></span>
                            <button type="button" data-cancel-reply class="btn btn--xs btn--ghost" aria-label="Hủy trả lời">Hủy</button>
                        </div>

                        <div class="comment-form__input-area">
                            <textarea
                                id="comment-content"
                                name="content"
                                class="form-input comment-form__textarea"
                                placeholder="Thêm bình luận... (hỗ trợ @mention)"
                                rows="3"
                                data-validate="required"
                                data-validate-message='{"required":"Nội dung bình luận không được để trống"}'
                                aria-label="Nội dung bình luận"
                            ></textarea>

                            <!-- @mention autocomplete dropdown (JS inject) -->
                            <div
                                class="mention-dropdown is-hidden"
                                id="mention-dropdown"
                                role="listbox"
                                aria-label="Gợi ý mention thành viên"
                            ></div>
                        </div>

                        <div class="comment-form__actions">
                            <button
                                type="submit"
                                class="btn btn--primary btn--sm"
                                data-submit-btn
                            >
                                <span class="btn__text">Gửi bình luận</span>
                                <span class="btn__spinner" aria-hidden="true" hidden></span>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- ============================================================
                 ACTIVITY LOG (tab mini)
                 ============================================================ -->
            <div class="issue-detail-section">
                <h2 class="issue-detail-section__title">Lịch sử hoạt động</h2>

                <?php if (empty($activity_log)): ?>
                <p class="issue-detail-section__empty">Chưa có hoạt động nào được ghi nhận.</p>
                <?php else: ?>
                <ol class="activity-timeline" aria-label="Lịch sử hoạt động của Issue">
                    <?php foreach ($activity_log as $log): ?>
                    <li class="activity-timeline__item">
                        <div class="activity-timeline__avatar">
                            <span class="avatar avatar--xs" aria-hidden="true">
                                <?= htmlspecialchars(mb_substr($log['actor_name'] ?? '?', 0, 1), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </span>
                        </div>
                        <div class="activity-timeline__body">
                            <p class="activity-timeline__text">
                                <strong><?= htmlspecialchars($log['actor_name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong>
                                <?= htmlspecialchars($log['description'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </p>
                            <time
                                class="activity-timeline__time"
                                datetime="<?= htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                title="<?= htmlspecialchars($log['created_at'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars(time_ago($log['created_at'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </time>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </div>

        </div>

        <!-- ================================================================
             METADATA SIDEBAR (phải 35%)
             ================================================================ -->
        <aside class="issue-detail-sidebar" aria-label="Thông tin Issue">

            <!-- Assignee -->
            <div class="sidebar-field">
                <h3 class="sidebar-field__label">Assignee</h3>
                <div class="sidebar-field__value" id="assignee-display">
                    <?php if (!empty($issue['assignee_id'])): ?>
                    <div class="user-chip" data-user-id="<?= htmlspecialchars((string) $issue['assignee_id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                        <span class="avatar avatar--sm" aria-hidden="true">
                            <?= htmlspecialchars(mb_substr($issue['assignee_name'] ?? '?', 0, 1), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </span>
                        <?= htmlspecialchars($issue['assignee_name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </div>
                    <?php else: ?>
                    <span class="sidebar-field__empty">Chưa gán</span>
                    <?php endif; ?>
                </div>

                <?php if ($can['assign'] ?? false): ?>
                <select
                    class="form-select form-select--sm"
                    id="assignee-select"
                    data-assignee-select
                    aria-label="Chọn Assignee"
                >
                    <option value="">Không gán</option>
                    <?php foreach ($members as $member): ?>
                    <option
                        value="<?= htmlspecialchars((string) $member['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        <?= (string) ($issue['assignee_id'] ?? '') === (string) $member['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($member['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <!-- Priority -->
            <div class="sidebar-field">
                <h3 class="sidebar-field__label">Độ ưu tiên</h3>
                <div class="sidebar-field__value">
                    <?php
                    $priority      = $issue['priority'] ?? 'medium';
                    $priority_icon = $priority_icons[$priority] ?? '→';
                    ?>
                    <span
                        class="priority-label priority--<?= htmlspecialchars($priority, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        aria-label="Độ ưu tiên: <?= htmlspecialchars(ucfirst($priority), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                    >
                        <span aria-hidden="true"><?= htmlspecialchars($priority_icon, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                        <?= htmlspecialchars(ucfirst($priority), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </span>
                </div>
            </div>

            <!-- Severity -->
            <div class="sidebar-field">
                <h3 class="sidebar-field__label">Mức độ nghiêm trọng</h3>
                <div class="sidebar-field__value">
                    <span
                        class="badge badge--<?= htmlspecialchars($issue['severity'] ?? 'major', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        aria-label="Severity: <?= htmlspecialchars(ucfirst($issue['severity'] ?? 'major'), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                    >
                        <?= htmlspecialchars(ucfirst($issue['severity'] ?? 'major'), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </span>
                </div>
            </div>

            <!-- Milestone -->
            <div class="sidebar-field">
                <h3 class="sidebar-field__label">Milestone</h3>
                <div class="sidebar-field__value">
                    <?php if (!empty($issue['milestone_name'])): ?>
                    <a
                        href="<?= url('milestones/' . htmlspecialchars((string) ($issue['milestone_id'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>"
                        class="link"
                    >
                        <?= htmlspecialchars($issue['milestone_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </a>
                    <?php else: ?>
                    <span class="sidebar-field__empty">Không có</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tags -->
            <div class="sidebar-field">
                <h3 class="sidebar-field__label">Tags</h3>
                <div class="sidebar-field__value sidebar-field__value--tags" id="issue-tags-display">
                    <?php if (!empty($issue['tags'])): ?>
                        <?php foreach ($issue['tags'] as $tag): ?>
                        <span
                            class="tag-chip"
                            style="--tag-color: <?= htmlspecialchars($tag['color'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        >
                            <?= htmlspecialchars($tag['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <span class="sidebar-field__empty">Không có tag</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reporter -->
            <div class="sidebar-field">
                <h3 class="sidebar-field__label">Reporter</h3>
                <div class="sidebar-field__value">
                    <div class="user-chip">
                        <span class="avatar avatar--sm" aria-hidden="true">
                            <?= htmlspecialchars(mb_substr($issue['reporter_name'] ?? '?', 0, 1), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </span>
                        <?= htmlspecialchars($issue['reporter_name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </div>
                </div>
            </div>

            <!-- Created / Updated -->
            <div class="sidebar-field">
                <h3 class="sidebar-field__label">Tạo lúc</h3>
                <div class="sidebar-field__value">
                    <time
                        datetime="<?= htmlspecialchars($issue['created_at'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        title="<?= htmlspecialchars($issue['created_at'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                    >
                        <?= htmlspecialchars(time_ago($issue['created_at'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </time>
                </div>
            </div>

            <div class="sidebar-field">
                <h3 class="sidebar-field__label">Cập nhật lúc</h3>
                <div class="sidebar-field__value">
                    <time
                        datetime="<?= htmlspecialchars($issue['updated_at'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        title="<?= htmlspecialchars($issue['updated_at'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                    >
                        <?= htmlspecialchars(time_ago($issue['updated_at'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </time>
                </div>
            </div>

            <!-- Danger Zone – chỉ hiện với Admin/Owner -->
            <?php if ($can['delete'] ?? false): ?>
            <div class="sidebar-field sidebar-field--danger">
                <h3 class="sidebar-field__label">Vùng nguy hiểm</h3>
                <button
                    type="button"
                    class="btn btn--danger btn--sm btn--full"
                    data-delete-issue="<?= htmlspecialchars((string) ($issue['id'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                    data-issue-key="<?= $issue_key ?>"
                    aria-label="Xóa Issue <?= $issue_key ?>"
                >
                    Xóa Issue
                </button>
            </div>
            <?php endif; ?>

        </aside>
    </div>
</div>

<!-- ================================================================
     MODAL: Resolution Note (bắt buộc khi chuyển sang Closed)
     modal.js quản lý open/close/focus trap
     ================================================================ -->
<dialog
    class="modal"
    id="resolution-note-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="resolution-note-modal-title"
>
    <div class="modal__content">
        <div class="modal__header">
            <h2 class="modal__title" id="resolution-note-modal-title">Ghi chú giải quyết</h2>
            <button type="button" class="modal__close" data-modal-close aria-label="Đóng">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
        <div class="modal__body">
            <p>Mô tả ngắn gọn cách bạn đã giải quyết Issue này.</p>
            <textarea
                id="resolution-note-input"
                class="form-input"
                rows="4"
                placeholder="Ví dụ: Đã sửa lỗi validate email trong RegisterController..."
                aria-label="Ghi chú giải quyết (bắt buộc)"
            ></textarea>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Hủy</button>
            <button type="button" class="btn btn--primary" id="confirm-transition-btn" data-submit-btn>
                <span class="btn__text">Xác nhận</span>
                <span class="btn__spinner" aria-hidden="true" hidden></span>
            </button>
        </div>
    </div>
</dialog>

<!-- MODAL: Nhập Issue ID khi chuyển status = duplicate -->
<dialog
    class="modal"
    id="duplicate-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="duplicate-modal-title"
>
    <div class="modal__content">
        <div class="modal__header">
            <h2 class="modal__title" id="duplicate-modal-title">Đánh dấu Trùng lặp</h2>
            <button type="button" class="modal__close" data-modal-close aria-label="Đóng">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
        <div class="modal__body">
            <p>Nhập ID của Issue gốc mà Issue này trùng lặp với.</p>
            <input
                type="text"
                id="duplicate-issue-id-input"
                class="form-input"
                placeholder="VD: BT-042"
                aria-label="ID Issue gốc"
            >
            <p class="form-error" id="duplicate-issue-error" role="alert" aria-live="polite"></p>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Hủy</button>
            <button type="button" class="btn btn--primary" id="confirm-duplicate-btn" data-submit-btn>
                <span class="btn__text">Xác nhận</span>
                <span class="btn__spinner" aria-hidden="true" hidden></span>
            </button>
        </div>
    </div>
</dialog>