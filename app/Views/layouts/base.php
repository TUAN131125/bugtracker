<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!--
        CSRF token trong meta tag – api.js đọc để tự động gắn vào mọi AJAX request.
        Theo ViewLayer Guide Phần 8.2.
    -->
    <meta name="csrf-token" content="<?= htmlspecialchars($data['csrfToken'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

    <title><?= htmlspecialchars($data['pageTitle'] ?? 'BugTracker', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> – BugTracker</title>

    <!-- Tailwind CSS 3.x CDN – blocking, load trước render (ViewLayer Guide Phần 1.3) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Animate.css – cho toast/modal transitions (ViewLayer Guide Phần 1.3) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet">

    <!--
        CSS nội bộ – load sau CDN.
        FIX: xóa inline <style> body { font-family: ... } vi phạm ViewLayer Guide Phần 1.
        Font Inter đã được khai báo trong _typography.css thông qua app.css.
    -->
    <link rel="stylesheet" href="/assets/css/app.css">

    <!--
        Chart.js – chỉ load trên trang dashboard (ViewLayer Guide Phần 1.3, Phần 9.4).
        WHY conditional: Chart.js ~220KB, không load trên trang không cần chart.
    -->
    <?php if (in_array($data['pageId'] ?? '', ['login', 'register', 'forgot-password'], true)): ?>
        <link rel="stylesheet" href="/assets/css/_auth.css">
    <?php endif; ?>

    <?php if (($data['pageId'] ?? '') === 'dashboard'): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js" defer></script>
    <?php endif; ?>

    <!--
        marked.js – chỉ load trên trang issue-form và issue-detail (ViewLayer Guide Phần 1.3).
        WHY conditional: marked.js ~80KB, không load toàn bộ trang.
    -->
    <?php if (in_array($data['pageId'] ?? '', ['issue-form', 'issue-detail'], true)): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js" defer></script>
    <?php endif; ?>
</head>
<body class="bg-gray-50 text-gray-900" data-page="<?= htmlspecialchars($data['pageId'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

    <?php
        // Render view content
        if (isset($viewPath) && file_exists(__DIR__ . '/../' . $viewPath . '.php')) {
            require __DIR__ . '/../' . $viewPath . '.php';
        }
    ?>

    <!-- Toast container – PHP flash messages render vào đây (ViewLayer Guide Phần 4.2) -->
    <?php require __DIR__ . '/../partials/_toast-container.php'; ?>

    <!--
        JS Load Strategy (ViewLayer Guide Phần 9.2):
        - type="module": bắt buộc vì các file dùng export/import ES6
        - type="module" tự động defer – không cần thêm defer attribute
        - Thứ tự: core trước (utils → toast → api) → page-specific sau
        WHY utils trước: toast.js và api.js import từ utils.js (debounce, escapeHtml...)
        WHY api trước page scripts: mọi page script đều gọi API qua api.js
    -->
    <script type="module" src="/assets/js/core/utils.js"></script>
    <script type="module" src="/assets/js/core/toast.js"></script>
    <script type="module" src="/assets/js/core/api.js"></script>
    <script type="module" src="/assets/js/core/modal.js"></script>
    <script type="module" src="/assets/js/core/validator.js"></script>

    <!--
        Page-specific script – chỉ load đúng module cho trang hiện tại.
        app.js đọc data-page attribute và init module tương ứng
        (ViewLayer Guide Phần 9.2 – tránh load tất cả JS trên mọi trang).
    -->
    <?php if (!empty($data['pageId'])): ?>
        <script type="module" src="/assets/js/pages/<?= htmlspecialchars($data['pageId'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>.js"></script>
    <?php endif; ?>

</body>
</html>