<?php
/**
 * login.php – Trang đăng nhập
 *
 * Khai báo layout – Response::view() sẽ wrap $content vào auth.php.
 * PHẢI đặt trước bất kỳ output nào.
 */
$layout = 'auth';

/**
 * Biến nhận từ LoginController::showForm():
 *   array  $errors     – lỗi validation từ server (keyed by field)
 *   array  $old_input  – giá trị cũ để giữ lại khi có lỗi
 *   string $csrf_token – CSRF token
 *   string $flash_error   – flash message lỗi từ session
 *   string $flash_success – flash message thành công (VD: sau reset password)
 *
 * Quy tắc ViewLayer Guide:
 *   - KHÔNG inline style, KHÔNG inline script
 *   - Mọi output PHP qua htmlspecialchars()
 *   - Form POST có CSRF hidden field
 *   - ARIA labels đầy đủ
 */

$errors    = $errors    ?? [];
$old_input = $old_input ?? [];
?>

<div class="min-h-screen bg-slate-50 flex flex-col justify-center
            py-12 px-4 sm:px-6 lg:px-8">

    <!-- ── Header ────────────────────────────────────────────────── -->
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center mb-8">
        <a href="<?= url('/') ?>" class="inline-flex items-center gap-2.5 mb-6">
            <img src="<?= asset('img/logo.png') ?>"
                 alt="BugTracker" width="36" height="36"
                 class="w-9 h-9 object-contain">
            <span class="text-xl font-bold text-gray-900 tracking-tight">BugTracker</span>
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Đăng nhập</h1>
        <p class="mt-1 text-sm text-gray-500">
            Chào mừng trở lại!
        </p>
    </div>

    <!-- ── Card ──────────────────────────────────────────────────── -->
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 px-8 py-8">

            <!-- Flash messages -->
            <?php if (!empty($flash_error)): ?>
                <div role="alert"
                     class="flex items-start gap-3 bg-red-50 border border-red-200
                            text-red-700 text-sm rounded-xl px-4 py-3 mb-6">
                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <?= htmlspecialchars($flash_error, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($flash_success)): ?>
                <div role="alert"
                     class="flex items-start gap-3 bg-green-50 border border-green-200
                            text-green-700 text-sm rounded-xl px-4 py-3 mb-6">
                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <?= htmlspecialchars($flash_success, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="<?= url('login') ?>"
                  data-form-validate
                  novalidate>

                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">

                <!-- Email -->
                <div class="mb-5">
                    <label for="email"
                           class="block text-sm font-medium text-gray-700 mb-1.5">
                        Email
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
                                   ? 'border-red-400 bg-red-50 focus:ring-red-400 focus:border-red-400'
                                   : 'border-gray-300 hover:border-gray-400' ?>"
                        placeholder="you@example.com"
                    >
                    <?php if (!empty($errors['email'])): ?>
                        <p id="email-error" role="alert"
                           class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?= htmlspecialchars($errors['email'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Password -->
                <div class="mb-5">
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password"
                               class="block text-sm font-medium text-gray-700">
                            Mật khẩu
                        </label>
                        <a href="<?= url('forgot-password') ?>"
                           class="text-xs text-blue-600 hover:text-blue-700
                                  font-medium transition-colors">
                            Quên mật khẩu?
                        </a>
                    </div>
                    <div class="relative">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            data-validate="required"
                            aria-describedby="<?= !empty($errors['password']) ? 'password-error' : '' ?>"
                            aria-invalid="<?= !empty($errors['password']) ? 'true' : 'false' ?>"
                            class="w-full px-3.5 py-2.5 pr-11 text-sm text-gray-900
                                   bg-white border rounded-xl outline-none
                                   transition-colors duration-150
                                   focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                   <?= !empty($errors['password'])
                                       ? 'border-red-400 bg-red-50 focus:ring-red-400 focus:border-red-400'
                                       : 'border-gray-300 hover:border-gray-400' ?>"
                            placeholder="••••••••"
                        >
                        <!-- Toggle show/hide – JS trong auth.js -->
                        <button
                            type="button"
                            class="js-toggle-password absolute right-3 top-1/2 -translate-y-1/2
                                   text-gray-400 hover:text-gray-600 transition-colors"
                            aria-label="Hiện/ẩn mật khẩu"
                            tabindex="-1"
                        >
                            <svg class="w-5 h-5 js-eye-icon" fill="none" stroke="currentColor"
                                 viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <?php if (!empty($errors['password'])): ?>
                        <p id="password-error" role="alert"
                           class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?= htmlspecialchars($errors['password'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center mb-6">
                    <input
                        type="checkbox"
                        id="remember_me"
                        name="remember_me"
                        value="1"
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded
                               focus:ring-2 focus:ring-blue-500 cursor-pointer"
                    >
                    <label for="remember_me"
                           class="ml-2 text-sm text-gray-600 cursor-pointer select-none">
                        Ghi nhớ đăng nhập
                    </label>
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    data-submit-btn
                    class="w-full flex items-center justify-center gap-2
                           px-4 py-2.5
                           bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                           text-white text-sm font-semibold
                           rounded-xl
                           shadow-sm shadow-blue-600/20
                           transition-all duration-150
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                           disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span class="btn__text">Đăng nhập</span>
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

            <!-- Divider + Register link -->
            <p class="mt-6 text-center text-sm text-gray-500">
                Chưa có tài khoản?
                <a href="<?= url('register') ?>"
                   class="font-semibold text-blue-600 hover:text-blue-700 transition-colors">
                    Đăng ký miễn phí
                </a>
            </p>

        </div>
    </div>

</div>