<?php
/**
 * @var string $csrf_token
 * @var array $issue        (Rỗng nếu tạo mới)
 * @var array $members      Danh sách thành viên
 * @var array $projects     Danh sách Project trong Workspace
 * @var array $errors       Mảng lỗi validation
 */
$isEdit = !empty($issue['id']);
?>

<div class="page-container max-w-4xl mx-auto">
    <div class="page-header">
        <div class="header-title">
            <h1><?= $isEdit ? 'Chỉnh sửa Issue [' . Sanitizer::escape($issue['issue_key']) . ']' : 'Tạo Issue mới' ?></h1>
        </div>
        <div class="header-actions">
            <button onclick="history.back()" class="btn btn-link text-muted">Hủy bỏ</button>
        </div>
    </div>

    <div class="card p-4">
        <form action="<?= $isEdit ? '/issues/edit/' . Sanitizer::escape($issue['id']) : '/issues/store' ?>" method="POST" enctype="multipart/form-data" id="issueForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">

            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="title">Tiêu đề bắt buộc <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" 
                           value="<?= Sanitizer::escape($issue['title'] ?? '') ?>" required>
                    <?php if (!empty($errors['title'])): ?>
                        <div class="invalid-feedback"><?= Sanitizer::escape($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="project_id">Dự án (Project) <span class="text-danger">*</span></label>
                    <select name="project_id" id="project_id" class="form-control" <?= $isEdit ? 'disabled' : 'required' ?>>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= Sanitizer::escape($project['id']) ?>" <?= ($issue['project_id'] ?? '') == $project['id'] ? 'selected' : '' ?>>
                                [<?= Sanitizer::escape($project['key']) ?>] <?= Sanitizer::escape($project['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="type">Loại Issue <span class="text-danger">*</span></label>
                    <select name="type" id="type" class="form-control">
                        <option value="bug" <?= ($issue['type'] ?? '') === 'bug' ? 'selected' : '' ?>>Bug</option>
                        <option value="task" <?= ($issue['type'] ?? '') === 'task' ? 'selected' : '' ?>>Task</option>
                        <option value="enhancement" <?= ($issue['type'] ?? '') === 'enhancement' ? 'selected' : '' ?>>Enhancement</option>
                        <option value="question" <?= ($issue['type'] ?? '') === 'question' ? 'selected' : '' ?>>Question</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="severity">Mức độ (Severity) <span class="text-danger">*</span></label>
                    <select name="severity" id="severity" class="form-control">
                        <option value="critical" <?= ($issue['severity'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="major" <?= ($issue['severity'] ?? 'major') === 'major' ? 'selected' : '' ?>>Major</option>
                        <option value="minor" <?= ($issue['severity'] ?? '') === 'minor' ? 'selected' : '' ?>>Minor</option>
                        <option value="trivial" <?= ($issue['severity'] ?? '') === 'trivial' ? 'selected' : '' ?>>Trivial</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="priority">Độ ưu tiên <span class="text-danger">*</span></label>
                    <select name="priority" id="priority" class="form-control">
                        <option value="urgent" <?= ($issue['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                        <option value="high" <?= ($issue['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= ($issue['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= ($issue['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="assignee_id">Người phụ trách</label>
                    <select name="assignee_id" id="assignee_id" class="form-control">
                        <option value="">-- Chưa gán --</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?= Sanitizer::escape($member['user_id']) ?>" <?= ($issue['assignee_id'] ?? '') == $member['user_id'] ? 'selected' : '' ?>>
                                <?= Sanitizer::escape($member['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Mô tả chi tiết (Hỗ trợ Markdown)</label>
                <textarea name="description" id="description" class="form-control font-monospace" rows="10" placeholder="Mô tả các bước tái hiện lỗi, hoặc chi tiết công việc..."><?= Sanitizer::escape($issue['description'] ?? '') ?></textarea>
                <div id="markdown-preview" class="markdown-body p-3 border rounded bg-light mt-2 d-none"></div>
                <button type="button" class="btn btn-sm btn-link mt-1" id="btnTogglePreview">Chuyển chế độ Preview</button>
            </div>

            <div class="form-group border border-dashed rounded p-4 text-center bg-light">
                <label for="attachments" class="font-weight-bold cursor-pointer d-block mb-2">Đính kèm tệp tin (Tối đa 5 file, mỗi file <= 2MB)</label>
                <input type="file" name="attachments[]" id="attachments" class="form-control-file d-inline-block" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.zip">
                <div class="text-small text-muted mt-2">Định dạng hỗ trợ: jpg, png, pdf, txt, zip.</div>
                <?php if (!empty($errors['attachments'])): ?>
                    <div class="text-danger mt-1 text-small"><?= Sanitizer::escape($errors['attachments']) ?></div>
                <?php endif; ?>
            </div>

            <hr class="mt-4 mb-4">
            
            <button type="submit" class="btn btn-primary btn-lg">
                <?= $isEdit ? 'Cập nhật thay đổi' : 'Tạo Issue' ?>
            </button>
        </form>
    </div>
</div>