<?php
/**
 * @var string $view_content Nội dung HTML của trang giới thiệu
 */
?>
<div class="landing-layout-wrapper">
    <nav class="landing-navbar">
        <div class="landing-nav-container">
            <a href="/" class="landing-nav-brand">
                <img src="/assets/img/logo.svg" alt="Logo" class="nav-logo-icon">
                <span class="nav-brand-name">BugTracker</span>
            </a>
            <div class="landing-nav-links-group">
                <a href="/#features" class="nav-link-item">Tính năng</a>
                <a href="/#pricing" class="nav-link-item">Bảng giá</a>
                <a href="/login" class="btn btn-secondary-outline btn-sm mr-2">Đăng nhập</a>
                <a href="/register" class="btn btn-primary btn-sm">Dùng thử miễn phí</a>
            </div>
        </div>
    </nav>

    <main class="landing-main-content">
        <?= $view_content ?>
    </main>

    <footer class="landing-footer-wrapper">
        <?php include __DIR__ . '/../partials/_footer-landing.php'; ?>
    </footer>
</div>