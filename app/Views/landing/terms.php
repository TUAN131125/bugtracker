<?php
/**
 * landing/terms.php
 * Trang Điều khoản sử dụng.
 */
$layout = 'landing';
?>
<div class="landing-terms">
    <div class="landing-terms__container">

        <header class="landing-terms__header">
            <h1 class="landing-terms__title">Điều khoản sử dụng</h1>
            <p class="landing-terms__date">
                Cập nhật lần cuối: <?= date('d/m/Y') ?>
            </p>
        </header>

        <article class="landing-terms__content prose">

            <h2>1. Chấp nhận điều khoản</h2>
            <p>
                Bằng cách truy cập và sử dụng BugTracker, bạn đồng ý tuân thủ
                các điều khoản và điều kiện được nêu trong tài liệu này.
            </p>

            <h2>2. Mô tả dịch vụ</h2>
            <p>
                BugTracker là hệ thống quản lý lỗi (Bug Tracking System) dành cho
                các nhóm phát triển phần mềm. Dịch vụ được cung cấp miễn phí.
            </p>

            <h2>3. Tài khoản người dùng</h2>
            <p>
                Bạn chịu trách nhiệm bảo mật thông tin đăng nhập của mình.
                Mỗi tài khoản chỉ được sử dụng bởi một người duy nhất.
                Không chia sẻ mật khẩu với người khác.
            </p>

            <h2>4. Dữ liệu và quyền riêng tư</h2>
            <p>
                Dữ liệu bạn nhập vào hệ thống (Issue, Comment, File đính kèm)
                thuộc về bạn. Chúng tôi không chia sẻ dữ liệu của bạn
                với bên thứ ba vì mục đích thương mại.
            </p>

            <h2>5. Sử dụng hợp lệ</h2>
            <p>
                Bạn đồng ý không sử dụng BugTracker để lưu trữ nội dung
                bất hợp pháp, spam, hoặc gây hại cho người dùng khác.
                Chúng tôi có quyền tạm ngừng tài khoản vi phạm.
            </p>

            <h2>6. Giới hạn trách nhiệm</h2>
            <p>
                BugTracker được cung cấp "nguyên trạng" (as-is).
                Chúng tôi không đảm bảo dịch vụ hoạt động liên tục 100%
                và không chịu trách nhiệm về mất mát dữ liệu do sự cố kỹ thuật.
            </p>

            <h2>7. Thay đổi điều khoản</h2>
            <p>
                Chúng tôi có thể cập nhật điều khoản này theo thời gian.
                Việc tiếp tục sử dụng dịch vụ sau khi cập nhật đồng nghĩa
                với việc bạn chấp nhận điều khoản mới.
            </p>

        </article>

        <div class="landing-terms__back">
            <a href="<?= url('/') ?>" class="btn btn--ghost">
                ← Quay về trang chủ
            </a>
        </div>

    </div>
</div>