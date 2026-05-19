<?php
/**
 * register.php – Trang đăng ký
 *
 * Khai báo layout – Response::view() sẽ wrap $content vào auth.php.
 * PHẢI đặt trước bất kỳ output nào.
 */
$layout = 'auth';

/**
 * Biến nhận từ RegisterController::showForm():
 *   array  $errors     – lỗi validation từ server
 *   array  $old_input  – giá trị cũ (trừ password)
 *   string $csrfToken  – CSRF token
 *   string $invite_email – email đã điền sẵn từ link mời (read-only)
 */

$errors       = $errors       ?? [];
$old_input    = $old_input    ?? [];
$invite_email = $invite_email ?? '';
$has_invite   = !empty($invite_email);
?>

<div class="auth-card-wrapper">

    <!-- ── Header ────────────────────────────────────────────────── -->
    <div class="auth-header">
        <a href="<?= url('/') ?>" class="auth-logo">
            <img src="<?= asset('img/logo.png') ?>"
                 alt="BugTracker" width="36" height="36"
                 class="auth-logo__img">
            <span class="auth-logo__name">BugTracker</span>
        </a>
        <h1 class="auth-title">
            <?= $has_invite ? 'Tạo tài khoản để tham gia' : 'Tạo tài khoản' ?>
        </h1>
        <p class="auth-subtitle">
            <?= $has_invite
                ? 'Bạn được mời tham gia Workspace. Hoàn tất đăng ký để tiếp tục.'
                : 'Miễn phí · Không cần thẻ tín dụng'
            ?>
        </p>
    </div>

    <!-- ── Card ──────────────────────────────────────────────────── -->
    <div class="auth-card">

        <form method="POST" action="<?= url('register') ?>"
              data-form-validate
              novalidate>

            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

            <!-- Họ tên -->
            <div class="form-group <?= !empty($errors['name']) ? 'form-group--error' : '' ?>">
                <label for="name" class="form-label">
                    Họ và tên <span class="form-required" aria-hidden="true">*</span>
                </label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    autocomplete="name"
                    autofocus
                    required
                    data-validate="required|min:2"
                    value="<?= htmlspecialchars($old_input['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                    aria-describedby="<?= !empty($errors['name']) ? 'name-error' : '' ?>"
                    aria-invalid="<?= !empty($errors['name']) ? 'true' : 'false' ?>"
                    class="form-input"
                    placeholder="Nguyễn Văn A"
                >
                <?php if (!empty($errors['name'])): ?>
                    <p id="name-error" role="alert" class="form-error">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <?= htmlspecialchars($errors['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="form-group <?= !empty($errors['email']) ? 'form-group--error' : '' ?>">
                <label for="email" class="form-label">
                    Email <span class="form-required" aria-hidden="true">*</span>
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    autocomplete="email"
                    required
                    <?= $has_invite ? 'readonly aria-readonly="true"' : '' ?>
                    data-validate="required|email"
                    value="<?= htmlspecialchars(
                        $has_invite ? $invite_email : ($old_input['email'] ?? ''),
                        ENT_QUOTES | ENT_HTML5, 'UTF-8'
                    ) ?>"
                    aria-describedby="<?= !empty($errors['email']) ? 'email-error' : ($has_invite ? 'email-hint' : '') ?>"
                    aria-invalid="<?= !empty($errors['email']) ? 'true' : 'false' ?>"
                    class="form-input <?= $has_invite ? 'form-input--readonly' : '' ?>"
                    placeholder="you@example.com"
                >
                <?php if ($has_invite): ?>
                    <p id="email-hint" class="form-hint">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        Email được điền sẵn từ lời mời và không thể thay đổi.
                    </p>
                <?php elseif (!empty($errors['email'])): ?>
                    <p id="email-error" role="alert" class="form-error">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <?= htmlspecialchars($errors['email'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group <?= !empty($errors['password']) ? 'form-group--error' : '' ?>">
                <label for="password" class="form-label">
                    Mật khẩu <span class="form-required" aria-hidden="true">*</span>
                </label>
                <div class="form-input-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        required
                        data-validate="required|min:8"
                        aria-describedby="password-strength <?= !empty($errors['password']) ? 'password-error' : '' ?>"
                        aria-invalid="<?= !empty($errors['password']) ? 'true' : 'false' ?>"
                        class="form-input form-input--has-icon-right"
                        placeholder="Tối thiểu 8 ký tự"
                    >
                    <button type="button"
                            class="form-input__icon-right js-toggle-password"
                            aria-label="Hiện/ẩn mật khẩu"
                            tabindex="-1">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>

                <!-- Password strength bar -->
                <div id="password-strength" class="password-strength" aria-live="polite" aria-atomic="true">
                    <div class="password-strength__bars">
                        <div class="js-strength-bar password-strength__bar"></div>
                        <div class="js-strength-bar password-strength__bar"></div>
                        <div class="js-strength-bar password-strength__bar"></div>
                        <div class="js-strength-bar password-strength__bar"></div>
                    </div>
                    <p class="js-strength-label password-strength__label"></p>
                </div>

                <?php if (!empty($errors['password'])): ?>
                    <p id="password-error" role="alert" class="form-error">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <?= htmlspecialchars($errors['password'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Confirm password -->
            <div class="form-group <?= !empty($errors['password_confirm']) ? 'form-group--error' : '' ?>">
                <label for="password_confirm" class="form-label">
                    Xác nhận mật khẩu <span class="form-required" aria-hidden="true">*</span>
                </label>
                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    autocomplete="new-password"
                    required
                    data-validate="required"
                    data-validate-match="password"
                    aria-describedby="<?= !empty($errors['password_confirm']) ? 'confirm-error' : '' ?>"
                    aria-invalid="<?= !empty($errors['password_confirm']) ? 'true' : 'false' ?>"
                    class="form-input"
                    placeholder="Nhập lại mật khẩu"
                >
                <?php if (!empty($errors['password_confirm'])): ?>
                    <p id="confirm-error" role="alert" class="form-error">
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <?= htmlspecialchars($errors['password_confirm'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Submit -->
            <button type="submit" class="auth-btn" data-submit-btn>
                <span class="btn__text">Tạo tài khoản</span>
                <span class="btn__spinner" aria-hidden="true" hidden>
                    <svg class="spinner-icon" viewBox="0 0 24 24" fill="none">
                        <circle class="spinner-track" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="spinner-fill" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </span>
            </button>

        </form>

        <p class="auth-footer-text">
            Đã có tài khoản?
            <a href="<?= url('login') ?>" class="auth-footer-link">
                Đăng nhập
            </a>
        </p>

    </div>

</div>