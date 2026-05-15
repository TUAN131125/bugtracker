<?php
/**
 * Landing Layout – BugTracker
 *
 * Layout riêng cho trang Landing Page (không có sidebar/header của app).
 * Không dùng layout app.php vì trang này public, không cần auth.
 *
 * Biến nhận từ Controller:
 *   string $pageId    – data-page attribute, app.js dùng để init đúng module
 *   string $pageTitle – <title> tag
 *   string $metaDesc  – meta description (SEO)
 *   string $content   – nội dung trang được render bởi Controller
 *
 * @see ViewLayer Implementation Guide v1.0.0 – Phần 1.3 (CDN Load Strategy)
 * @see ViewLayer Implementation Guide v1.0.0 – Phần 9.2 (JS Performance)
 */
?>
<!DOCTYPE html>
<html lang="vi" data-page="<?= htmlspecialchars($pageId ?? 'landing', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php if (!empty($metaDesc)): ?>
        <meta name="description" content="<?= htmlspecialchars($metaDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
    <?php endif; ?>

    <title><?= htmlspecialchars($pageTitle ?? 'BugTracker', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></title>

    <!-- FIX: đổi svg → png vì file vật lý là logo.png, không phải logo.svg -->
    <link rel="icon" type="image/png" href="<?= asset('img/logo.png') ?>">

    <!-- Tailwind CSS 3.x CDN – blocking, load trước render (ViewLayer Guide Phần 1.3) -->
    <!-- FIX: bỏ unpkg.com/@tailwindcss/browser@4 (bị 302 redirect) -->
    <!-- WHY cdn.tailwindcss.com: URL chính thức, ổn định, không bị redirect -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Animate.css – load qua cdnjs ổn định (ViewLayer Guide Phần 1.3) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">

    <!-- CSS nội bộ – load sau CDN -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/_landing.css') ?>">
</head>
<body class="landing-body">

    <?php
        // Navbar riêng cho landing page (không có sidebar)
        $navFile = VIEWS_PATH . '/partials/_navbar-landing.php';
        if (file_exists($navFile)) {
            include $navFile;
        }
    ?>

    <main id="main-content">
        <?= $content ?? '' ?>
    </main>

    <?php
        $footerFile = VIEWS_PATH . '/partials/_footer-landing.php';
        if (file_exists($footerFile)) {
            include $footerFile;
        }
    ?>

    <!--
        JS Load Strategy (ViewLayer Guide Phần 9.2):
        - type="module": bắt buộc vì các file dùng export/import ES6
        - type="module" tự động defer – không cần thêm defer attribute
        - Thứ tự: core trước (utils → toast → api) → page-specific sau
        WHY utils trước: api.js và landing.js có thể import từ utils.js
        WHY api trước landing: landing.js gọi API qua api.js
    -->
    <script type="module" src="<?= asset('js/core/utils.js') ?>"></script>
    <script type="module" src="<?= asset('js/core/toast.js') ?>"></script>
    <script type="module" src="<?= asset('js/core/api.js') ?>"></script>
    <script type="module" src="<?= asset('js/pages/landing.js') ?>"></script>

</body>
</html>