<?php
/**
 * @var string $csrf_token      Token bảo mật
 * @var array  $project         Mảng dữ liệu dự án (sẽ rỗng nếu là Tạo mới)
 * @var array  $members         Danh sách các thành viên trong Workspace để gán vào Project
 * @var array  $project_members Danh sách ID của các thành viên đã có trong Project (dùng khi Edit)
 * @var array  $errors          Mảng lưu lỗi kiểm tra từ phía Server
 */
$isEdit = !empty($project['id']);
?>

<div class="page-container max-w-3xl mx-auto">
    <div class="page-header mb-4">
        <div class="header-title">
            <h1><?= $isEdit ? 'Chỉnh sửa Dự án: ' . Sanitizer::escape($project['name']) : 'Khởi tạo Dự án mới' ?></h1>
            <p class="subtitle">
                <?= $isEdit ? 'Cập nhật thông tin và danh sách thành viên tham gia.' : 'Dự án đại diện cho một sản phẩm hoặc một module lớn cần theo dõi lỗi.' ?>
            </p>
        </div>
        <div class="header-actions">
            <button onclick="history.back()" class="btn btn-link text-muted">Hủy bỏ</button>
        </div>
    </div>

    <div class="card p-4 shadow-sm">
        <form action="<?= $isEdit ? '/projects/edit/' . Sanitizer::escape($project['id']) : '/projects/store' ?>" method="POST" id="projectForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">

            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="name">Tên Dự án <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" 
                           placeholder="Ví dụ: Ứng dụng Di động iOS" value="<?= Sanitizer::escape($project['name'] ?? '') ?>" required>
                    <?php if (!empty($errors['name'])): ?>
                        <div class="invalid-feedback"><?= Sanitizer::escape($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group col-md-4">
                    <label for="key">Mã Dự án (Key) <span class="text-danger">*</span></label>
                    <input type="text" name="key" id="key" class="form-control text-uppercase font-weight-bold <?= !empty($errors['key']) ? 'is-invalid' : '' ?>" 
                           placeholder="VD: IOS" value="<?= Sanitizer::escape($project['key'] ?? '') ?>" 
                           pattern="[A-Z]{2,6}" title="Mã dự án phải từ 2 đến 6 ký tự chữ cái in hoa" 
                           <?= $isEdit ? 'readonly' : 'required' ?>>
                    <?php if (!$isEdit): ?>
                        <small class="form-text text-muted">Dùng làm tiền tố ID (VD: IOS-001). Không thể đổi sau khi tạo.</small>
                    <?php endif; ?>
                    <?php if (!empty($errors['key'])): ?>
                        <div class="invalid-feedback"><?= Sanitizer::escape($errors['key']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Mô tả chi tiết</label>
                <textarea name="description" id="description" class="form-control" rows="4" placeholder="Mô tả mục tiêu của dự án này..."><?= Sanitizer::escape($project['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="color">Màu đại diện</label>
                    <div class="d-flex align-items-center">
                        <input type="color" name="color" id="color" class="form-control p-1 mr-2" style="width: 50px; height: 38px;" 
                               value="<?= Sanitizer::escape($project['color'] ?? '#2E86AB') ?>">
                        <span class="text-small text-muted">Giúp dễ nhận diện trong danh sách Issue.</span>
                    </div>
                </div>

                <div class="form-group col-md-6">
                    <label for="status">Trạng thái hoạt động</label>
                    <select name="status" id="status" class="form-control">
                        <option value="active" <?= ($project['status'] ?? '') === 'active' ? 'selected' : '' ?>>Đang hoạt động (Active)</option>
                        <option value="archived" <?= ($project['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Đã lưu trữ (Archived)</option>
                    </select>
                </div>
            </div>

            <hr class="mt-4 mb-4">
            
            <div class="form-group">
                <label class="font-weight-bold d-block mb-2">Thành viên tham gia <span class="text-danger">*</span></label>
                <div class="text-small text-muted mb-3">Chỉ những thành viên được chọn mới có thể xem và xử lý các lỗi thuộc dự án này.</div>
                
                <div class="member-selection-grid bg-light p-3 border rounded" style="max-height: 250px; overflow-y: auto;">
                    <?php if (empty($members)): ?>
                        <div class="text-muted text-center text-small">Không có thành viên nào trong Workspace để thêm.</div>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <?php 
                            // Kiểm tra xem member này đã được check chưa
                            $isChecked = in_array($member['user_id'], $project_members ?? []);
                            // Nếu đang tạo mới, mặc định check chính người đang tạo
                            if (!$isEdit && $member['user_id'] == $_SESSION['user_id']) {
                                $isChecked = true;
                            }
                            ?>
                            <div class="custom-control custom-checkbox mb-2 p-2 border-bottom">
                                <input type="checkbox" class="custom-control-input" name="members[]" id="member_<?= Sanitizer::escape($member['user_id']) ?>" value="<?= Sanitizer::escape($member['user_id']) ?>" <?= $isChecked ? 'checked' : '' ?>>
                                <label class="custom-control-label d-flex align-items-center cursor-pointer w-100" for="member_<?= Sanitizer::escape($member['user_id']) ?>">
                                    <img src="<?= Sanitizer::escape($member['avatar_path'] ?? '/assets/img/avatar-default.svg') ?>" class="avatar-sm mr-2 border rounded-circle" alt="">
                                    <div>
                                        <span class="d-block font-weight-bold text-dark"><?= Sanitizer::escape($member['name']) ?></span>
                                        <span class="d-block text-small text-muted text-uppercase"><?= Sanitizer::escape($member['role']) ?></span>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if (!empty($errors['members'])): ?>
                    <div class="text-danger mt-1 text-small"><?= Sanitizer::escape($errors['members']) ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group mt-4 text-right">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <?= $isEdit ? 'Lưu thay đổi' : 'Hoàn tất khởi tạo' ?>
                </button>
            </div>
        </form>
    </div>
</div>