<?php
/**
 * @var array $issues       Danh sách Issue đã phân trang
 * @var array $filters      Mảng trạng thái bộ lọc hiện tại
 * @var array $tags         Danh sách Tags trong Workspace
 * @var array $members      Danh sách Member để gán bộ lọc
 */
?>

<div class="page-container">
    <div class="page-header">
        <div class="header-title">
            <h1>Danh sách công việc & lỗi</h1>
            <p class="subtitle">Quản lý và theo dõi toàn bộ Issue trong không gian làm việc.</p>
        </div>
        <div class="header-actions">
            <a href="/issues/create" class="btn btn-primary">
                <span class="icon">+</span> Tạo Issue mới
            </a>
        </div>
    </div>

    <div class="card filter-card mb-4">
        <form action="/issues" method="GET" class="filter-form grid-filter">
            <div class="form-group search-group">
                <label for="search">Tìm kiếm</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Nhập tiêu đề hoặc ID (VD: BT-001)..." value="<?= Sanitizer::escape($filters['search'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <option value="open" <?= ($filters['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="resolved" <?= ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="closed" <?= ($filters['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>

            <div class="form-group">
                <label for="assignee">Người phụ trách</label>
                <select name="assignee" id="assignee" class="form-control" onchange="this.form.submit()">
                    <option value="">Bất kỳ ai</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= Sanitizer::escape($member['user_id']) ?>" <?= ($filters['assignee'] ?? '') == $member['user_id'] ? 'selected' : '' ?>>
                            <?= Sanitizer::escape($member['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group btn-group-filter align-self-end">
                <button type="submit" class="btn btn-secondary">Lọc</button>
                <a href="/issues" class="btn btn-link">Xóa lọc</a>
            </div>
        </form>
    </div>

    <div class="card table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 100px;">Key ID</th>
                    <th>Tiêu đề</th>
                    <th>Trạng thái</th>
                    <th>Ưu tiên</th>
                    <th>Người phụ trách</th>
                    <th>Cập nhật lúc</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($issues)): ?>
                    <tr>
                        <td colspan="6" class="text-center empty-state py-5">
                            <img src="/assets/img/empty-state/no-issues.svg" alt="No Issues" class="img-empty-state mb-3">
                            <p class="text-muted">Không tìm thấy công việc hay lỗi nào khớp với bộ lọc.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($issues as $issue): ?>
                        <tr class="cursor-pointer hover-bg" onclick="window.location='/issues/<?= Sanitizer::escape($issue['issue_key']) ?>'">
                            <td><span class="font-weight-bold text-primary"><?= Sanitizer::escape($issue['issue_key']) ?></span></td>
                            <td>
                                <strong class="text-dark d-block"><?= Sanitizer::escape($issue['title']) ?></strong>
                                <div class="issue-tags mt-1">
                                    <span class="badge badge-light-secondary border text-small type-<?= Sanitizer::escape($issue['type']) ?>"><?= Sanitizer::escape(ucfirst($issue['type'])) ?></span>
                                    </div>
                            </td>
                            <td>
                                <span class="badge status-<?= Sanitizer::escape($issue['status']) ?>">
                                    <?= Sanitizer::escape(strtoupper(str_replace('_', ' ', $issue['status']))) ?>
                                </span>
                            </td>
                            <td>
                                <span class="priority-icon priority-<?= Sanitizer::escape($issue['priority']) ?>" title="<?= Sanitizer::escape(ucfirst($issue['priority'])) ?>">
                                    <?= Sanitizer::escape(ucfirst($issue['priority'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($issue['assignee_name'])): ?>
                                    <div class="user-info-cell">
                                        <img src="<?= Sanitizer::escape($issue['assignee_avatar'] ?? '/assets/img/avatar-default.svg') ?>" class="avatar-sm" alt="Avatar">
                                        <span class="text-small"><?= Sanitizer::escape($issue['assignee_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted text-small">Chưa gán</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-small text-muted"><?= Sanitizer::escape($issue['updated_at']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if (!empty($pagination)): ?>
            <div class="pagination-wrapper p-3 border-top">
                <?php include __DIR__ . '/../partials/_pagination.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>