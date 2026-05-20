<?php
/**
 * @var string $view_content Nội dung HTML lõi của form xác thực
 */
?>
<div class="auth-layout-container">
    <div class="auth-centered-wrapper">
        <div class="auth-brand-logo text-center mb-4">
            <a href="/">
                <img src="/assets/img/logo.svg" alt="BugTracker Logo" class="brand-logo-img">
                <span class="brand-logo-text">BugTracker</span>
            </a>
        </div>

        <div class="auth-main-card-body">
            <?= $view_content ?>
        </div>

        <div class="auth-minimal-footer text-center mt-4 text-small text-muted">
            <p>&copy; <?= date('Y') ?> BugTracker. All rights reserved.</p>
        </div>
    </div>
</div>