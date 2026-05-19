<?php
/**
 * auth.php – Auth Layout
 *
 * Layout cho các trang không cần đăng nhập:
 * Login, Register, Forgot Password, Reset Password.
 *
 * Cơ chế hoạt động (Response::view()):
 *   1. View template (login.php, register.php...) set $layout = 'auth'
 *   2. Response::view() capture output của view thành $content
 *   3. Load file này, inject $content vào <?= $content ?>
 *
 * Biến nhận từ Controller (qua extract($data)):
 *   string $pageTitle  – tiêu đề trang
 *   string $pageId     – data-page attribute, auth.js dùng để init module
 *   string $csrfToken  – CSRF token cho meta tag
 *   string $content    – HTML đã render của view (inject bởi Response::view())
 */
?>
<!DOCTYPE html>
<html lang="vi" data-page="<?= htmlspecialchars($pageId ?? 'auth', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSRF token – api.js đọc để gắn vào mọi AJAX request (ViewLayer Guide Phần 8.2) -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

    <title><?= htmlspecialchars($pageTitle ?? 'BugTracker', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> – BugTracker</title>

    <!-- Favicon – dùng png vì file vật lý là logo.png -->
    <link rel="icon" type="image/png" href="<?= asset('img/logo.png') ?>">

    <!--
        Tailwind CSS 3.x CDN – PHẢI dùng <script>, không phải <link>.
        WHY <script> không phải <link>:
        cdn.tailwindcss.com là JS bundle tự sinh CSS vào <style> tag,
        không phải file .css tĩnh – dùng <link rel="stylesheet"> sẽ không hoạt động.
        Load blocking (không defer) vì cần có class trước khi render.
    -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- app.css – global tokens, reset, components dùng chung toàn hệ thống -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <!-- _auth.css – styles riêng cho auth pages, load sau app.css để có thể override -->
    <link rel="stylesheet" href="<?= asset('css/_auth.css') ?>">
</head>
<body class="auth-body">

    <main class="auth-page" id="main-content" role="main">
        <?= $content ?? '' ?>
    </main>

    <!--
        JS Load Strategy (ViewLayer Guide Phần 9.2):
        - type="module" tự động defer, không cần thêm defer attribute
        - Thứ tự: utils → api → toast → validator → page module (auth.js)
        WHY không dùng defer + type="module" cùng lúc: defer là no-op với module
    -->
    <script type="module" src="<?= asset('js/core/utils.js') ?>"></script>
    <script type="module" src="<?= asset('js/core/api.js') ?>"></script>
    <script type="module" src="<?= asset('js/core/toast.js') ?>"></script>
    <script src="<?= asset('js/core/validator.js') ?>" defer></script>

    <!--
        auth.js – xử lý password toggle, strength meter, slug auto-generate.
        Chỉ load trên auth pages (layout này), không load trên app layout.
    -->
    <script type="module" src="<?= asset('js/pages/auth.js') ?>"></script>

</body>
</html>