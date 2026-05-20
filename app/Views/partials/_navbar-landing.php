<?php
/**
 * _navbar-landing.php
 *
 * Navigation bar công khai cho Landing Page.
 * Sticky top, transparent khi đầu trang, solid khi scroll.
 * JS trong landing.js xử lý class toggle khi scroll (thêm class 'scrolled').
 *
 * Quy tắc:
 *   - KHÔNG có inline style, KHÔNG có inline script (ViewLayer Guide Phần 1)
 *   - Dùng Tailwind utility class trực tiếp thay vì BEM custom class
 *   - ARIA labels đầy đủ cho icon-only buttons (ViewLayer Guide Phần 8.3)
 */
?>
<header
    id="landing-navbar"
    role="banner"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-300
           bg-transparent"
    data-navbar
>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- ── Logo ─────────────────────────────────────────── -->
            <a
                href="<?= url('/') ?>"
                class="flex items-center gap-2 flex-shrink-0"
                aria-label="BugTracker – Trang chủ"
            >
                <img
                    src="<?= asset('img/logo.png') ?>"
                    alt="BugTracker logo"
                    width="32"
                    height="32"
                    class="w-8 h-8 object-contain"
                >
                <span class="font-bold text-lg text-gray-900 tracking-tight">
                    BugTracker
                </span>
            </a>

            <!-- ── Desktop Nav ────────────────────────────────────── -->
            <nav
                class="hidden md:flex items-center gap-6"
                aria-label="Menu chính"
            >
                <a href="<?= url('/#features') ?>"
                   class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
                    Tính năng
                </a>
                <a href="<?= url('/#how-it-works') ?>"
                   class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors">
                    Cách hoạt động
                </a>
            </nav>

            <!-- ── CTA Buttons (desktop) ──────────────────────────── -->
            <div class="hidden md:flex items-center gap-3">
                <a
                    href="<?= url('login') ?>"
                    class="text-sm font-medium text-gray-600 hover:text-gray-900
                           px-4 py-2 rounded-lg transition-colors"
                >
                    Đăng nhập
                </a>
                <a
                    href="<?= url('register') ?>"
                    class="text-sm font-semibold text-white bg-blue-600
                           hover:bg-blue-700 px-4 py-2 rounded-lg
                           transition-colors shadow-sm"
                >
                    Dùng miễn phí
                </a>
            </div>

            <!-- ── Hamburger (mobile) ─────────────────────────────── -->
            <button
                class="md:hidden flex flex-col justify-center items-center
                       w-10 h-10 gap-1.5 rounded-lg
                       hover:bg-gray-100 transition-colors js-hamburger"
                aria-label="Mở menu"
                aria-expanded="false"
                aria-controls="mobile-menu"
            >
                <span class="block w-5 h-0.5 bg-gray-700 transition-all"></span>
                <span class="block w-5 h-0.5 bg-gray-700 transition-all"></span>
                <span class="block w-5 h-0.5 bg-gray-700 transition-all"></span>
            </button>

        </div>
    </div>

    <!-- ── Mobile Menu ────────────────────────────────────────────── -->
    <div
        id="mobile-menu"
        aria-hidden="true"
        hidden
        class="md:hidden bg-white border-t border-gray-100 shadow-lg"
    >
        <nav class="max-w-6xl mx-auto px-4 py-4 flex flex-col gap-1"
             aria-label="Menu mobile">
            <a href="<?= url('/#features') ?>"
               class="text-sm font-medium text-gray-700 hover:text-blue-600
                      hover:bg-blue-50 px-3 py-2 rounded-lg transition-colors">
                Tính năng
            </a>
            <a href="<?= url('/#how-it-works') ?>"
               class="text-sm font-medium text-gray-700 hover:text-blue-600
                      hover:bg-blue-50 px-3 py-2 rounded-lg transition-colors">
                Cách hoạt động
            </a>

            <hr class="my-2 border-gray-100">

            <a href="<?= url('login') ?>"
               class="text-sm font-medium text-gray-700 hover:text-blue-600
                      hover:bg-blue-50 px-3 py-2 rounded-lg transition-colors">
                Đăng nhập
            </a>
            <a href="<?= url('register') ?>"
               class="text-sm font-semibold text-white bg-blue-600
                      hover:bg-blue-700 px-3 py-2 rounded-lg
                      text-center transition-colors mt-1">
                Dùng miễn phí
            </a>
        </nav>
    </div>

</header>

<!-- Spacer để nội dung không bị navbar fixed che khuất -->
<div class="h-16" aria-hidden="true"></div>