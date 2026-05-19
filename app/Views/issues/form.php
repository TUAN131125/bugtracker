<?php
/**
 * Issue Form View – Tạo mới và Sửa Issue
 *
 * Dùng chung cho 2 action:
 *   - Tạo mới: IssueController::create() → $issue = null, $isEdit = false
 *   - Sửa:     IssueController::edit()   → $issue = array, $isEdit = true
 *
 * Data nhận từ Controller sau extract():
 *   $issue      – array Issue hiện tại (null nếu tạo mới)
 *   $isEdit     – bool
 *   $projects   – array danh sách Project (cho dropdown chọn Project)
 *   $members    – array Member trong Workspace (cho Assignee)
 *   $milestones – array Milestone của Project đang chọn
 *   $tags       – array Tag của Workspace
 *   $pageId     – string 'issue-form'
 *   $pageTitle  – string
 *   $csrfToken  – string
 *
 * @see SRS v1.0.0 – UC-019 (Tạo Issue), UC-022 (Sửa Issue)
 * @see ViewLayer Guide v1.0.0 – Phần 6.5, 3.1, 6.1
 * @see Task Assignment v1.0.0 – D3-018, D3-019
 */

$layout = 'app';

// Giá trị mặc định
$issue      = $issue      ?? null;
$isEdit     = $isEdit     ?? false;
$projects   = $projects   ?? [];
$members    = $members    ?? [];
$milestones = $milestones ?? [];
$tags       = $tags       ?? [];
$csrfToken  = $csrfToken  ?? '';

// Helper: lấy old input (sau validation fail) hoặc giá trị Issue hiện tại (khi edit)
// Thứ tự ưu tiên: old_input > $issue > default
$val = function (string $key, mixed $default = '') use ($issue): string {
    $old = $_SESSION['_old_input'][$key] ?? null;
    if ($old !== null) {
        return htmlspecialchars((string) $old, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if ($issue !== null && isset($issue[$key])) {
        return htmlspecialchars((string) $issue[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return htmlspecialchars((string) $default, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

// Form action URL
$formAction = $isEdit
    ? url('issues/' . htmlspecialchars($issue['issue_key'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') . '/update')
    : url('issues/store');

$formMethod   = 'POST';
$submitLabel  = $isEdit ? 'Lưu thay đổi' : 'Tạo Issue';
$pageHeading  = $isEdit ? 'Chỉnh sửa Issue ' . htmlspecialchars($issue['issue_key'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Tạo Issue mới';

// Các option cố định theo SRS
$type_options = [
    'bug'         => 'Bug',
    'task'        => 'Task',
    'enhancement' => 'Enhancement',
    'question'    => 'Question',
];

$severity_options = [
    'critical' => 'Critical',
    'major'    => 'Major',
    'minor'    => 'Minor',
    'trivial'  => 'Trivial',
];

$priority_options = [
    'urgent' => '↑↑ Khẩn cấp',
    'high'   => '↑ Cao',
    'medium' => '→ Trung bình',
    'low'    => '↓ Thấp',
];
?>

<div class="issue-form-page">

    <!-- Dữ liệu cho issue-form.js – KHÔNG viết JS inline -->
    <script type="application/json" id="page-data">
        <?= json_encode([
            'isEdit'     => $isEdit,
            'issueKey'   => $issue['issue_key'] ?? null,
            'csrfToken'  => $csrfToken,
            'milestones' => $milestones,
            'members'    => array_map(fn($m) => ['id' => $m['id'], 'name' => $m['name']], $members),
            'tags'       => array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'color' => $t['color']], $tags),
        ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
    </script>

    <!-- Header -->
    <div class="page-header">
        <div class="page-header__left">
            <a href="<?= url('issues') ?>" class="btn btn--ghost btn--sm" aria-label="Quay lại danh sách Issue">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width:16px;height:16px">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                </svg>
            </a>
            <h1 class="page-header__title"><?= $pageHeading ?></h1>
        </div>
    </div>

    <form
        id="issue-form"
        method="<?= $formMethod ?>"
        action="<?= $formAction ?>"
        enctype="multipart/form-data"
        data-form-validate
        novalidate
    >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

        <?php if ($isEdit): ?>
        <!-- Method spoofing: form HTML chỉ hỗ trợ GET/POST, dùng _method để Router nhận PUT -->
        <input type="hidden" name="_method" value="PUT">
        <?php endif; ?>

        <div class="issue-form-layout">

            <!-- ================================================================
                 CỘT TRÁI – Form chính
                 ================================================================ -->
            <div class="issue-form-main">

                <!-- TITLE -->
                <div class="form-group" id="group-title">
                    <label for="issue-title" class="form-label">
                        Tiêu đề
                        <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="issue-title"
                        name="title"
                        class="form-input"
                        value="<?= $val('title') ?>"
                        placeholder="Mô tả ngắn gọn vấn đề..."
                        data-validate="required|min:5|max:500"
                        data-validate-message='{"required":"Tiêu đề không được để trống","min":"Tiêu đề cần ít nhất 5 ký tự","max":"Tiêu đề tối đa 500 ký tự"}'
                        data-validate-on="input"
                        aria-describedby="title-error"
                        maxlength="500"
                        autofocus
                    >
                    <span class="form-error" id="title-error" role="alert" aria-live="polite"></span>
                </div>

                <!-- TYPE -->
                <div class="form-group" id="group-type">
                    <label for="issue-type" class="form-label">
                        Loại Issue
                        <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="issue-type"
                        name="type"
                        class="form-select"
                        data-validate="required"
                        aria-describedby="type-error"
                    >
                        <?php foreach ($type_options as $value => $label): ?>
                        <option
                            value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            <?= $val('type', 'bug') === $value ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-error" id="type-error" role="alert" aria-live="polite"></span>
                </div>

                <!-- DESCRIPTION với Markdown preview -->
                <div class="form-group" id="group-description">
                    <label class="form-label">Mô tả</label>

                    <!-- Tab Write / Preview -->
                    <div class="markdown-editor" data-markdown-editor>
                        <div class="markdown-editor__tabs" role="tablist">
                            <button
                                type="button"
                                id="write-tab"
                                role="tab"
                                class="markdown-editor__tab is-active"
                                aria-selected="true"
                                aria-controls="write-panel"
                                data-tab="write"
                            >
                                Soạn thảo
                            </button>
                            <button
                                type="button"
                                id="preview-tab"
                                role="tab"
                                class="markdown-editor__tab"
                                aria-selected="false"
                                aria-controls="preview-panel"
                                data-tab="preview"
                            >
                                Xem trước
                            </button>
                        </div>

                        <!-- Write panel -->
                        <div
                            id="write-panel"
                            role="tabpanel"
                            aria-labelledby="write-tab"
                            class="markdown-editor__panel"
                        >
                            <textarea
                                id="issue-description"
                                name="description"
                                class="form-input markdown-editor__textarea"
                                placeholder="Mô tả chi tiết Issue... (hỗ trợ Markdown: **bold**, *italic*, # Heading, \`code\`)"
                                rows="12"
                                aria-label="Mô tả Issue – hỗ trợ Markdown"
                                data-description-input
                            ><?= $val('description') ?></textarea>
                            <p class="form-hint">Hỗ trợ Markdown: **in đậm**, *in nghiêng*, # Tiêu đề, `code`</p>
                        </div>

                        <!-- Preview panel – marked.js render vào đây -->
                        <div
                            id="preview-panel"
                            role="tabpanel"
                            aria-labelledby="preview-tab"
                            class="markdown-editor__panel markdown-body is-hidden"
                            data-preview-output
                        >
                            <p class="markdown-editor__preview-empty">Chưa có nội dung để xem trước.</p>
                        </div>
                    </div>
                </div>

                <!-- FILE ATTACHMENT -->
                <div class="form-group" id="group-attachments">
                    <label class="form-label">File đính kèm</label>
                    <p class="form-hint">Tối đa 5 file, mỗi file không quá 2MB. Chấp nhận: JPG, PNG, GIF, PDF, TXT, ZIP.</p>

                    <div class="file-drop-zone" data-file-drop-zone>
                        <svg class="file-drop-zone__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="file-drop-zone__text">
                            Kéo file vào đây hoặc
                            <label for="attachments-input" class="link" style="cursor:pointer">chọn từ máy</label>
                        </p>
                        <input
                            type="file"
                            id="attachments-input"
                            name="attachments[]"
                            multiple
                            accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.zip"
                            class="visually-hidden"
                            data-file-input
                            aria-label="Chọn file đính kèm"
                        >
                    </div>

                    <!-- Danh sách file đã chọn – JS render -->
                    <div
                        id="attachment-preview-list"
                        class="attachment-preview-list"
                        aria-live="polite"
                        aria-label="Danh sách file đã chọn"
                    ></div>
                    <span class="form-error" id="attachment-error" role="alert" aria-live="polite"></span>
                </div>

            </div>

            <!-- ================================================================
                 CỘT PHẢI – Metadata
                 ================================================================ -->
            <div class="issue-form-sidebar">

                <!-- PROJECT (chỉ hiện khi tạo mới) -->
                <?php if (!$isEdit): ?>
                <div class="form-group" id="group-project">
                    <label for="issue-project" class="form-label">
                        Project
                        <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="issue-project"
                        name="project_id"
                        class="form-select"
                        data-validate="required"
                        data-project-select
                        aria-describedby="project-error"
                    >
                        <option value="">Chọn Project...</option>
                        <?php foreach ($projects as $project): ?>
                        <option
                            value="<?= htmlspecialchars((string) $project['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            <?= $val('project_id') === (string) $project['id'] ? 'selected' : '' ?>
                        >
                            [<?= htmlspecialchars($project['key'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>]
                            <?= htmlspecialchars($project['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-error" id="project-error" role="alert" aria-live="polite"></span>
                </div>
                <?php endif; ?>

                <!-- SEVERITY -->
                <div class="form-group" id="group-severity">
                    <label for="issue-severity" class="form-label">
                        Mức độ nghiêm trọng
                        <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="issue-severity"
                        name="severity"
                        class="form-select"
                        data-validate="required"
                        aria-describedby="severity-error"
                    >
                        <?php foreach ($severity_options as $value => $label): ?>
                        <option
                            value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            <?= $val('severity', 'major') === $value ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-error" id="severity-error" role="alert" aria-live="polite"></span>
                </div>

                <!-- PRIORITY -->
                <div class="form-group" id="group-priority">
                    <label for="issue-priority" class="form-label">
                        Độ ưu tiên
                        <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select
                        id="issue-priority"
                        name="priority"
                        class="form-select"
                        data-validate="required"
                        aria-describedby="priority-error"
                    >
                        <?php foreach ($priority_options as $value => $label): ?>
                        <option
                            value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            <?= $val('priority', 'medium') === $value ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-error" id="priority-error" role="alert" aria-live="polite"></span>
                </div>

                <!-- ASSIGNEE -->
                <div class="form-group" id="group-assignee">
                    <label for="issue-assignee" class="form-label">Assignee</label>
                    <select
                        id="issue-assignee"
                        name="assignee_id"
                        class="form-select"
                    >
                        <option value="">Không gán</option>
                        <?php foreach ($members as $member): ?>
                        <option
                            value="<?= htmlspecialchars((string) $member['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            <?= $val('assignee_id') === (string) $member['id'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($member['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- MILESTONE -->
                <?php if (!empty($milestones)): ?>
                <div class="form-group" id="group-milestone">
                    <label for="issue-milestone" class="form-label">Milestone</label>
                    <select
                        id="issue-milestone"
                        name="milestone_id"
                        class="form-select"
                    >
                        <option value="">Không có</option>
                        <?php foreach ($milestones as $milestone): ?>
                        <option
                            value="<?= htmlspecialchars((string) $milestone['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            <?= $val('milestone_id') === (string) $milestone['id'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($milestone['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- TAGS – multi-select custom (JS render pill/chip) -->
                <?php if (!empty($tags)): ?>
                <div class="form-group" id="group-tags">
                    <label class="form-label">Tags</label>

                    <!-- Tags đã chọn hiện tại (khi edit) -->
                    <?php
                    $selected_tag_ids = [];
                    if ($isEdit && !empty($issue['tags'])) {
                        $selected_tag_ids = array_column($issue['tags'], 'id');
                    }
                    // Old input override (sau validation fail)
                    if (!empty($_SESSION['_old_input']['tag_ids'])) {
                        $selected_tag_ids = (array) $_SESSION['_old_input']['tag_ids'];
                    }
                    ?>

                    <!-- Hidden inputs cho selected tags – JS cập nhật khi user chọn/bỏ -->
                    <div id="selected-tags-inputs" data-selected-tags-container>
                        <?php foreach ($selected_tag_ids as $tagId): ?>
                        <input
                            type="hidden"
                            name="tag_ids[]"
                            value="<?= htmlspecialchars((string) $tagId, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        >
                        <?php endforeach; ?>
                    </div>

                    <!-- Tag picker UI – JS render dựa trên page-data -->
                    <div
                        class="tag-picker"
                        data-tag-picker
                        data-selected='<?= htmlspecialchars(json_encode($selected_tag_ids), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'
                        aria-label="Chọn Tags"
                    >
                        <!-- Tags available – render từ $tags array -->
                        <?php foreach ($tags as $tag): ?>
                        <button
                            type="button"
                            class="tag-chip tag-chip--selectable <?= in_array($tag['id'], $selected_tag_ids, false) ? 'is-selected' : '' ?>"
                            data-tag-id="<?= htmlspecialchars((string) $tag['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            data-tag-name="<?= htmlspecialchars($tag['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            style="--tag-color: <?= htmlspecialchars($tag['color'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            aria-pressed="<?= in_array($tag['id'], $selected_tag_ids, false) ? 'true' : 'false' ?>"
                        >
                            <?= htmlspecialchars($tag['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- SUBMIT ACTIONS -->
                <div class="form-actions">
                    <button
                        type="submit"
                        class="btn btn--primary btn--full"
                        data-submit-btn
                    >
                        <span class="btn__text"><?= htmlspecialchars($submitLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                        <span class="btn__spinner" aria-hidden="true" hidden>
                            <svg class="spinner-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.416" stroke-dashoffset="31.416">
                                    <animate attributeName="stroke-dashoffset" dur="0.8s" repeatCount="indefinite" from="31.416" to="0"/>
                                </circle>
                            </svg>
                        </span>
                    </button>

                    <a
                        href="<?= url('issues') ?>"
                        class="btn btn--ghost btn--full"
                    >
                        Hủy
                    </a>
                </div>

            </div>
        </div>
    </form>
</div>