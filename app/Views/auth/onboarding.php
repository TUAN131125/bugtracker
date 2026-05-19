<?php $layout = 'auth'; ?>
<?php
/**
 * onboarding.php – Màn hình Onboarding chuẩn cấu trúc View Layer (Chống crash biến)
 */
$errors            = $errors            ?? [];
$old_input         = $old_input         ?? [];
$current_user_name = $current_user_name ?? '';

// Tự động đồng bộ biến CSRF để chống lỗi Fatal Error trên PHP 8
$activeCsrf = $csrfToken ?? $csrf_token ?? ''; 
?>

<div class="auth-card-wrapper onboarding-wrapper">

    <div class="auth-header">
        <a href="<?= url('/') ?>" class="auth-logo" aria-label="BugTracker – Trang chủ">
            <img src="<?= asset('img/logo.png') ?>" alt="BugTracker" width="36" height="36" class="auth-logo__img">
        </a>
        <h1 class="auth-title">Chào mừng, <?= htmlspecialchars($current_user_name, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>!</h1>
        <p class="auth-subtitle">Thiết lập không gian làm việc của bạn để bắt đầu săn bug.</p>
    </div>

    <div class="onboarding-options">
        <div class="onboarding-card">
            <div class="onboarding-card__body">
                <h3>Tạo Workspace mới</h3>
                <p>Khởi tạo một không gian làm việc độc lập cho đội ngũ của bạn.</p>
                <button type="button" class="btn btn--secondary" onclick="document.getElementById('form-create').hidden = false; document.getElementById('form-join').hidden = true;">Chọn</button>
            </div>
            
            <div id="form-create" class="onboarding-card__panel" hidden>
                <form method="POST" action="<?= url('workspace/create') ?>" data-form-validate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($activeCsrf, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

                    <div class="form-group <?= !empty($errors['name']) ? 'form-group--error' : '' ?>">
                        <label class="form-label" for="ws-name">Tên Workspace</label>
                        <input type="text" id="ws-name" name="name" class="form-control" required data-validate="required|min:2" value="<?= htmlspecialchars($old_input['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                    </div>

                    <button type="submit" class="btn btn--primary" data-submit-btn>
                        <span class="btn__text">Tạo Không Gian Làm Việc</span>
                        <span class="btn__spinner" hidden>⏳</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="onboarding-card">
            <div class="onboarding-card__body">
                <h3>Tham gia Workspace</h3>
                <p>Nhập mã mời (Token) bạn nhận được từ Email để vào nhóm có sẵn.</p>
                <button type="button" class="btn btn--secondary" onclick="document.getElementById('form-join').hidden = false; document.getElementById('form-create').hidden = true;">Chọn</button>
            </div>

            <div id="form-join" class="onboarding-card__panel" hidden>
                <form method="POST" action="<?= url('workspace/join') ?>" data-form-validate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($activeCsrf, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

                    <div class="form-group <?= !empty($errors['invite_code']) ? 'form-group--error' : '' ?>">
                        <label class="form-label" for="invite-code">Mã mời (Invite Code)</label>
                        <input type="text" id="invite-code" name="invite_code" class="form-control" required data-validate="required">
                    </div>

                    <button type="submit" class="btn btn--primary" data-submit-btn>
                        <span class="btn__text">Gia Nhập Ngay</span>
                        <span class="btn__spinner" hidden>⏳</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="auth-footer-text">
        Không phải bạn? 
        <form method="POST" action="<?= url('logout') ?>" class="auth-footer-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($activeCsrf, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
            <button type="submit" class="auth-footer-link auth-footer-link--btn">Đăng xuất</button>
        </form>
    </div>

</div>