<?php
/**
 * _header.php – Top Header Bar
 *
 * Breadcrumb + Global Search + Notification Bell + User Avatar
 */
$unread = (int)($unreadNotifCount ?? 0);
$user   = $currentUser ?? [];
?>
<header class="app-header" role="banner">

    <!-- Hamburger (mobile) -->
    <button
        class="app-header__btn js-sidebar-toggle"
        aria-label="Mở menu"
        aria-expanded="false"
        aria-controls="app-sidebar"
        style="display: none; margin-right: var(--space-2);"
        id="sidebar-toggle-btn"
    >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>

    <!-- Breadcrumb -->
    <nav class="app-header__breadcrumb" aria-label="Breadcrumb">
        <?php if (!empty($breadcrumbs)): ?>
            <?php foreach ($breadcrumbs as $i => $crumb): ?>
                <?php if ($i > 0): ?>
                    <span class="app-header__breadcrumb-sep" aria-hidden="true">
                        <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                <?php endif; ?>
                <?php if ($i === count($breadcrumbs) - 1): ?>
                    <span class="app-header__breadcrumb-current" aria-current="page">
                        <?= htmlspecialchars($crumb['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </span>
                <?php else: ?>
                    <a href="<?= url(htmlspecialchars($crumb['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>"
                       class="app-header__breadcrumb-link">
                        <?= htmlspecialchars($crumb['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <!-- Global Search -->
    <div class="app-header__search" id="global-search-container" data-search-container>
        <svg class="app-header__search-icon" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
        </svg>
        <input
            type="search"
            id="global-search"
            class="app-header__search-input"
            placeholder="Tìm kiếm issue, project... (Ctrl+K)"
            autocomplete="off"
            aria-label="Tìm kiếm toàn hệ thống"
            aria-expanded="false"
            aria-autocomplete="list"
            aria-controls="search-dropdown"
        >
        <!-- Search results dropdown (render bởi global-search.js) -->
        <div id="search-dropdown" role="listbox" aria-label="Kết quả tìm kiếm" hidden></div>
    </div>

    <!-- Header Actions -->
    <div class="app-header__actions">

        <!-- Notification Bell -->
        <button
            class="app-header__btn js-notif-btn"
            aria-label="Thông báo<?= $unread > 0 ? " ({$unread} chưa đọc)" : '' ?>"
            aria-haspopup="true"
            aria-expanded="false"
            id="notif-btn"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <span
                class="notif-badge"
                id="notif-badge"
                aria-live="polite"
                data-count="<?= $unread ?>"
            >
                <?= $unread > 0 ? ($unread > 99 ? '99+' : $unread) : '' ?>
            </span>
        </button>

        <!-- User Avatar -->
        <div class="dropdown">
            <button
                class="app-header__avatar-btn js-header-profile"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="header-profile-dropdown"
                aria-label="Menu người dùng"
            >
                <?php if (!empty($user['avatar_path'])): ?>
                    <img src="<?= asset($user['avatar_path']) ?>"
                         alt="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                <?php else: ?>
                    <span class="avatar avatar--sm" style="width:100%;height:100%;border-radius:50%;">
                        <?= htmlspecialchars(mb_substr($user['name'] ?? 'U', 0, 1), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </button>

            <div class="dropdown__menu" id="header-profile-dropdown" hidden
                 role="menu" aria-label="Menu người dùng">
                <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--color-neutral-100);">
                    <p style="font-size: var(--text-sm); font-weight: var(--font-weight-semibold); margin:0; color: var(--color-neutral-900);">
                        <?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </p>
                    <p style="font-size: var(--text-xs); color: var(--color-neutral-500); margin: 2px 0 0;">
                        <?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </p>
                </div>
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

    </div>
</header>