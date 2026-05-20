<?php
/**
 * @var string $csrf_token  Token chống tấn công giả mạo CSRF [cite: 180]
 * @var array $errors       Mảng chứa thông báo lỗi biểu mẫu
 * 
 */
use App\Helpers\Sanitizer;
?>

<div class="auth-card">
    <div class="auth-header">
        <h2 class="auth-title">Khôi phục mật khẩu</h2>
        <p class="auth-subtitle">Nhập email đăng ký, hệ thống sẽ gửi liên kết đặt lại mật khẩu an toàn.</p>
    </div>

    <?php if (!empty($errors['global'])): ?>
        <div class="alert alert-danger">
            <?= Sanitizer::escape($errors['global']) ?>
        </div>
    <?php endif; ?>

    <form action="/forgot-password" method="POST" class="auth-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">

        <div class="form-group">
            <label for="email">Email tài khoản</label>
            <input type="email" name="email" id="email" class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>" 
                   placeholder="your-email@domain.com" required>
            <?php if (!empty($errors['email'])): ?>
                <div class="invalid-feedback"><?= Sanitizer::escape($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Gửi liên kết khôi phục</button>
    </form>

    <div class="auth-footer">
        <a href="/login" class="link-secondary text-small">← Quay lại trang đăng nhập</a>
    </div>
</div>