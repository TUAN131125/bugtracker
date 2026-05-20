<?php
/**
 * @var string|null $error_reference_id Mã ID tham chiếu log được sinh ra từ Exception Handler
 */
?>
<div class="error-page-container text-center">
    <div class="error-content">
        <h1 class="error-code text-danger">500</h1>
        <h2 class="error-title">Lỗi hệ thống máy chủ</h2>
        <p class="error-description text-muted">
            Đã xảy ra sự cố kỹ thuật không mong muốn trong quá trình xử lý yêu cầu của bạn. 
            Luồng xử lý đã bị tạm dừng để bảo vệ an toàn dữ liệu.
        </p>
        
        <?php if (!empty($error_reference_id)): ?>
            <div class="error-reference mt-3 p-3 bg-light rounded border">
                <span class="text-small text-muted">Mã tham chiếu lỗi (Vui lòng cung cấp mã này cho Admin):</span>
                <br>
                <code class="font-weight-bold text-dark fs-5"><?= Sanitizer::escape($error_reference_id) ?></code>
            </div>
        <?php endif; ?>
        
        <div class="error-actions mt-4">
            <button onclick="window.location.reload()" class="btn btn-secondary">
                <span class="icon">🔄</span> Tải lại trang
            </button>
            <a href="/dashboard" class="btn btn-primary">
                <span class="icon">🏠</span> Về Bảng điều khiển
            </a>
        </div>
    </div>
</div>