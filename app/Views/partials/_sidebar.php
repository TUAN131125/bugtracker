<?php
/**
 * @var array  $workspaces          Danh sách các Workspace user đang tham gia 
 * @var array  $active_workspace    Thông tin Workspace đang được active [cite: 163]
 * @var string $current_route       Đường dẫn hiện tại (để đánh dấu active menu)
 * @var string $current_user_role   Role của user trong workspace hiện tại [cite: 102]
 */
use App\Helpers\Sanitizer;

// Xác định quyền để hiển thị menu
$isAdminOrOwner = in_array($current_user_role, ['owner', 'admin']);
?>

<div class="sidebar-inner d-flex flex-column h-100">
    <div class="sidebar-brand p-3 border-bottom text-center">
        <a href="/dashboard" class="text-decoration-none">
            <img src="/assets/img/logo.svg" alt="BugTracker" class="logo-icon mb-1" style="height: 32px;">
            <h4 class="brand-text font-weight-bold text-dark m-0">BugTracker</h4>
        </a>
    </div>

    <div class="workspace-switcher p-3 border-bottom">
        <div class="dropdown w-100">
            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-left d-flex justify-content-between align-items-center" type="button" id="workspaceDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="text-truncate font-weight-bold"><?= Sanitizer::escape($active_workspace['name'] ?? 'Chọn Workspace') ?></span>
                <span class="icon-caret">▼</span>
            </button>
            <div class="dropdown-menu w-100 shadow-sm" aria-labelledby="workspaceDropdown">
                <div class="dropdown-header text-muted text-small">Không gian của bạn</div>
                <?php foreach ($workspaces as $ws): ?>
                    <a class="dropdown-item <?= ($ws['id'] === ($active_workspace['id'] ?? 0)) ? 'active bg-light-primary text-primary' : '' ?>" href="/workspace/switch/<?= Sanitizer::escape($ws['slug']) ?>">
                        <?= Sanitizer::escape($ws['name']) ?>
                    </a>
                <?php endforeach; ?>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-primary font-weight-bold" href="/workspace/create">
                    <span class="icon">+</span> Tạo Workspace mới
                </a>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav flex-grow-1 p-2 overflow-auto">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= strpos($current_route, '/dashboard') === 0 ? 'active' : '' ?>" href="/dashboard">
                    <span class="nav-icon">📊</span> Bảng điều khiển
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= strpos($current_route, '/issues') === 0 ? 'active' : '' ?>" href="/issues">
                    <span class="nav-icon">🐞</span> Lỗi & Công việc
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= strpos($current_route, '/projects') === 0 ? 'active' : '' ?>" href="/projects">
                    <span class="nav-icon">📁</span> Dự án
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= strpos($current_route, '/workspace/members') === 0 ? 'active' : '' ?>" href="/workspace/members">
                    <span class="nav-icon">👥</span> Thành viên
                </a>
            </li>

            <?php if ($isAdminOrOwner): ?>
                <li class="nav-item mt-3">
                    <div class="nav-section-title text-muted text-small font-weight-bold px-3 mb-1 text-uppercase">Quản trị</div>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_route, '/workspace/settings') === 0 ? 'active' : '' ?>" href="/workspace/settings">
                        <span class="nav-icon">⚙️</span> Cài đặt Workspace
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($current_user_role === 'owner'): ?>
                <li class="nav-item mt-3">
                    <div class="nav-section-title text-muted text-small font-weight-bold px-3 mb-1 text-uppercase">Hệ thống (Dev)</div>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_route, '/admin/email-queue') === 0 ? 'active' : '' ?>" href="/admin/email-queue">
                        <span class="nav-icon">📧</span> Hàng đợi Email
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($current_route, '/admin/system-logs') === 0 ? 'active' : '' ?>" href="/admin/system-logs">
                        <span class="nav-icon">🖥️</span> Nhật ký hệ thống
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>