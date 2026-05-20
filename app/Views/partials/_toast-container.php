<?php
/**
 * Container này hiển thị các thông báo Flash Session được set từ Controller
 * Ví dụ: $_SESSION['flash_success'] = 'Cập nhật thành công!'
 */
?>

<div aria-live="polite" aria-atomic="true" class="toast-container position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
    
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="toast toast-success show fade" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <strong class="mr-auto">✅ Thành công</strong>
                <button type="button" class="ml-2 mb-1 close text-white" onclick="this.parentElement.parentElement.remove()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="toast-body bg-white text-dark">
                <?= Sanitizer::escape($_SESSION['flash_success']) ?>
            </div>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="toast toast-danger show fade" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-danger text-white">
                <strong class="mr-auto">❌ Lỗi</strong>
                <button type="button" class="ml-2 mb-1 close text-white" onclick="this.parentElement.parentElement.remove()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="toast-body bg-white text-dark">
                <?= Sanitizer::escape($_SESSION['flash_error']) ?>
            </div>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    </div>