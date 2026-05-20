<?php
/**
 * @var array $logs         Danh sách log lấy từ SystemLog Model
 * @var array $filters      Các bộ lọc hiện tại (level, context)
 * @var string $csrf_token  Token chống tấn công CSRF
 */
?>

<div class="admin-container">
    <div class="page-header">
        <div class="header-title">
            <h1>Nhật ký Hệ thống (System Logs)</h1>
            [cite_start]<p class="subtitle">Giải pháp xem và giám sát lỗi vận hành thời gian thực thay thế cho SSH truy cập file[cite: 458, 481].</p>
        </div>
        <div class="header-actions">
            <form action="/admin/system-logs/cleanup" method="POST" class="inline-form" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tất cả dữ liệu nhật ký hệ thống cũ hơn 3 tháng?');">
                <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                <button type="submit" class="btn btn-danger-outline">
                    [cite_start]<span class="icon">🗑️</span> Dọn dẹp log cũ (>3 tháng) [cite: 439, 481]
                </button>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <form action="/admin/system-logs" method="GET" class="filter-form">
            <div class="form-group">
                <label for="level">Cấp độ lỗi (Level)</label>
                <select name="level" id="level" class="form-control" onchange="this.form.submit()">
                    <option value="">Tất cả cấp độ</option>
                    <option value="DEBUG" <?= ($filters['level'] ?? '') === 'DEBUG' ? 'selected' : '' ?>>DEBUG</option>
                    <option value="INFO" <?= ($filters['level'] ?? '') === 'INFO' ? 'selected' : '' ?>>INFO</option>
                    <option value="WARNING" <?= ($filters['level'] ?? '') === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                    <option value="ERROR" <?= ($filters['level'] ?? '') === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                    <option value="CRITICAL" <?= ($filters['level'] ?? '') === 'CRITICAL' ? 'selected' : '' ?>>CRITICAL</option>
                </select>
            </div>
            <div class="form-group">
                <label for="context">Ngữ cảnh (Context)</label>
                <input type="text" name="context" id="context" class="form-control" placeholder="Ví dụ: EmailService, AuthController..." value="<?= Sanitizer::escape($filters['context'] ?? '') ?>">
            </div>
            <div class="form-group btn-group-filter">
                <button type="submit" class="btn btn-secondary">Tìm kiếm</button>
                <a href="/admin/system-logs" class="btn btn-link">Đặt lại</a>
            </div>
        </form>
    </div>

    <div class="card table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th style="width: 120px;">Cấp độ</th>
                    <th style="width: 180px;">Ngữ cảnh</th>
                    <th>Thông điệp hệ thống</th>
                    <th style="width: 180px;">Thời gian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center empty-state">Không tìm thấy bản ghi nhật ký hệ thống nào phù hợp.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php 
                            // Định nghĩa màu sắc nhãn badge theo mức độ nghiêm trọng
                            $levelClass = 'badge-secondary';
                            if ($log['level'] === 'CRITICAL') $levelClass = 'badge-danger bg-dark';
                            elseif ($log['level'] === 'ERROR') $levelClass = 'badge-danger';
                            elseif ($log['level'] === 'WARNING') $levelClass = 'badge-warning';
                            elseif ($log['level'] === 'INFO') $levelClass = 'badge-info';
                        ?>
                        <tr class="log-row-header">
                            <td>#<?= Sanitizer::escape($log['id']) ?></td>
                            <td><span class="badge <?= $levelClass ?>"><?= Sanitizer::escape($log['level']) ?></span></td>
                            <td><code class="text-primary"><?= Sanitizer::escape($log['context'] ?? 'Global') ?></code></td>
                            <td>
                                <div class="log-message-summary font-weight-bold">
                                    <?= Sanitizer::escape($log['message']) ?>
                                </div>
                                <?php if (!empty($log['trace'])): ?>
                                    <details class="log-trace-details">
                                        <summary class="text-small text-muted cursor-pointer">Xem Stack Trace</summary>
                                        <pre class="code-preview text-small text-danger bg-light p-2 mt-1 rounded"><code><?= Sanitizer::escape($log['trace']) ?></code></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-small text-muted" title="<?= Sanitizer::escape($log['created_at']) ?>"><?= Sanitizer::escape($log['created_at']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>