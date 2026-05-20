<?php
/**
 * @var array $stats                    Mảng chứa số liệu cấu hình (total, open, in_progress, closed)
 * @var string $status_distribution_json Chuỗi JSON phân phối trạng thái để nạp vào Donut Chart
 * @var string $weekly_trends_json       Chuỗi JSON xu hướng lỗi theo tuần để nạp vào Line Chart
 * @var array $top_assignees            Danh sách 5 Developer xử lý nhiều lỗi nhất
 * @var array $top_reporters            Danh sách 5 Thành viên phát hiện nhiều lỗi nhất
 * @var array $recent_activities         Danh sách 10 hoạt động gần nhất trong Workspace
 */
use App\Helpers\Sanitizer;
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="header-title">
            <h1>Bảng điều khiển không gian</h1>
            [cite_start]<p class="subtitle">Giám sát tổng quan năng suất làm việc, tiến độ dự án và chu trình xử lý lỗi[cite: 82, 255].</p>
        </div>
        <div class="header-actions">
            <span class="badge badge-info bg-light text-dark p-2 font-weight-bold">
                📅 Hôm nay: <?= Sanitizer::escape(date('d/m/Y')) ?>
            </span>
        </div>
    </div>

    <div class="dashboard-grid grid-quad">
        <div class="card stat-card border-left-primary">
            <div class="stat-content">
                <span class="stat-label">Tổng số Issue</span>
                <h3 class="stat-value text-primary"><?= Sanitizer::escape($stats['total'] ?? 0) ?></h3>
            </div>
            <div class="stat-icon bg-light-primary">📋</div>
        </div>

        <div class="card stat-card border-left-danger">
            <div class="stat-content">
                <span class="stat-label">Issue Đang Mở</span>
                <h3 class="stat-value text-danger"><?= Sanitizer::escape($stats['open'] ?? 0) ?></h3>
            </div>
            <div class="stat-icon bg-light-danger">🔓</div>
        </div>

        <div class="card stat-card border-left-warning">
            <div class="stat-content">
                <span class="stat-label">Đang Giải Quyết</span>
                <h3 class="stat-value text-warning"><?= Sanitizer::escape($stats['in_progress'] ?? 0) ?></h3>
            </div>
            <div class="stat-icon bg-light-warning">⚡</div>
        </div>

        <div class="card stat-card border-left-success">
            <div class="stat-content">
                <span class="stat-label">Đã Đóng (30 ngày)</span>
                <h3 class="stat-value text-success"><?= Sanitizer::escape($stats['closed'] ?? 0) ?></h3>
            </div>
            <div class="stat-icon bg-light-success">✅</div>
        </div>
    </div>

    <div class="dashboard-grid grid-dual mt-4">
        <div class="card chart-card">
            <div class="card-header">
                <h3 class="card-title">Xu hướng Issue tạo mới (3 tháng qua)</h3>
            </div>
            <div class="card-body canvas-container">
                <canvas id="weeklyTrendsChart" data-chart="<?= Sanitizer::escape($weekly_trends_json) ?>"></canvas>
            </div>
        </div>

        <div class="card chart-card">
            <div class="card-header">
                <h3 class="card-title">Phân phối trạng thái hiện tại</h3>
            </div>
            <div class="card-body canvas-container canvas-center">
                <canvas id="statusDistributionChart" data-chart="<?= Sanitizer::escape($status_distribution_json) ?>"></canvas>
            </div>
        </div>
    </div>

    <div class="dashboard-grid grid-dual mt-4">
        <div class="card table-card">
            <div class="card-header">
                <h3 class="card-title">Top 5 Kỹ sư xử lý lỗi nhiều nhất</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Thành viên</th>
                        <th class="text-center" style="width: 120px;">Đã giải quyết</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_assignees)): ?>
                        <tr>
                            [cite_start]<td colspan="2" class="text-center empty-state text-muted">Chưa có dữ liệu phân công lỗi[cite: 261].</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($top_assignees as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info-cell">
                                        [cite_start]<img src="<?= Sanitizer::escape($user['avatar_path'] ?? '/assets/img/avatar-default.svg') ?>" class="avatar-sm" alt="Avatar"> [cite: 49]
                                        <span class="font-weight-bold text-dark"><?= Sanitizer::escape($user['name']) ?></span>
                                    </div>
                                </td>
                                <td class="text-center"><span class="badge badge-success"><?= Sanitizer::escape($user['resolved_count']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card table-card">
            <div class="card-header">
                <h3 class="card-title">Top 5 Thành viên phát hiện lỗi</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Thành viên</th>
                        <th class="text-center" style="width: 120px;">Số Issue tạo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_reporters)): ?>
                        <tr>
                            [cite_start]<td colspan="2" class="text-center empty-state text-muted">Chưa có dữ liệu phát hiện lỗi[cite: 260].</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($top_reporters as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info-cell">
                                        [cite_start]<img src="<?= Sanitizer::escape($user['avatar_path'] ?? '/assets/img/avatar-default.svg') ?>" class="avatar-sm" alt="Avatar"> [cite: 49]
                                        <span class="font-weight-bold text-dark"><?= Sanitizer::escape($user['name']) ?></span>
                                    </div>
                                </td>
                                <td class="text-center"><span class="badge badge-info"><?= Sanitizer::escape($user['reported_count']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-4 timeline-card">
        <div class="card-header">
            <h3 class="card-title">Hoạt động gần đây trong không gian làm việc</h3>
        </div>
        <div class="card-body">
            <?php if (empty($recent_activities)): ?>
                <div class="text-center py-4 empty-state">
                    [cite_start]<img src="/assets/img/empty-state/no-data-chart.svg" class="img-empty-state" alt="Không có dữ liệu"> [cite: 49]
                    [cite_start]<p class="text-muted mt-2">Chưa có hoạt động nào được ghi nhận trong không gian này[cite: 242].</p>
                </div>
            <?php else: ?>
                <div class="activity-timeline">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-avatar-wrapper">
                                [cite_start]<img src="<?= Sanitizer::escape($activity['avatar_path'] ?? '/assets/img/avatar-default.svg') ?>" class="avatar-md" alt="User Avatar"> [cite: 49, 242]
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-text">
                                    <strong class="text-dark"><?= Sanitizer::escape($activity['user_name'] ?? 'Hệ thống') ?></strong>
                                    <span class="action-desc">
                                        <?= Sanitizer::escape($activity['rendered_message']) ?>
                                    </span>
                                </div>
                                <div class="timeline-time" title="<?= Sanitizer::escape($activity['created_at']) ?>">
                                    🕒 <?= Sanitizer::escape($activity['relative_time']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>