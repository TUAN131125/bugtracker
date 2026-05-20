<?php
/**
 * @var string $csrf_token Token bảo mật chống CSRF
 * @var array  $errors     Mảng lưu lỗi kiểm tra phía Server
 * @var array  $old        Dữ liệu cũ khi form nhập lỗi
 */
?>

<div class="page-container max-w-2xl mx-auto mt-5">
    <div class="page-header mb-4 text-center">
        <h1 class="header-title">Tạo Không gian làm việc mới</h1>
        <p class="subtitle mt-2">
            Workspace là nơi chứa các Dự án, Issue và Thành viên của bạn. 
            Mỗi Workspace hoạt động hoàn toàn độc lập với nhau.
        </p>
    </div>

    <div class="card p-4 shadow-sm border-0">
        <form action="/workspace/store" method="POST" id="createWorkspaceForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">

            <div class="form-group mb-4">
                <label for="name" class="font-weight-bold">Tên Workspace <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name" class="form-control form-control-lg <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" 
                       placeholder="Ví dụ: Công ty TNHH ABC, Team Alpha..." value="<?= Sanitizer::escape($old['name'] ?? '') ?>" required autofocus>
                <?php if (!empty($errors['name'])): ?>
                    <div class="invalid-feedback"><?= Sanitizer::escape($errors['name']) ?></div>
                <?php endif; ?>
                <small class="form-text text-muted mt-2">Tên này sẽ hiển thị ở menu điều hướng và trong các email thông báo.</small>
            </div>

            <div class="form-group mb-4">
                <label for="description" class="font-weight-bold">Mô tả ngắn (Tùy chọn)</label>
                <textarea name="description" id="description" class="form-control" rows="3" placeholder="Không gian làm việc cho dự án Outsourcing..."><?= Sanitizer::escape($old['description'] ?? '') ?></textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-5">
                <a href="javascript:history.back()" class="btn btn-link text-muted text-decoration-none">← Quay lại</a>
                <button type="submit" class="btn btn-primary btn-lg px-4">
                    Khởi tạo Workspace
                </button>
            </div>
        </form>
    </div>
</div>