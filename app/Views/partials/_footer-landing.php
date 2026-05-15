<?php
/**
 * _footer-landing.php
 *
 * Footer cho Landing Page.
 * Gồm: Logo + tagline, Navigation links, Copyright.
 *
 * Quy tắc:
 *   - KHÔNG có inline style, KHÔNG có inline script (ViewLayer Guide Phần 1)
 *   - Dùng Tailwind utility class trực tiếp thay vì BEM custom class
 *   - Không có mục "Bảng giá" theo yêu cầu
 */
?>
<footer class="bg-gray-900 text-gray-300" role="contentinfo">

    <!-- ── Main footer content ───────────────────────────────────── -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">

            <!-- Brand Column -->
            <div class="md:col-span-2">
                <a
                    href="<?= url('/') ?>"
                    class="inline-flex items-center gap-2 mb-4"
                    aria-label="BugTracker"
                >
                    <img
                        src="<?= asset('img/logo.png') ?>"
                        alt="BugTracker"
                        width="28"
                        height="28"
                        class="w-7 h-7 object-contain brightness-0 invert"
                    >
                    <span class="font-bold text-lg text-white">BugTracker</span>
                </a>
                <p class="text-sm text-gray-400 leading-relaxed max-w-xs">
                    Hệ thống quản lý lỗi dành cho nhóm phát triển phần mềm.<br>
                    Đơn giản. Hiệu quả. Miễn phí.
                </p>
            </div>

            <!-- Sản phẩm Column -->
            <div>
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">
                    Sản phẩm
                </h3>
                <ul class="space-y-2" role="list">
                    <li>
                        <a href="<?= url('/#features') ?>"
                           class="text-sm text-gray-400 hover:text-white transition-colors">
                            Tính năng
                        </a>
                    </li>
                    <li>
                        <a href="<?= url('/#how-it-works') ?>"
                           class="text-sm text-gray-400 hover:text-white transition-colors">
                            Cách hoạt động
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Tài khoản Column -->
            <div>
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider mb-4">
                    Tài khoản
                </h3>
                <ul class="space-y-2" role="list">
                    <li>
                        <a href="<?= url('register') ?>"
                           class="text-sm text-gray-400 hover:text-white transition-colors">
                            Đăng ký miễn phí
                        </a>
                    </li>
                    <li>
                        <a href="<?= url('login') ?>"
                           class="text-sm text-gray-400 hover:text-white transition-colors">
                            Đăng nhập
                        </a>
                    </li>
                    <li>
                        <a href="<?= url('terms') ?>"
                           class="text-sm text-gray-400 hover:text-white transition-colors">
                            Điều khoản sử dụng
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>

    <!-- ── Copyright bar ─────────────────────────────────────────── -->
    <div class="border-t border-gray-800">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
            <p class="text-sm text-gray-500 text-center">
                &copy; <?= date('Y') ?> BugTracker. Được xây dựng với ❤️ bởi nhóm phát triển.
            </p>
        </div>
    </div>

</footer>