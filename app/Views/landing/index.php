<?php
/**
 * landing/index.php
 *
 * Landing Page nâng cấp — không dùng hình ảnh bên ngoài, không có bảng giá.
 * Toàn bộ visual dùng CSS + SVG inline + UI mockup components.
 *
 * Sections:
 *   1. Hero         – Headline + CTA + animated UI mockup thuần CSS
 *   2. Stats        – Số liệu thuyết phục
 *   3. Features     – 6 tính năng với icon SVG inline
 *   4. How It Works – 3 bước với visual CSS
 *   5. Final CTA    – Kêu gọi đăng ký
 *
 * Không có inline style hay inline script.
 * Không dùng ảnh PNG/JPG bên ngoài.
 * Mọi animation class từ _landing.css.
 */

$layout = 'landing';
?>

<!-- ============================================================
     SECTION 1: HERO
     ============================================================ -->
<section class="lp-hero" aria-labelledby="hero-heading">

    <!-- Nền lưới trang trí -->
    <div class="lp-hero__grid-bg" aria-hidden="true"></div>

    <div class="lp-hero__inner">

        <!-- ── Cột trái: Copy ── -->
        <div class="lp-hero__copy">

            <div class="lp-hero__badge">
                <span class="lp-hero__badge-dot"></span>
                Miễn phí · Không cần thẻ tín dụng
            </div>

            <h1 class="lp-hero__heading" id="hero-heading">
                Kiểm soát bug<br>
                <em class="lp-hero__heading-em">có hệ thống</em>
            </h1>

            <p class="lp-hero__sub">
                BugTracker giúp nhóm phát triển theo dõi, phân loại và
                giải quyết lỗi từ lúc phát hiện đến khi đóng ticket —
                với phân quyền rõ ràng và dashboard trực quan.
            </p>

            <div class="lp-hero__actions" 
                style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">

                <a href="<?= url('register') ?>"
                class="inline-flex items-center gap-2
                        px-7 py-3.5
                        bg-blue-600 hover:bg-blue-700
                        text-white font-semibold text-base
                        rounded-xl
                        shadow-lg shadow-blue-600/30
                        transition-all duration-200
                        hover:shadow-xl hover:shadow-blue-600/40
                        hover:-translate-y-0.5">
                    Bắt đầu miễn phí
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="currentColor"
                        style="width:18px; height:18px; flex-shrink:0;">
                        <path fill-rule="evenodd"
                            d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"
                            clip-rule="evenodd"/>
                    </svg>
                </a>

                <a href="#features"
                class="inline-flex items-center gap-2
                        px-7 py-3.5
                        bg-transparent hover:bg-blue-50
                        text-blue-600 font-semibold text-base
                        border-2 border-blue-600
                        rounded-xl
                        transition-all duration-200">
                    Khám phá tính năng
                </a>

</div>

            <div class="lp-hero__trust">
                <span class="lp-trust-item">
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 0C3.58 0 0 3.58 0 8s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm3.707 6.707l-4 4a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L7 8.586l3.293-3.293a1 1 0 011.414 1.414z"/>
                    </svg>
                    Bảo mật dữ liệu
                </span>
                <span class="lp-trust-sep" aria-hidden="true">·</span>
                <span class="lp-trust-item">
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 0C3.58 0 0 3.58 0 8s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm3.707 6.707l-4 4a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L7 8.586l3.293-3.293a1 1 0 011.414 1.414z"/>
                    </svg>
                    Không quảng cáo
                </span>
                <span class="lp-trust-sep" aria-hidden="true">·</span>
                <span class="lp-trust-item">
                    <svg aria-hidden="true" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 0C3.58 0 0 3.58 0 8s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm3.707 6.707l-4 4a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L7 8.586l3.293-3.293a1 1 0 011.414 1.414z"/>
                    </svg>
                    PHP MVC thuần
                </span>
            </div>
        </div>

        <!-- ── Cột phải: UI Mockup thuần CSS ── -->
        <div class="lp-hero__visual" aria-hidden="true">

            <!-- Cửa sổ app giả lập -->
            <div class="lp-mockup">

                <!-- Thanh tiêu đề cửa sổ -->
                <div class="lp-mockup__titlebar">
                    <span class="lp-mockup__dot lp-mockup__dot--red"></span>
                    <span class="lp-mockup__dot lp-mockup__dot--yellow"></span>
                    <span class="lp-mockup__dot lp-mockup__dot--green"></span>
                    <span class="lp-mockup__url">bugtracker.app / dashboard</span>
                </div>

                <!-- Nội dung app -->
                <div class="lp-mockup__body">

                    <!-- Sidebar giả -->
                    <div class="lp-mockup__sidebar">
                        <div class="lp-mockup__ws-avatar"></div>
                        <div class="lp-mockup__nav-item lp-mockup__nav-item--active"></div>
                        <div class="lp-mockup__nav-item"></div>
                        <div class="lp-mockup__nav-item"></div>
                        <div class="lp-mockup__nav-item"></div>
                    </div>

                    <!-- Main content giả -->
                    <div class="lp-mockup__main">

                        <!-- Stat cards -->
                        <div class="lp-mockup__stats">
                            <div class="lp-mockup__stat-card">
                                <div class="lp-mockup__stat-label"></div>
                                <div class="lp-mockup__stat-num lp-mockup__stat-num--blue">24</div>
                            </div>
                            <div class="lp-mockup__stat-card">
                                <div class="lp-mockup__stat-label"></div>
                                <div class="lp-mockup__stat-num lp-mockup__stat-num--yellow">8</div>
                            </div>
                            <div class="lp-mockup__stat-card">
                                <div class="lp-mockup__stat-label"></div>
                                <div class="lp-mockup__stat-num lp-mockup__stat-num--green">31</div>
                            </div>
                        </div>

                        <!-- Issue list giả -->
                        <div class="lp-mockup__issue-list">
                            <div class="lp-mockup__issue lp-mockup__issue--open">
                                <span class="lp-mockup__issue-badge lp-mockup__issue-badge--open">Open</span>
                                <span class="lp-mockup__issue-title">Login page XSS vulnerability</span>
                                <span class="lp-mockup__issue-id">BT-047</span>
                            </div>
                            <div class="lp-mockup__issue lp-mockup__issue--progress">
                                <span class="lp-mockup__issue-badge lp-mockup__issue-badge--progress">In Progress</span>
                                <span class="lp-mockup__issue-title">Upload timeout on large files</span>
                                <span class="lp-mockup__issue-id">BT-046</span>
                            </div>
                            <div class="lp-mockup__issue lp-mockup__issue--resolved">
                                <span class="lp-mockup__issue-badge lp-mockup__issue-badge--resolved">Resolved</span>
                                <span class="lp-mockup__issue-title">Pagination breaks on mobile</span>
                                <span class="lp-mockup__issue-id">BT-045</span>
                            </div>
                            <div class="lp-mockup__issue lp-mockup__issue--triage">
                                <span class="lp-mockup__issue-badge lp-mockup__issue-badge--triage">In Triage</span>
                                <span class="lp-mockup__issue-title">Avatar upload MIME bypass</span>
                                <span class="lp-mockup__issue-id">BT-044</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Floating notification badge -->
            <div class="lp-hero__float lp-hero__float--notif">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                </svg>
                <span>BT-047 đã được gán cho bạn</span>
            </div>

            <!-- Floating status badge -->
            <div class="lp-hero__float lp-hero__float--status">
                <span class="lp-hero__float-dot lp-hero__float-dot--green"></span>
                BT-045 → Resolved
            </div>

        </div>
    </div>
</section>


<!-- ============================================================
     SECTION 2: STATS
     ============================================================ -->
<section class="lp-stats" aria-label="Thống kê hệ thống">
    <div class="lp-stats__inner">

        <div class="lp-stats__item">
            <span class="lp-stats__num">8</span>
            <span class="lp-stats__label">Trạng thái Issue</span>
        </div>

        <div class="lp-stats__div" aria-hidden="true"></div>

        <div class="lp-stats__item">
            <span class="lp-stats__num">4</span>
            <span class="lp-stats__label">Cấp phân quyền RBAC</span>
        </div>

        <div class="lp-stats__div" aria-hidden="true"></div>

        <div class="lp-stats__item">
            <span class="lp-stats__num">∞</span>
            <span class="lp-stats__label">Workspace &amp; Project</span>
        </div>

        <div class="lp-stats__div" aria-hidden="true"></div>

        <div class="lp-stats__item">
            <span class="lp-stats__num">100%</span>
            <span class="lp-stats__label">Miễn phí</span>
        </div>

    </div>
</section>


<!-- ============================================================
     SECTION 3: FEATURES
     ============================================================ -->
<section class="lp-features" id="features" aria-labelledby="features-title">
    <div class="lp-section__inner">

        <header class="lp-section__header">
            <h2 class="lp-section__title" id="features-title">Mọi thứ nhóm bạn cần</h2>
            <p class="lp-section__sub">
                Từ báo cáo lỗi đến xác minh fix — BugTracker hỗ trợ
                toàn bộ vòng đời phát triển phần mềm.
            </p>
        </header>

        <div class="lp-features__grid">

            <!-- Feature 1 -->
            <article class="lp-feature-card">
                <div class="lp-feature-card__icon lp-feature-card__icon--blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <h3 class="lp-feature-card__title">Issue State Machine</h3>
                <p class="lp-feature-card__desc">
                    8 trạng thái rõ ràng: Open → Triage → In Progress → Resolved → Closed.
                    Chuyển trạng thái có kiểm soát quyền hạn, ghi Activity Log tự động.
                </p>
                <!-- Mini visual: state flow -->
                <div class="lp-feature-card__visual" aria-hidden="true">
                    <span class="lp-mini-badge lp-mini-badge--open">Open</span>
                    <svg viewBox="0 0 16 16" fill="currentColor" class="lp-arrow"><path d="M8.293 3.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L10.586 9H3a1 1 0 010-2h7.586L8.293 4.707a1 1 0 010-1.414z"/></svg>
                    <span class="lp-mini-badge lp-mini-badge--progress">In Progress</span>
                    <svg viewBox="0 0 16 16" fill="currentColor" class="lp-arrow"><path d="M8.293 3.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L10.586 9H3a1 1 0 010-2h7.586L8.293 4.707a1 1 0 010-1.414z"/></svg>
                    <span class="lp-mini-badge lp-mini-badge--resolved">Resolved</span>
                </div>
            </article>

            <!-- Feature 2 -->
            <article class="lp-feature-card">
                <div class="lp-feature-card__icon lp-feature-card__icon--violet">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h3 class="lp-feature-card__title">Phân quyền RBAC</h3>
                <p class="lp-feature-card__desc">
                    4 vai trò: Owner, Admin, Member, Guest. Mỗi người chỉ thấy và
                    làm đúng việc của mình. Middleware kiểm tra quyền từng request.
                </p>
                <!-- Mini visual: role badges -->
                <div class="lp-feature-card__visual" aria-hidden="true">
                    <span class="lp-role-badge lp-role-badge--owner">Owner</span>
                    <span class="lp-role-badge lp-role-badge--admin">Admin</span>
                    <span class="lp-role-badge lp-role-badge--member">Member</span>
                    <span class="lp-role-badge lp-role-badge--guest">Guest</span>
                </div>
            </article>

            <!-- Feature 3 -->
            <article class="lp-feature-card">
                <div class="lp-feature-card__icon lp-feature-card__icon--teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                </div>
                <h3 class="lp-feature-card__title">Comment &amp; @Mention</h3>
                <p class="lp-feature-card__desc">
                    Threaded comments, @mention gửi notification, emoji reactions,
                    chỉnh sửa trong 30 phút. Mọi trao đổi gắn liền với Issue.
                </p>
                <!-- Mini visual: comment thread -->
                <div class="lp-feature-card__visual lp-feature-card__visual--thread" aria-hidden="true">
                    <div class="lp-mini-comment">
                        <div class="lp-mini-comment__avatar lp-mini-comment__avatar--a"></div>
                        <div class="lp-mini-comment__bubble"></div>
                    </div>
                    <div class="lp-mini-comment lp-mini-comment--reply">
                        <div class="lp-mini-comment__avatar lp-mini-comment__avatar--b"></div>
                        <div class="lp-mini-comment__bubble lp-mini-comment__bubble--mention"></div>
                    </div>
                </div>
            </article>

            <!-- Feature 4 -->
            <article class="lp-feature-card">
                <div class="lp-feature-card__icon lp-feature-card__icon--amber">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="lp-feature-card__title">Dashboard &amp; Báo cáo</h3>
                <p class="lp-feature-card__desc">
                    Donut chart trạng thái, bar chart theo Project, activity feed.
                    Dữ liệu PHP → JSON → Chart.js, không cần server background job.
                </p>
                <!-- Mini visual: bar chart CSS -->
                <div class="lp-feature-card__visual" aria-hidden="true">
                    <div class="lp-mini-chart">
                        <div class="lp-mini-bar" style="--h:65%;--c:var(--color-primary-600)"></div>
                        <div class="lp-mini-bar" style="--h:40%;--c:var(--color-warning-500)"></div>
                        <div class="lp-mini-bar" style="--h:80%;--c:var(--color-success-500)"></div>
                        <div class="lp-mini-bar" style="--h:30%;--c:var(--color-info-500)"></div>
                        <div class="lp-mini-bar" style="--h:55%;--c:var(--color-primary-600)"></div>
                    </div>
                </div>
            </article>

            <!-- Feature 5 -->
            <article class="lp-feature-card">
                <div class="lp-feature-card__icon lp-feature-card__icon--rose">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                    </svg>
                </div>
                <h3 class="lp-feature-card__title">File Đính Kèm Bảo Mật</h3>
                <p class="lp-feature-card__desc">
                    Upload screenshot, log, zip tối đa 2MB/file. Lưu ngoài webroot,
                    serve qua PHP controller sau khi kiểm tra quyền workspace.
                </p>
                <!-- Mini visual: file type chips -->
                <div class="lp-feature-card__visual" aria-hidden="true">
                    <span class="lp-file-chip lp-file-chip--img">JPG</span>
                    <span class="lp-file-chip lp-file-chip--img">PNG</span>
                    <span class="lp-file-chip lp-file-chip--pdf">PDF</span>
                    <span class="lp-file-chip lp-file-chip--txt">TXT</span>
                    <span class="lp-file-chip lp-file-chip--zip">ZIP</span>
                </div>
            </article>

            <!-- Feature 6 -->
            <article class="lp-feature-card">
                <div class="lp-feature-card__icon lp-feature-card__icon--slate">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <h3 class="lp-feature-card__title">Tìm Kiếm Toàn Cục</h3>
                <p class="lp-feature-card__desc">
                    Tìm Issue theo ID (BT-001) hoặc từ khóa ngay tại header.
                    Debounce 300ms, kết quả AJAX phân nhóm Issues / Members / Projects.
                </p>
                <!-- Mini visual: search bar -->
                <div class="lp-feature-card__visual" aria-hidden="true">
                    <div class="lp-mini-search">
                        <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M14.707 13.293l-3.14-3.14A5.5 5.5 0 102.5 8a5.5 5.5 0 006.153 5.448l3.14 3.14a1 1 0 001.414-1.414zM4 8a3.5 3.5 0 117 0 3.5 3.5 0 01-7 0z"/></svg>
                        <span class="lp-mini-search__text">BT-042</span>
                        <span class="lp-mini-search__cursor"></span>
                    </div>
                    <div class="lp-mini-result">
                        <span class="lp-mini-badge lp-mini-badge--open">Open</span>
                        Login XSS bug
                    </div>
                </div>
            </article>

        </div>
    </div>
</section>


<!-- ============================================================
     SECTION 4: HOW IT WORKS
     ============================================================ -->
<section class="lp-steps" id="how-it-works" aria-labelledby="steps-title">
    <div class="lp-section__inner">

        <header class="lp-section__header">
            <h2 class="lp-section__title" id="steps-title">Bắt đầu trong 3 bước</h2>
            <p class="lp-section__sub">
                Không cài đặt, không cấu hình phức tạp. Chạy ngay trên trình duyệt.
            </p>
        </header>

        <ol class="lp-steps__list" aria-label="Các bước bắt đầu">

            <li class="lp-step">
                <!-- Icon bước -->
                <div class="lp-step__icon-wrap" aria-hidden="true">
                    <div class="lp-step__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div class="lp-step__connector" aria-hidden="true"></div>
                </div>

                <div class="lp-step__content">
                    <div class="lp-step__num" aria-hidden="true">01</div>
                    <h3 class="lp-step__title">Tạo tài khoản miễn phí</h3>
                    <p class="lp-step__desc">
                        Đăng ký bằng email trong 30 giây. Xác minh email,
                        đặt mật khẩu và bạn đã sẵn sàng.
                    </p>
                    <!-- Mini form mockup -->
                    <div class="lp-step__visual" aria-hidden="true">
                        <div class="lp-mini-form">
                            <div class="lp-mini-form__field">
                                <div class="lp-mini-form__label"></div>
                                <div class="lp-mini-form__input lp-mini-form__input--filled"></div>
                            </div>
                            <div class="lp-mini-form__field">
                                <div class="lp-mini-form__label"></div>
                                <div class="lp-mini-form__input"></div>
                            </div>
                            <div class="lp-mini-form__btn"></div>
                        </div>
                    </div>
                </div>
            </li>

            <li class="lp-step">
                <div class="lp-step__icon-wrap" aria-hidden="true">
                    <div class="lp-step__icon lp-step__icon--violet">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div class="lp-step__connector" aria-hidden="true"></div>
                </div>

                <div class="lp-step__content">
                    <div class="lp-step__num" aria-hidden="true">02</div>
                    <h3 class="lp-step__title">Tạo Workspace và mời nhóm</h3>
                    <p class="lp-step__desc">
                        Tạo Workspace cho tổ chức, tạo Project với key riêng
                        (BT, APP...), mời thành viên qua email với role phù hợp.
                    </p>
                    <!-- Mini workspace visual -->
                    <div class="lp-step__visual" aria-hidden="true">
                        <div class="lp-mini-workspace">
                            <div class="lp-mini-workspace__header">
                                <div class="lp-mini-workspace__avatar"></div>
                                <div class="lp-mini-workspace__name"></div>
                            </div>
                            <div class="lp-mini-workspace__members">
                                <div class="lp-mini-avatar lp-mini-avatar--1"></div>
                                <div class="lp-mini-avatar lp-mini-avatar--2"></div>
                                <div class="lp-mini-avatar lp-mini-avatar--3"></div>
                                <div class="lp-mini-avatar lp-mini-avatar--add">+</div>
                            </div>
                        </div>
                    </div>
                </div>
            </li>

            <li class="lp-step">
                <div class="lp-step__icon-wrap" aria-hidden="true">
                    <div class="lp-step__icon lp-step__icon--green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <!-- Không có connector ở bước cuối -->
                </div>

                <div class="lp-step__content">
                    <div class="lp-step__num" aria-hidden="true">03</div>
                    <h3 class="lp-step__title">Tạo Issue và theo dõi tiến độ</h3>
                    <p class="lp-step__desc">
                        Báo cáo bug, gán developer, theo dõi qua Dashboard.
                        QA xác minh và đóng ticket. Toàn bộ vòng đời trong một nơi.
                    </p>
                    <!-- Mini issue card visual -->
                    <div class="lp-step__visual" aria-hidden="true">
                        <div class="lp-mini-issue-card">
                            <div class="lp-mini-issue-card__top">
                                <span class="lp-mini-badge lp-mini-badge--progress">In Progress</span>
                                <span class="lp-mini-issue-card__id">BT-048</span>
                            </div>
                            <div class="lp-mini-issue-card__title"></div>
                            <div class="lp-mini-issue-card__meta">
                                <div class="lp-mini-avatar lp-mini-avatar--1" style="width:20px;height:20px"></div>
                                <div class="lp-mini-issue-card__bar-wrap">
                                    <div class="lp-mini-issue-card__bar"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </li>

        </ol>
    </div>
</section>


<!-- ============================================================
     SECTION 5: FINAL CTA
     ============================================================ -->
<section class="lp-cta" aria-labelledby="cta-title">
    <div class="lp-cta__glow" aria-hidden="true"></div>
    <div class="lp-cta__inner">
        <h2 class="lp-cta__title" id="cta-title">
            Sẵn sàng kiểm soát bug<br>của nhóm bạn?
        </h2>
        <p class="lp-cta__sub">
            Tạo tài khoản miễn phí trong 30 giây. Không ràng buộc, không thẻ tín dụng.
        </p>
        <div class="lp-cta__actions"
            style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">

            <a href="<?= url('register') ?>"
            class="inline-flex items-center
                    px-7 py-3.5
                    bg-white hover:bg-gray-50
                    text-blue-700 font-semibold text-base
                    rounded-xl
                    shadow-md shadow-black/20
                    transition-all duration-200
                    hover:shadow-lg hover:-translate-y-0.5">
                Đăng ký miễn phí
            </a>

            <a href="<?= url('login') ?>"
            class="inline-flex items-center
                    px-7 py-3.5
                    bg-transparent hover:bg-white/10
                    text-white font-semibold text-base
                    border-2 border-white/70 hover:border-white
                    rounded-xl
                    transition-all duration-200">
                Đã có tài khoản? Đăng nhập
            </a>

</div>
        <!-- Activity indicators -->
        <div class="lp-cta__activity" aria-hidden="true">
            <div class="lp-cta__activity-item">
                <span class="lp-cta__activity-dot"></span>
                BT-049 vừa được tạo
            </div>
            <div class="lp-cta__activity-item">
                <span class="lp-cta__activity-dot lp-cta__activity-dot--green"></span>
                BT-045 đã Resolved
            </div>
            <div class="lp-cta__activity-item">
                <span class="lp-cta__activity-dot lp-cta__activity-dot--yellow"></span>
                BT-046 chuyển In Progress
            </div>
        </div>
    </div>
</section>