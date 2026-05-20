<?php
/**
 * @var array $emails       Danh sách email lấy từ EmailQueue Model
 * @var array $filters      Các bộ lọc hiện tại (status, search)
 * @var string $csrf_token  Token chống tấn công CSRF
 */
?>

<div class="admin-container">
    <div class="page-header">
        <div class="header-title">
            <h1>Quản lý Hàng đợi Email</h1>
            [cite_start]<p class="subtitle">Theo dõi trạng thái và gửi lại các email bị lỗi do giới hạn hosting[cite: 342, 481].</p>
        </div>
        <div class="header-actions">
            <form action="/admin/email-queue/retry-all" method="POST" class="inline-form" onsubmit="return confirm('Bạn có chắc muốn thử gửi lại tối đa 10 email lỗi gần nhất?');">
                <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                <button type="submit" class="btn btn-primary">
                    [cite_start]<span class="icon">🔄</span> Gửi lại email lỗi (Batch 10) [cite: 481]
                </button>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <form action="/admin/email-queue" method="GET" class="filter-form">
            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending (Chờ gửi)</option>
                    <option value="sent" <?= ($filters['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Sent (Đã gửi)</option>
                    <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Failed (Thất bại)</option>
                </select>
            </div>
            <div class="form-group search-group">
                <label for="search">Tìm kiếm người nhận</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Nhập email người nhận..." value="<?= Sanitizer::escape($filters['search'] ?? '') ?>">
            </div>
            <div class="form-group btn-group-filter">
                <button type="submit" class="btn btn-secondary">Lọc kết quả</button>
                <a href="/admin/email-queue" class="btn btn-link">Xóa bộ lọc</a>
            </div>
        </form>
    </div>

    <div class="card table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Người nhận</th>
                    <th>Tiêu đề</th>
                    <th>Trạng thái</th>
                    <th>Số lần thử</th>
                    <th>Lỗi cuối cùng</th>
                    <th>Ngày tạo</th>
                    <th class="text-center">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($emails)): ?>
                    <tr>
                        <td colspan="8" class="text-center empty-state">Hàng đợi email hiện tại đang trống.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($emails as $email): ?>
                        <tr>
                            <td>#<?= Sanitizer::escape($email['id']) ?></td>
                            <td>
                                <strong class="text-dark"><?= Sanitizer::escape($email['to_name'] ?? '') ?></strong>
                                <div class="text-muted text-small"><?= Sanitizer::escape($email['to_email']) ?></div>
                            </td>
                            <td><span class="text-truncate" title="<?= Sanitizer::escape($email['subject']) ?>"><?= Sanitizer::escape($email['subject']) ?></span></td>
                            <td>
                                <?php if ($email['status'] === 'sent'): ?>
                                    <span class="badge badge-success">Sent</span>
                                <?php elseif ($email['status'] === 'pending'): ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= Sanitizer::escape($email['attempts']) ?></td>
                            <td>
                                <?php if (!empty($email['last_error'])): ?>
                                    <span class="text-danger text-small text-break" title="<?= Sanitizer::escape($email['last_error']) ?>">
                                        <?= Sanitizer::escape(substr($email['last_error'], 0, 50)) . (strlen($email['last_error']) > 50 ? '...' : '') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-small"><?= Sanitizer::escape($email['created_at']) ?></span></td>
                            <td class="text-center">
                                <?php if ($email['status'] === 'failed'): ?>
                                    <form action="/admin/email-queue/retry/<?= Sanitizer::escape($email['id']) ?>" method="POST" onsubmit="return confirm('Gửi lại email này ngay lập tức?');">
                                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                                        <button type="submit" class="btn btn-sm btn-icon-only" title="Thử gửi lại">🔄</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-icon-only disabled" disabled title="Không cần xử lý">✔️</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>