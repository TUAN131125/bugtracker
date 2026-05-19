<?php
/**
 * forgot-password.php – Trang quên mật khẩu
 *
 * Khai báo layout – Response::view() sẽ wrap $content vào auth.php.
 * PHẢI đặt trước bất kỳ output nào.
 */
$layout = 'auth';

/**
 * Biến nhận từ PasswordResetController::showForgotForm():
 *   array  $errors     – lỗi validation
 *   array  $old_input  – giá trị cũ
 *   string $csrf_token – CSRF token
 *   bool   $email_sent – true nếu đã gửi email (hiện step 2)
 */

$errors     = $errors     ?? [];
$old_input  = $old_input  ?? [];
$email_sent = $email_sent ?? false;
?>

<div class="min-h-screen bg-slate-50 flex flex-col justify-center
            py-12 px-4 sm:px-6 lg:px-8">

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center mb-8">
        <a href="<?= url('/') ?>" class="inline-flex items-center gap-2.5 mb-6">
            <img src="<?= asset('img/logo.png') ?>"
                 alt="BugTracker" width="36" height="36"
                 class="w-9 h-9 object-contain">
            <span class="text-xl font-bold text-gray-900 tracking-tight">BugTracker</span>
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Đặt lại mật khẩu</h1>
        <p class="mt-1 text-sm text-gray-500">
            Nhập email để nhận link đặt lại mật khẩu.
        </p>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 px-8 py-8">

            <?php if ($email_sent): ?>
                <!-- ── Step 2: Đã gửi ────────────────────────── -->
                <div class="text-center py-4">
                    <div class="w-14 h-14 bg-green-100 rounded-full
                                flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600" fill="none"
                             stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 mb-2">
                        Kiểm tra hộp thư của bạn
                    </h2>
                    <p class="text-sm text-gray-500 mb-6 leading-relaxed">
                        Nếu email tồn tại trong hệ thống, chúng tôi đã gửi link
                        đặt lại mật khẩu. Link có hiệu lực trong <strong>1 giờ</strong>.
                    </p>
                    <a href="<?= url('login') ?>"
                       class="inline-flex items-center gap-1.5 text-sm
                              font-medium text-blue-600 hover:text-blue-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Quay lại đăng nhập
                    </a>
                </div>

            <?php else: ?>
                <!-- ── Step 1: Form ──────────────────────────── -->
                <form method="POST" action="<?= url('forgot-password') ?>"
                      data-form-validate novalidate>

                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

                    <div class="mb-6">
                        <label for="email"
                               class="block text-sm font-medium text-gray-700 mb-1.5">
                            Địa chỉ Email
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            autocomplete="email"
                            autofocus
                            required
                            data-validate="required|email"
                            value="<?= htmlspecialchars($old_input['email'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            aria-describedby="<?= !empty($errors['email']) ? 'email-error' : '' ?>"
                            aria-invalid="<?= !empty($errors['email']) ? 'true' : 'false' ?>"
                            class="w-full px-3.5 py-2.5 text-sm text-gray-900
                                   bg-white border rounded-xl outline-none
                                   transition-colors duration-150
                                   placeholder:text-gray-400
                                   focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   <?= !empty($errors['email'])
                                       ? 'border-red-400 bg-red-50'
                                       : 'border-gray-300 hover:border-gray-400' ?>"
                            placeholder="you@example.com"
                        >
                        <?php if (!empty($errors['email'])): ?>
                            <p id="email-error" role="alert"
                               class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor"
                                     viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <?= htmlspecialchars($errors['email'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <button
                        type="submit"
                        data-submit-btn
                        class="w-full flex items-center justify-center gap-2
                               px-4 py-2.5
                               bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                               text-white text-sm font-semibold
                               rounded-xl shadow-sm shadow-blue-600/20
                               transition-all duration-150
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                               disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span class="btn__text">Gửi link đặt lại</span>
                        <span class="btn__spinner hidden" aria-hidden="true">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </span>
                    </button>

                </form>

                <p class="mt-6 text-center">
                    <a href="<?= url('login') ?>"
                       class="inline-flex items-center gap-1.5 text-sm
                              font-medium text-gray-500 hover:text-gray-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Quay lại đăng nhập
                    </a>
                </p>

            <?php endif; ?>

        </div>
    </div>

</div>