<?php
/**
 * Dashboard – View Template
 * /app/Views/dashboard/index.php
 *
 * Layout: app.php (sidebar + header đã được inject bởi Response::view())
 *
 * Data contract từ DashboardController::index() – Phần 5.1 Task Assignment:
 *   $chart_data         → array { status_counts[], project_counts[] }
 *   $my_issues          → array – 5 issue được giao cho current user
 *   $recent_activity    → array – 10 hoạt động gần nhất
 *   $unread_notif_count → int   – badge notification
 *
 * QUY TẮC BẤT BIẾN (ViewLayer Guide Phần 8.1):
 *   - KHÔNG có <style> hay <script> inline trong file này
 *   - Mọi biến PHP render ra HTML phải qua Sanitizer::escape()
 *   - Chart data inject qua <script type="application/json"> – KHÔNG phải <script> thường
 *
 * @see ViewLayer Implementation Guide v1.0.0 – Phần 6.2 (Dashboard)
 * @see Task Assignment v1.0.0               – D3-024, Interface Contract Phần 5.1
 * @see DashboardController                  – D1-028
 *
 * @author  Dev 3
 * @version 1.0.0
 */

use App\Helpers\Sanitizer;
use App\Helpers\Csrf;
?>

<?php /* ── Inject chart data vào DOM để dashboard.js đọc – ViewLayer Guide 6.2 ── */ ?>
<?php if (!empty($chart_data)): ?>
<script type="application/json" id="dashboard-chart-data">
    <?= json_encode($chart_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>
</script>
<?php endif; ?>

<?php /* ── Inject poll interval để dashboard.js đọc từ PHP constant ── */ ?>
<meta name="poll-interval" content="<?= NOTIFICATION_POLL_INTERVAL ?>">

<div class="dashboard-page">

    <?php /* ================================================================
           SECTION A – Page Header
           ================================================================ */ ?>
    <div class="page-header">
        <div class="page-header__left">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Tổng quan hoạt động Workspace</p>
        </div>
        <div class="page-header__right">
            <span class="page-meta">
                Cập nhật lúc <span id="last-updated" class="page-meta__time">vừa xong</span>
            </span>
        </div>
    </div>

    <?php /* ================================================================
           SECTION B – Stat Cards (4 widget)
           Data: $chart_data['status_counts']
           ViewLayer Guide Phần 6.2 – Widget Cards
           ================================================================ */ ?>
    <?php
    // Tính tổng từ status_counts array – không query DB thêm
    $statusMap   = [];
    foreach (($chart_data['status_counts'] ?? []) as $row) {
        $statusMap[$row['status']] = (int) $row['count'];
    }
    $totalOpen       = $statusMap['open']        ?? 0;
    $totalInProgress = $statusMap['in_progress'] ?? 0;
    $totalResolved   = $statusMap['resolved']     ?? 0;

    // Tính overdue: không có trong status_counts, DashboardController cần cung cấp
    // Tạm thời lấy từ $chart_data['overdue_count'] nếu có
    $totalOverdue = (int) ($chart_data['overdue_count'] ?? 0);

    // Grand total
    $grandTotal = array_sum(array_column($chart_data['status_counts'] ?? [], 'count'));
    ?>

    <div class="stat-grid">
        <?php
        $statCards = [
            [
                'id'     => 'total',
                'label'  => 'Tổng Issue',
                'value'  => $grandTotal,
                'icon'   => 'layers',
                'mod'    => 'total',
                'trend'  => null,
            ],
            [
                'id'     => 'open',
                'label'  => 'Đang mở',
                'value'  => $totalOpen,
                'icon'   => 'circle',
                'mod'    => 'open',
                'trend'  => null,
            ],
            [
                'id'     => 'in-progress',
                'label'  => 'Đang xử lý',
                'value'  => $totalInProgress,
                'icon'   => 'zap',
                'mod'    => 'progress',
                'trend'  => null,
            ],
            [
                'id'     => 'overdue',
                'label'  => 'Quá hạn',
                'value'  => $totalOverdue,
                'icon'   => 'alert-triangle',
                'mod'    => 'overdue',
                'trend'  => null,
            ],
        ];
        ?>

        <?php foreach ($statCards as $card): ?>
        <div class="stat-card stat-card--<?= Sanitizer::escape($card['mod']) ?>"
             role="region"
             aria-label="<?= Sanitizer::escape($card['label']) ?>: <?= (int)$card['value'] ?>">
            <div class="stat-card__header">
                <span class="stat-card__label"><?= Sanitizer::escape($card['label']) ?></span>
                <span class="stat-card__icon" aria-hidden="true">
                    <?php include __DIR__ . '/../partials/_icon.php';
                    // Dev 3 cần tạo _icon.php partial – tạm dùng text fallback ?>
                    <svg class="icon icon--<?= Sanitizer::escape($card['icon']) ?>" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <?php if ($card['icon'] === 'layers'): ?>
                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                            <polyline points="2 17 12 22 22 17"></polyline>
                            <polyline points="2 12 12 17 22 12"></polyline>
                        <?php elseif ($card['icon'] === 'circle'): ?>
                            <circle cx="12" cy="12" r="10"></circle>
                        <?php elseif ($card['icon'] === 'zap'): ?>
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        <?php elseif ($card['icon'] === 'alert-triangle'): ?>
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        <?php endif; ?>
                    </svg>
                </span>
            </div>
            <div class="stat-card__value" id="stat-<?= Sanitizer::escape($card['id']) ?>">
                <?= (int) $card['value'] ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php /* ================================================================
           SECTION C – Charts Row
           ViewLayer Guide Phần 6.2 – Chart.js Doughnut + Bar
           ================================================================ */ ?>
    <div class="charts-row">

        <?php /* ── Donut Chart: Issue by Status ── */ ?>
        <div class="chart-card chart-card--donut">
            <div class="chart-card__header">
                <h2 class="chart-card__title">Phân phối theo trạng thái</h2>
            </div>
            <div class="chart-card__body">
                <?php if (!empty($chart_data['status_counts'])): ?>
                    <div class="chart-wrapper chart-wrapper--donut">
                        <canvas id="status-donut-chart"
                                role="img"
                                aria-label="Biểu đồ phân phối Issue theo trạng thái"></canvas>
                    </div>
                    <div class="chart-legend" id="donut-legend" aria-hidden="true"></div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg class="empty-state__icon" viewBox="0 0 80 80" fill="none" aria-hidden="true">
                            <circle cx="40" cy="40" r="32" stroke="var(--color-neutral-200)" stroke-width="2" stroke-dasharray="6 4"/>
                            <circle cx="40" cy="40" r="18" stroke="var(--color-neutral-300)" stroke-width="2"/>
                        </svg>
                        <p class="empty-state__text">Chưa có Issue nào.</p>
                        <a href="/issues/create" class="btn btn--primary btn--sm">Tạo Issue đầu tiên</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ── Bar Chart: Issue by Project ── */ ?>
        <div class="chart-card chart-card--bar">
            <div class="chart-card__header">
                <h2 class="chart-card__title">Issue theo Project</h2>
            </div>
            <div class="chart-card__body">
                <?php if (!empty($chart_data['project_counts'])): ?>
                    <div class="chart-wrapper chart-wrapper--bar">
                        <canvas id="project-bar-chart"
                                role="img"
                                aria-label="Biểu đồ số lượng Issue theo từng Project"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <svg class="empty-state__icon" viewBox="0 0 80 60" fill="none" aria-hidden="true">
                            <rect x="8"  y="40" width="12" height="16" rx="2" fill="var(--color-neutral-200)"/>
                            <rect x="26" y="28" width="12" height="28" rx="2" fill="var(--color-neutral-200)"/>
                            <rect x="44" y="20" width="12" height="36" rx="2" fill="var(--color-neutral-200)"/>
                            <rect x="62" y="32" width="12" height="24" rx="2" fill="var(--color-neutral-200)"/>
                        </svg>
                        <p class="empty-state__text">Chưa có Project nào.</p>
                        <a href="/projects/create" class="btn btn--primary btn--sm">Tạo Project</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <?php /* ================================================================
           SECTION D – Bottom Row: My Issues + Recent Activity
           ViewLayer Guide Phần 6.2 – List widgets
           ================================================================ */ ?>
    <div class="lists-row">

        <?php /* ── My Issues ── */ ?>
        <div class="list-card">
            <div class="list-card__header">
                <h2 class="list-card__title">Giao cho tôi</h2>
                <a href="/issues?assignee=me" class="list-card__link">Xem tất cả</a>
            </div>
            <div class="list-card__body">
                <?php if (!empty($my_issues)): ?>
                    <ul class="issue-list" role="list">
                        <?php foreach ($my_issues as $issue): ?>
                        <li class="issue-list__item">
                            <a href="/issues/<?= Sanitizer::escape($issue['issue_key']) ?>"
                               class="issue-row"
                               aria-label="Issue <?= Sanitizer::escape($issue['issue_key']) ?>: <?= Sanitizer::escape($issue['title']) ?>">

                                <?php /* Priority indicator */ ?>
                                <span class="priority-dot priority-dot--<?= Sanitizer::escape($issue['priority'] ?? 'medium') ?>"
                                      aria-label="Độ ưu tiên: <?= Sanitizer::escape($issue['priority'] ?? 'medium') ?>"></span>

                                <div class="issue-row__main">
                                    <span class="issue-row__key"><?= Sanitizer::escape($issue['issue_key']) ?></span>
                                    <span class="issue-row__title"><?= Sanitizer::escape($issue['title']) ?></span>
                                </div>

                                <div class="issue-row__meta">
                                    <span class="badge badge--<?= Sanitizer::escape($issue['status']) ?>"
                                          aria-label="Trạng thái: <?= Sanitizer::escape($issue['status']) ?>">
                                        <?php
                                        $statusLabels = [
                                            'open'        => 'Mới',
                                            'in_triage'   => 'Xem xét',
                                            'in_progress' => 'Đang làm',
                                            'resolved'    => 'Đã xong',
                                            'closed'      => 'Đóng',
                                            'reopened'    => 'Mở lại',
                                            'wont_fix'    => 'Bỏ qua',
                                            'duplicate'   => 'Trùng',
                                        ];
                                        echo Sanitizer::escape($statusLabels[$issue['status']] ?? $issue['status']);
                                        ?>
                                    </span>
                                    <span class="issue-row__project"><?= Sanitizer::escape($issue['project_name'] ?? '') ?></span>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state empty-state--inline">
                        <p class="empty-state__text">Không có Issue nào được giao cho bạn.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php /* ── Recent Activity ── */ ?>
        <div class="list-card">
            <div class="list-card__header">
                <h2 class="list-card__title">Hoạt động gần đây</h2>
                <a href="/workspace/activity" class="list-card__link">Xem tất cả</a>
            </div>
            <div class="list-card__body">
                <?php if (!empty($recent_activity)): ?>
                    <ul class="activity-list" role="list">
                        <?php foreach ($recent_activity as $log): ?>
                        <?php
                        // Parse metadata JSON – ActivityLog TDD Phần 4.4
                        $meta   = json_decode($log['metadata'] ?? '{}', true) ?? [];

                        // Build human-readable message từ action_type + metadata
                        $actionMessages = [
                            'issue_created'        => 'tạo Issue <strong>%s</strong>',
                            'issue_status_changed' => 'đổi <strong>%s</strong> → <em>%s</em>',
                            'issue_assigned'       => 'gán <strong>%s</strong> cho %s',
                            'issue_commented'      => 'bình luận vào <strong>%s</strong>',
                            'project_created'      => 'tạo Project <strong>%s</strong>',
                            'project_archived'     => 'archive Project <strong>%s</strong>',
                            'member_invited'       => 'mời <strong>%s</strong>',
                            'member_kicked'        => 'xóa thành viên <strong>%s</strong>',
                        ];

                        $tpl    = $actionMessages[$log['action_type']] ?? 'thực hiện hành động';
                        $issueKey = htmlspecialchars($meta['issue_key'] ?? '', ENT_QUOTES, 'UTF-8');
                        $from   = htmlspecialchars($meta['from'] ?? '', ENT_QUOTES, 'UTF-8');
                        $to     = htmlspecialchars($meta['to'] ?? '', ENT_QUOTES, 'UTF-8');
                        $name   = htmlspecialchars($meta['to_user_name'] ?? $meta['email'] ?? $meta['project_name'] ?? '', ENT_QUOTES, 'UTF-8');

                        // Dùng match theo action_type để build message an toàn
                        $message = match($log['action_type']) {
                            'issue_created'        => sprintf($tpl, $issueKey),
                            'issue_status_changed' => sprintf($tpl, $issueKey, $to),
                            'issue_assigned'       => sprintf($tpl, $issueKey, $name),
                            'issue_commented'      => sprintf($tpl, $issueKey),
                            'project_created'      => sprintf($tpl, $name),
                            'project_archived'     => sprintf($tpl, $name),
                            'member_invited'       => sprintf($tpl, $name),
                            'member_kicked'        => sprintf($tpl, $name),
                            default                => 'thực hiện hành động',
                        };

                        // Thời gian tương đối
                        $createdAt  = strtotime($log['created_at'] ?? 'now');
                        $diffSec    = time() - $createdAt;
                        $relTime    = match(true) {
                            $diffSec < 60      => 'Vừa xong',
                            $diffSec < 3600    => floor($diffSec / 60)  . ' phút trước',
                            $diffSec < 86400   => floor($diffSec / 3600) . ' giờ trước',
                            default            => floor($diffSec / 86400) . ' ngày trước',
                        };
                        ?>
                        <li class="activity-item" role="listitem">
                            <?php /* Avatar */ ?>
                            <div class="activity-item__avatar">
                                <?php if (!empty($log['avatar_path'])): ?>
                                    <img src="<?= Sanitizer::escape($log['avatar_path']) ?>"
                                         alt="<?= Sanitizer::escape($log['actor_name'] ?? 'User') ?>"
                                         class="avatar avatar--sm"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="avatar avatar--sm avatar--placeholder"
                                         aria-label="<?= Sanitizer::escape($log['actor_name'] ?? 'System') ?>">
                                        <?= mb_strtoupper(mb_substr($log['actor_name'] ?? 'S', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php /* Content */ ?>
                            <div class="activity-item__content">
                                <p class="activity-item__text">
                                    <strong class="activity-item__actor">
                                        <?= Sanitizer::escape($log['actor_name'] ?? 'Hệ thống') ?>
                                    </strong>
                                    <?= $message /* Đã escape từng phần riêng bên trên */ ?>
                                </p>
                                <time class="activity-item__time"
                                      datetime="<?= Sanitizer::escape($log['created_at'] ?? '') ?>"
                                      title="<?= Sanitizer::escape($log['created_at'] ?? '') ?>">
                                    <?= Sanitizer::escape($relTime) ?>
                                </time>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state empty-state--inline">
                        <p class="empty-state__text">Chưa có hoạt động nào trong Workspace này.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
<?php /* END .dashboard-page */ ?>