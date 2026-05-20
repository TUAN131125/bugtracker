<?php
/**
 * @var string $csrf_token  Token chống tấn công giả mạo CSRF [cite: 180]
 * @var array $errors       Mảng chứa thông báo lỗi xử lý mã mời [cite: 290]
 */
?>

<div class="onboarding-container">
    <div class="onboarding-header text-center">
        <h1 class="onboarding-title">Chào mừng bạn đến với BugTracker!</h1>
        [cite_start]<p class="onboarding-subtitle">Để bắt đầu làm việc, vui lòng lựa chọn một trong hai phương thức thiết lập không gian dưới đây[cite: 120].</p>
    </div>

    <div class="onboarding-grid">
        <div class="card onboarding-card text-center">
            <div class="card-icon">🏢</div>
            <h3 class="card-title">Tạo Workspace mới</h3>
            [cite_start]<p class="card-text">Dành cho Quản lý hoặc Trưởng nhóm muốn xây dựng một không gian làm việc độc lập cho doanh nghiệp hoặc dự án của mình[cite: 121, 290].</p>
            <a href="/workspace/create" class="btn btn-primary btn-block">Bắt đầu khởi tạo</a>
        </div>

        <div class="card onboarding-card">
            <div class="text-center">
                <div class="card-icon">✉️</div>
                <h3 class="card-title">Tham gia Workspace có sẵn</h3>
                [cite_start]<p class="card-text">Nhập mã mời do Quản trị viên của bạn cung cấp hoặc chờ đợi liên kết xác nhận trực tiếp gửi đến hòm thư[cite: 122, 290].</p>
            </div>

            <form action="/onboarding/join-code" method="POST" class="onboarding-inline-form mt-3" novalidate>
                <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                
                <div class="form-group">
                    <label for="invite_code" class="sr-only">Mã mời Workspace</label>
                    <input type="text" name="invite_code" id="invite_code" class="form-control text-center <?= !empty($errors['invite_code']) ? 'is-invalid' : '' ?>" 
                           placeholder="Nhập mã mời (Ví dụ: WS-XYZ123)" required>
                    <?php if (!empty($errors['invite_code'])): ?>
                        <div class="invalid-feedback text-center"><?= Sanitizer::escape($errors['invite_code']) ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-secondary btn-block">Kích hoạt tham gia</button>
            </form>
        </div>
    </div>
</div>