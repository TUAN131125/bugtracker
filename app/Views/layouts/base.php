<?php
/**
 * @var string $page_title  Tiêu đề hiển thị trên tab trình duyệt
 * @var string $page_id     Định danh duy nhất của trang (Dùng cho data-page attr)
 * @var string $sub_layout  Đường dẫn đến layout con (app, auth, hoặc landing)
 * @var string $view_content Nội dung HTML lõi của view chức năng
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Sanitizer::escape($page_title ?? 'BugTracker') ?></title>
    
    <link rel="shortcut icon" href="/assets/img/logo.svg" type="image/svg+xml">
    
    <link rel="stylesheet" href="/assets/css/app.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js" defer></script>
</head>
<body data-page="<?= Sanitizer::escape($page_id ?? 'default') ?>">

    <?php 
        if (!empty($sub_layout)) {
            include __DIR__ . '/' . $sub_layout . '.php';
        } else {
            echo $view_content; 
        }
    ?>

    <?php include __DIR__ . '/../partials/_toast-container.php'; ?>

    <script src="/assets/js/core/utils.js" defer></script>
    <script src="/assets/js/core/api.js" defer></script>
    <script src="/assets/js/core/toast.js" defer></script>
    <script src="/assets/js/core/modal.js" defer></script>
    <script src="/assets/js/core/validator.js" defer></script>
    
    <script src="/assets/js/app.js" defer></script>
</body>
</html>