<?php
/**
 * _sidebar.php – Dark Sidebar Navigation
 *
 * Biến từ layout app.php:
 *   $currentUser       – array
 *   $activeWorkspace   – array ['id', 'name', 'slug', 'avatar_path']
 *   $workspaces        – array (danh sách workspace)
 *   $pageId            – string (để highlight active item)
 */

$current_page  = $pageId ?? '';
$active_ws     = $activeWorkspace ?? [];
$user          = $currentUser ?? [];
$current_role  = $_SESSION['current_role'] ?? 'guest';

// Helper: xác định nav item có active không
$is_active = fn(string $page): string =>
    $current_page === $page ? 'is-active' : '';
?>
<aside
    class="app-sidebar js-sidebar"
    role="navigation"
    aria-label="Menu điều hướng chính"
    id="app-sidebar"
>

    <!-- ================================================
         WORKSPACE SWITCHER
         ================================================ -->
    <div class="sidebar-workspace">
        <button
            class="sidebar-workspace__trigger js-workspace-trigger"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="workspace-dropdown"
            title="Chuyển đổi Workspace"
        >
            <!-- Avatar Workspace -->
            <span class="sidebar-workspace__avatar" aria-hidden="true">
                <?php if (!empty($active_ws['avatar_path'])): ?>
                    <img
                        src="<?= asset($active_ws['avatar_path']) ?>"
                        alt=""
                        width="32" height="32"
                        style="border-radius: var(--radius-md); object-fit: cover; width:100%; height:100%;"
                    >
                <?php else: ?>
                    <?= htmlspecialchars(mb_substr($active_ws['name'] ?? 'W', 0, 1), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                <?php endif; ?>
            </span>

            <span class="sidebar-workspace__name">
                <?= htmlspecialchars($active_ws['name'] ?? 'Workspace', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </span>

            <!-- Chevron icon -->
            <svg class="sidebar-workspace__chevron" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>

        <!-- Workspace dropdown menu -->
        <div class="dropdown__menu" id="workspace-dropdown" hidden role="menu" aria-label="Danh sách Workspace">
            <?php foreach ($workspaces ?? [] as $ws): ?>
                <form method="POST" action="<?= url('workspace/switch/' . htmlspecialchars($ws['slug'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                    <button type="submit" class="dropdown__item" role="menuitem">
                        <span class="avatar avatar--xs" style="background-color: var(--color-primary-600);">
                            <?= htmlspecialchars(mb_substr($ws['name'], 0, 1), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </span>
                        <?= htmlspecialchars($ws['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        <?php if ((int)($ws['id'] ?? 0) === (int)($active_ws['id'] ?? -1)): ?>
                            <svg style="width:14px;height:14px;margin-left:auto;color:var(--color-primary-600);" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        <?php endif; ?>
                    </button>
                </form>
            <?php endforeach; ?>
            <div class="dropdown__divider"></div>
            <a href="<?= url('workspace/create') ?>" class="dropdown__item" role="menuitem">
                <svg style="width:16px;height:16px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                </svg>
                Tạo Workspace mới
            </a>
        </div>
    </div>

    <!-- ================================================
         NAVIGATION LINKS
         ================================================ -->
    <nav class="sidebar-nav" aria-label="Navigation">

        <!-- Main section -->
        <div class="sidebar-nav__section">
            <span class="sidebar-nav__section-label">Menu chính</span>

            <a href="<?= url('dashboard') ?>"
               class="sidebar-nav__item <?= $is_active('dashboard') ?>"
               aria-current="<?= $current_page === 'dashboard' ? 'page' : 'false' ?>">
                <svg class="sidebar-nav__icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                </svg>
                <span class="sidebar-nav__label">Dashboard</span>
            </a>

            <a href="<?= url('issues') ?>"
               class="sidebar-nav__item <?= $is_active('issue-list') ?>"
               aria-current="<?= $current_page === 'issue-list' ? 'page' : 'false' ?>">
                <svg class="sidebar-nav__icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span class="sidebar-nav__label">Issues</span>
            </a>

            <a href="<?= url('projects') ?>"
               class="sidebar-nav__item <?= $is_active('project-list') ?>"
               aria-current="<?= $current_page === 'project-list' ? 'page' : 'false' ?>">
                <svg class="sidebar-nav__icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
                <span class="sidebar-nav__label">Projects</span>
            </a>

            <?php if (in_array($current_role, ['owner', 'admin', 'member'], true)): ?>
            <a href="<?= url('workspace/members') ?>"
               class="sidebar-nav__item <?= $is_active('members') ?>"
               aria-current="<?= $current_page === 'members' ? 'page' : 'false' ?>">
                <svg class="sidebar-nav__icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                </svg>
                <span class="sidebar-nav__label">Members</span>
            </a>
            <?php endif; ?>

        </div>

        <!-- Settings section (Owner/Admin only) -->
        <?php if (in_array($current_role, ['owner', 'admin'], true)): ?>
        <div class="sidebar-nav__section">
            <span class="sidebar-nav__section-label">Quản trị</span>

            <a href="<?= url('workspace/settings') ?>"
               class="sidebar-nav__item <?= $is_active('workspace-settings') ?>">
                <svg class="sidebar-nav__icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
                </svg>
                <span class="sidebar-nav__label">Cài đặt Workspace</span>
            </a>
        </div>
        <?php endif; ?>

    </nav>

    <!-- ================================================
         USER PROFILE – Bottom
         ================================================ -->
    <div class="sidebar-profile">
        <button
            class="sidebar-profile__trigger js-profile-trigger"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="profile-dropdown"
        >
            <!-- Avatar -->
            <span class="avatar avatar--sm sidebar-profile__avatar" aria-hidden="true">
                <?php if (!empty($user['avatar_path'])): ?>
                    <img src="<?= asset($user['avatar_path']) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars(mb_substr($user['name'] ?? 'U', 0, 1), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                <?php endif; ?>
            </span>

            <span class="sidebar-profile__info">
                <span class="sidebar-profile__name">
                    <?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                </span>
                <span class="sidebar-profile__role">
                    <?= htmlspecialchars(ucfirst($current_role), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                </span>
            </span>
        </button>

        <!-- Profile dropdown -->
        <div class="dropdown__menu" id="profile-dropdown" hidden
             style="bottom: calc(100% + 8px); top: auto;"
             role="menu" aria-label="Menu người dùng">
            <a href="<?= url('profile') ?>" class="dropdown__item" role="menuitem">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                Hồ sơ cá nhân
            </a>
            <div class="dropdown__divider"></div>
            <form method="POST" action="<?= url('logout') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                <button type="submit" class="dropdown__item dropdown__item--danger" role="menuitem">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/>
                    </svg>
                    Đăng xuất
                </button>
            </form>
        </div>
    </div>

</aside>