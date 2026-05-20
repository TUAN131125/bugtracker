<?php
/**
 * @var string $csrf_token  Token chống tấn công giả mạo CSRF 
 * @var array $errors       Mảng lưu trữ thông báo lỗi cấu hình từ Controller 
 * @var string $old_email   Dữ liệu email cũ giữ lại khi submit lỗi
 */
use App\Helpers\Sanitizer;
?>

<div class="auth-card">
    <div class="auth-header">
        <h2 class="auth-title">Đăng nhập BugTracker</h2>
        <p class="auth-subtitle">Quản lý và theo dõi lỗi làm việc nhóm hiệu quả</p>
    </div>

    <?php if (!empty($errors['global'])): ?>
        <div class="alert alert-danger">
            <?= Sanitizer::escape($errors['global']) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
            <?= Sanitizer::escape($_SESSION['flash_success']) ?>
        </div>
    <?php endif; ?>

    <form action="/login" method="POST" class="auth-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">

        <div class="form-group">
            <label for="email">Địa chỉ Email</label>
            <input type="email" name="email" id="email" class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>" 
                   placeholder="name@company.com" value="<?= Sanitizer::escape($old_email ?? '') ?>" required>
            <?php if (!empty($errors['email'])): ?>
                <div class="invalid-feedback"><?= Sanitizer::escape($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <div class="form-label-group">
                <label for="password">Mật khẩu</label>
                <a href="/forgot-password" class="text-small link-primary">Quên mật khẩu?</a>
            </div>
            <input type="password" name="password" id="password" class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>" 
                   placeholder="••••••••" required>
            <?php if (!empty($errors['password'])): ?>
                <div class="invalid-feedback"><?= Sanitizer::escape($errors['password']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group checkbox-group">
            <label class="custom-checkbox">
                <input type="checkbox" name="remember_me" value="1">
                <span class="checkbox-text">Ghi nhớ đăng nhập trên thiết bị này</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
    </form>

    <div class="auth-footer">
        <span>Chưa có tài khoản?</span>
        <a href="/register" class="link-primary font-weight-bold">Đăng ký mới</a>
    </div>
</div>