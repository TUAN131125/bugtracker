<?php
/**
 * app.php – Authenticated App Layout
 *
 * Biến nhận từ Controller qua Response::view():
 *   $pageId           – string
 *   $pageTitle        – string
 *   $csrfToken        – string
 *   $currentUser      – array
 *   $workspaces       – array
 *   $activeWorkspace  – array
 *   $unreadNotifCount – int
 *   $breadcrumbs      – array
 *   $content          – string (inject bởi Response::view())
 */
?>
<!DOCTYPE html>
<html lang="vi" data-page="<?= htmlspecialchars($pageId ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'BugTracker', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> – BugTracker</title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/logo.svg') ?>">

    <!-- Tailwind CSS CDN (blocking – cần trước render) -->
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">

    <!-- Animate.css -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">

    <!-- Local CSS – toàn bộ styles ở đây, không inline -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <!-- Chart.js – chỉ load trên Dashboard -->
    <?php if (($pageId ?? '') === 'dashboard'): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js" defer></script>
    <?php endif; ?>

    <?php if (str_starts_with($pageId ?? '', 'issue')): ?>
        <link rel="stylesheet" href="<?= asset('css/_issues.css') ?>">
    <?php endif; ?>

    <!-- marked.js – chỉ load trên Issue form/detail -->
    <?php if (in_array($pageId ?? '', ['issue-form', 'issue-detail'], true)): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js" defer></script>
    <?php endif; ?>
</head>
<body>

    <!-- Toast container -->
    <?php include VIEWS_PATH . '/partials/_toast-container.php'; ?>

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay js-sidebar-overlay" aria-hidden="true"></div>

    <div class="app-shell">

        <?php include VIEWS_PATH . '/partials/_sidebar.php'; ?>

        <div class="app-main">

            <?php include VIEWS_PATH . '/partials/_header.php'; ?>

            <main class="app-content" id="main-content" role="main">
                <?= $content ?? '' ?>
            </main>

        </div>
    </div>

    <!-- JS Core -->
    <script src="<?= asset('js/core/utils.js') ?>" defer></script>
    <script src="<?= asset('js/core/api.js') ?>" defer></script>
    <script src="<?= asset('js/core/toast.js') ?>" defer></script>
    <script src="<?= asset('js/core/modal.js') ?>" defer></script>
    <script src="<?= asset('js/core/validator.js') ?>" defer></script>
    <script src="<?= asset('js/app.js') ?>" type="module" defer></script>

</body>
</html>