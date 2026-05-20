<?php
/**
 * @var string $csrf_token  Token chống tấn công giả mạo CSRF [cite: 180, 286]
 * @var array $errors       Mảng lưu lỗi kiểm tra phía Server [cite: 286]
 * @var array $old          Mảng lưu lại dữ liệu cũ đã nhập khi form bị trả về
 */
?>

<div class="auth-card">
    <div class="auth-header">
        <h2 class="auth-title">Tạo tài khoản mới</h2>
        <p class="auth-subtitle">Bắt đầu chuẩn hóa chu trình sửa lỗi và kiểm thử phần mềm</p>
    </div>

    <form action="/register" method="POST" class="auth-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">

        <div class="form-group">
            <label for="name">Họ và tên đầy đủ</label>
            <input type="text" name="name" id="name" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" 
                   placeholder="Ví dụ: Nguyễn Văn A" value="<?= Sanitizer::escape($old['name'] ?? '') ?>" required>
            <?php if (!empty($errors['name'])): ?>
                <div class="invalid-feedback"><?= Sanitizer::escape($errors['name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">Địa chỉ Email</label>
            <input type="email" name="email" id="email" class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>" 
                   placeholder="email@example.com" value="<?= Sanitizer::escape($old['email'] ?? '') ?>" required>
            <?php if (!empty($errors['email'])): ?>
                <div class="invalid-feedback"><?= Sanitizer::escape($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password">Mật khẩu</label>
            <input type="password" name="password" id="password" class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>" 
                   placeholder="Tối thiểu 8 ký tự" required>
            [cite_start]<small class="form-text text-muted">Yêu cầu có ít nhất 8 ký tự, bao gồm cả chữ hoa và chữ số[cite: 114, 286].</small>
            <?php if (!empty($errors['password'])): ?>
                <div class="invalid-feedback"><?= Sanitizer::escape($errors['password']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="password_confirm">Xác nhận mật khẩu</label>
            <input type="password" name="password_confirm" id="password_confirm" class="form-control <?= !empty($errors['password_confirm']) ? 'is-invalid' : '' ?>" 
                   placeholder="Nhập lại mật khẩu phía trên" required>
            <?php if (!empty($errors['password_confirm'])): ?>
                <div class="invalid-feedback"><?= Sanitizer::escape($errors['password_confirm']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Đăng ký tài khoản</button>
    </form>

    <div class="auth-footer">
        <span>Đã có tài khoản?</span>
        <a href="/login" class="link-primary font-weight-bold">Đăng nhập ngay</a>
    </div>
</div>