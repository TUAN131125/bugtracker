<?php
/**
 * Issue List View
 *
 * Hiển thị danh sách Issue với filter sidebar và sort controls.
 * Data nhận từ IssueController::index() sau extract():
 *   $issues       – array danh sách Issue (đã phân trang)
 *   $filters      – array filter đang active (?status=open&priority=high)
 *   $pagination   – array { current_page, total_pages, total, per_page }
 *   $projects     – array danh sách Project trong Workspace (cho filter dropdown)
 *   $members      – array danh sách Member (cho filter Assignee)
 *   $milestones   – array danh sách Milestone (cho filter)
 *   $tags         – array danh sách Tag của Workspace
 *   $pageId       – string 'issue-list'
 *   $pageTitle    – string
 *   $csrfToken    – string
 *   $can          – array quyền: ['create_issue' => bool, ...]
 *
 * @see SRS v1.0.0 – UC-020 (Xem danh sách Issue với Filter và Sort)
 * @see ViewLayer Guide v1.0.0 – Phần 2.2, 3.1, 6.1
 * @see Task Assignment v1.0.0 – D3-016
 */

// PHẢI có dòng này ở đầu – Response.php đọc $layout để wrap layout
// Thiếu dòng này → trang không có CSS và JS (ViewLayer Guide Phần 6.1)
$layout = 'app';

// Giá trị mặc định cho $mock_ – Dev 3 dùng khi Dev 2 chưa implement Controller
// Prefix $mock_ để dễ tìm và replace sau (Task Assignment Phần 0.3)
$issues     = $issues     ?? [];
$filters    = $filters    ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total' => 0, 'per_page' => 20];
$projects   = $projects   ?? [];
$members    = $members    ?? [];
$milestones = $milestones ?? [];
$tags       = $tags       ?? [];
$can        = $can        ?? ['create_issue' => false];

// Sort options
$sort_options = [
    'created_at_desc' => 'Mới nhất',
    'created_at_asc'  => 'Cũ nhất',
    'priority_desc'   => 'Ưu tiên cao nhất',
    'updated_at_desc' => 'Cập nhật gần nhất',
    'id_asc'          => 'ID tăng dần',
];
$current_sort = htmlspecialchars($filters['sort'] ?? 'created_at_desc', ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Status options – map value → label + CSS class
// Phải khớp với state machine trong IssueService (SRS Phần 3.3.1)
$status_options = [
    'open'        => ['label' => 'Mới',          'class' => 'badge--open'],
    'in_triage'   => ['label' => 'Đang xem xét', 'class' => 'badge--triage'],
    'in_progress' => ['label' => 'Đang xử lý',   'class' => 'badge--in-progress'],
    'resolved'    => ['label' => 'Đã giải quyết','class' => 'badge--resolved'],
    'closed'      => ['label' => 'Đã đóng',      'class' => 'badge--closed'],
    'reopened'    => ['label' => 'Mở lại',        'class' => 'badge--reopened'],
    'wont_fix'    => ['label' => 'Không sửa',     'class' => 'badge--wontfix'],
    'duplicate'   => ['label' => 'Trùng lặp',     'class' => 'badge--duplicate'],
];

$priority_options = [
    'urgent' => ['label' => 'Khẩn cấp', 'icon' => '↑↑', 'class' => 'priority--urgent'],
    'high'   => ['label' => 'Cao',       'icon' => '↑',  'class' => 'priority--high'],
    'medium' => ['label' => 'Trung bình','icon' => '→',  'class' => 'priority--medium'],
    'low'    => ['label' => 'Thấp',      'icon' => '↓',  'class' => 'priority--low'],
];

$severity_options = [
    'critical' => ['label' => 'Critical', 'class' => 'badge--critical'],
    'major'    => ['label' => 'Major',    'class' => 'badge--major'],
    'minor'    => ['label' => 'Minor',    'class' => 'badge--minor'],
    'trivial'  => ['label' => 'Trivial',  'class' => 'badge--trivial'],
];

$type_options = [
    'bug'         => 'Bug',
    'task'        => 'Task',
    'enhancement' => 'Enhancement',
    'question'    => 'Question',
];

// Helper: kiểm tra filter có đang active không
$is_filter_active = function (string $key, string $value) use ($filters): bool {
    $active = $filters[$key] ?? [];
    if (is_string($active)) {
        $active = [$active];
    }
    return in_array($value, (array) $active, true);
};
?>

<div class="issue-list-page">

    <?php
    // Truyền dữ liệu PHP sang JS qua thẻ script type=application/json
    // KHÔNG viết logic JS inline (ViewLayer Guide Phần 6.4)
    // JSON_HEX_TAG: escape < > để chống XSS khi inject vào HTML
    ?>
    <script type="application/json" id="page-data">
        <?= json_encode([
            'currentSort' => $filters['sort'] ?? 'created_at_desc',
            'totalIssues' => $pagination['total'],
        ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>
    </script>

    <!-- ================================================================
         HEADER: Tiêu đề trang + nút tạo mới
         ================================================================ -->
    <div class="page-header">
        <div class="page-header__left">
            <h1 class="page-header__title">Danh sách Issue</h1>
            <span class="page-header__count">
                <?= htmlspecialchars((string) $pagination['total'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> issue
            </span>
        </div>

        <?php if ($can['create_issue']): ?>
        <div class="page-header__actions">
            <a href="<?= url('issues/create') ?>" class="btn btn--primary">
                <svg class="btn__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                </svg>
                Tạo Issue mới
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="issue-list-layout">

        <!-- ================================================================
             FILTER SIDEBAR (trái)
             Filter dùng GET method → URL shareable, bookmark được
             (ViewLayer Guide Phần 6.3, SRS UC-020)
             ================================================================ -->
        <aside class="filter-sidebar" id="filter-sidebar" aria-label="Bộ lọc Issue">

            <div class="filter-sidebar__header">
                <h2 class="filter-sidebar__title">Bộ lọc</h2>
                <?php
                // Hiển thị nút "Xóa bộ lọc" nếu đang có filter active
                $has_active_filter = !empty(array_filter($filters, fn($v) => $v !== '' && $v !== null && $v !== []));
                ?>
                <?php if ($has_active_filter): ?>
                <a href="<?= url('issues') ?>" class="filter-sidebar__clear">Xóa tất cả</a>
                <?php endif; ?>
            </div>

            <!-- Filter là GET form – URL phản ánh trạng thái filter -->
            <form
                id="filter-form"
                method="GET"
                action="<?= url('issues') ?>"
                data-filter-form
            >

                <!-- Tìm kiếm theo keyword -->
                <div class="filter-group">
                    <label for="filter-keyword" class="filter-group__label">Từ khóa</label>
                    <input
                        type="text"
                        id="filter-keyword"
                        name="q"
                        class="form-input form-input--sm"
                        placeholder="Tìm theo tiêu đề..."
                        value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                        data-validate-on="input"
                    >
                </div>

                <!-- Filter Status – multi-select checkbox -->
                <div class="filter-group">
                    <button
                        type="button"
                        class="filter-group__toggle"
                        aria-expanded="true"
                        aria-controls="filter-status"
                    >
                        Trạng thái
                        <svg class="filter-group__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div class="filter-group__options" id="filter-status">
                        <?php foreach ($status_options as $value => $opt): ?>
                        <label class="filter-checkbox">
                            <input
                                type="checkbox"
                                name="status[]"
                                value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                <?= $is_filter_active('status', $value) ? 'checked' : '' ?>
                                data-auto-submit
                            >
                            <span class="badge <?= htmlspecialchars($opt['class'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                                <?= htmlspecialchars($opt['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Filter Priority -->
                <div class="filter-group">
                    <button
                        type="button"
                        class="filter-group__toggle"
                        aria-expanded="true"
                        aria-controls="filter-priority"
                    >
                        Độ ưu tiên
                        <svg class="filter-group__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div class="filter-group__options" id="filter-priority">
                        <?php foreach ($priority_options as $value => $opt): ?>
                        <label class="filter-checkbox">
                            <input
                                type="checkbox"
                                name="priority[]"
                                value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                <?= $is_filter_active('priority', $value) ? 'checked' : '' ?>
                                data-auto-submit
                            >
                            <span class="priority-label <?= htmlspecialchars($opt['class'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                                <span aria-hidden="true"><?= htmlspecialchars($opt['icon'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                                <?= htmlspecialchars($opt['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Filter Severity -->
                <div class="filter-group">
                    <button
                        type="button"
                        class="filter-group__toggle"
                        aria-expanded="false"
                        aria-controls="filter-severity"
                    >
                        Mức độ nghiêm trọng
                        <svg class="filter-group__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div class="filter-group__options is-collapsed" id="filter-severity">
                        <?php foreach ($severity_options as $value => $opt): ?>
                        <label class="filter-checkbox">
                            <input
                                type="checkbox"
                                name="severity[]"
                                value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                <?= $is_filter_active('severity', $value) ? 'checked' : '' ?>
                                data-auto-submit
                            >
                            <span class="badge <?= htmlspecialchars($opt['class'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                                <?= htmlspecialchars($opt['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Filter Type -->
                <div class="filter-group">
                    <button
                        type="button"
                        class="filter-group__toggle"
                        aria-expanded="false"
                        aria-controls="filter-type"
                    >
                        Loại Issue
                        <svg class="filter-group__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div class="filter-group__options is-collapsed" id="filter-type">
                        <?php foreach ($type_options as $value => $label): ?>
                        <label class="filter-checkbox">
                            <input
                                type="checkbox"
                                name="type[]"
                                value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                <?= $is_filter_active('type', $value) ? 'checked' : '' ?>
                                data-auto-submit
                            >
                            <?= htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Filter Assignee -->
                <?php if (!empty($members)): ?>
                <div class="filter-group">
                    <button
                        type="button"
                        class="filter-group__toggle"
                        aria-expanded="false"
                        aria-controls="filter-assignee"
                    >
                        Assignee
                        <svg class="filter-group__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div class="filter-group__options is-collapsed" id="filter-assignee">
                        <?php foreach ($members as $member): ?>
                        <label class="filter-checkbox">
                            <input
                                type="checkbox"
                                name="assignee[]"
                                value="<?= htmlspecialchars((string) $member['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                <?= $is_filter_active('assignee', (string) $member['id']) ? 'checked' : '' ?>
                                data-auto-submit
                            >
                            <span class="avatar avatar--xs" aria-hidden="true">
                                <?= htmlspecialchars(mb_substr($member['name'], 0, 1), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </span>
                            <?= htmlspecialchars($member['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filter Milestone -->
                <?php if (!empty($milestones)): ?>
                <div class="filter-group">
                    <button
                        type="button"
                        class="filter-group__toggle"
                        aria-expanded="false"
                        aria-controls="filter-milestone"
                    >
                        Milestone
                        <svg class="filter-group__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div class="filter-group__options is-collapsed" id="filter-milestone">
                        <?php foreach ($milestones as $milestone): ?>
                        <label class="filter-checkbox">
                            <input
                                type="checkbox"
                                name="milestone[]"
                                value="<?= htmlspecialchars((string) $milestone['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                <?= $is_filter_active('milestone', (string) $milestone['id']) ? 'checked' : '' ?>
                                data-auto-submit
                            >
                            <?= htmlspecialchars($milestone['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filter Tags -->
                <?php if (!empty($tags)): ?>
                <div class="filter-group">
                    <button
                        type="button"
                        class="filter-group__toggle"
                        aria-expanded="false"
                        aria-controls="filter-tags"
                    >
                        Tags
                        <svg class="filter-group__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    <div class="filter-group__options is-collapsed" id="filter-tags">
                        <?php foreach ($tags as $tag): ?>
                        <label class="filter-checkbox">
                            <input
                                type="checkbox"
                                name="tag[]"
                                value="<?= htmlspecialchars((string) $tag['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                <?= $is_filter_active('tag', (string) $tag['id']) ? 'checked' : '' ?>
                                data-auto-submit
                            >
                            <span
                                class="tag-dot"
                                style="background-color: <?= htmlspecialchars($tag['color'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                aria-hidden="true"
                            ></span>
                            <?= htmlspecialchars($tag['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Submit ẩn – JS submit form khi checkbox thay đổi (debounce 500ms) -->
                <button type="submit" class="visually-hidden" aria-hidden="true">Lọc</button>

            </form>

        </aside>

        <!-- ================================================================
             ISSUE LIST AREA (phải)
             ================================================================ -->
        <div class="issue-list-main">

            <!-- Sort controls + skeleton loader target -->
            <div class="issue-list-toolbar">
                <p class="issue-list-toolbar__count">
                    Hiển thị
                    <strong><?= htmlspecialchars((string) count($issues), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong>
                    /
                    <?= htmlspecialchars((string) $pagination['total'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    issue
                </p>

                <div class="issue-list-toolbar__sort">
                    <label for="sort-select" class="visually-hidden">Sắp xếp theo</label>
                    <select
                        id="sort-select"
                        name="sort"
                        class="form-select form-select--sm"
                        data-sort-select
                        form="filter-form"
                    >
                        <?php foreach ($sort_options as $value => $label): ?>
                        <option
                            value="<?= htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            <?= $current_sort === $value ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Skeleton loader – JS show khi submit filter, hide khi load xong -->
            <!-- aria-busy="true" + aria-label cho screen reader (ViewLayer Guide Phần 8.3) -->
            <div
                class="issue-skeleton-list is-hidden"
                id="issue-skeleton"
                aria-busy="true"
                aria-label="Đang tải danh sách Issue..."
            >
                <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="skeleton-row">
                    <div class="skeleton-block skeleton-badge"></div>
                    <div class="skeleton-block skeleton-text" style="flex: 1"></div>
                    <div class="skeleton-block skeleton-badge"></div>
                    <div class="skeleton-block skeleton-avatar"></div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Issue list thực tế -->
            <div class="issue-card-list" id="issue-list" aria-label="Danh sách Issue">

                <?php if (empty($issues)): ?>
                <!-- Empty state -->
                <div class="empty-state">
                    <img
                        src="<?= asset('img/empty-state/no-issues.svg') ?>"
                        alt=""
                        aria-hidden="true"
                        class="empty-state__img"
                    >
                    <h3 class="empty-state__title">Không có Issue nào</h3>
                    <p class="empty-state__desc">
                        <?php if ($has_active_filter): ?>
                            Không có Issue nào khớp với bộ lọc hiện tại.
                            <a href="<?= url('issues') ?>" class="link">Xóa bộ lọc</a>
                        <?php elseif ($can['create_issue']): ?>
                            Workspace chưa có Issue nào.
                            <a href="<?= url('issues/create') ?>" class="link">Tạo Issue đầu tiên</a>
                        <?php else: ?>
                            Chưa có Issue nào trong Workspace này.
                        <?php endif; ?>
                    </p>
                </div>

                <?php else: ?>
                    <?php foreach ($issues as $issue): ?>
                    <?php
                    // Include partial _issue-card.php cho mỗi issue
                    // Partial nhận biến $issue (array) từ scope hiện tại
                    $card_issue = $issue; // đổi tên để partial dùng $card_issue
                    include VIEWS_PATH . '/partials/_issue-card.php';
                    ?>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
            <div class="pagination-wrapper">
                <?php include VIEWS_PATH . '/partials/_pagination.php'; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>