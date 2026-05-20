<?php
/**
 * @var string $csrf_token        Token bảo mật
 * @var array  $workspace         Dữ liệu hiện tại của Workspace
 * @var string $current_user_role Vai trò của người đang xem (owner, admin)
 * @var array  $admins            Danh sách các Admin (dành cho tính năng chuyển quyền của Owner)
 * @var array  $errors            Mảng thông báo lỗi form
 */

$isOwner = $current_user_role === 'owner';
?>

<div class="page-container max-w-4xl mx-auto">
    <div class="page-header mb-4">
        <div class="header-title">
            <h1>Cài đặt Workspace</h1>
            <p class="subtitle">Quản lý thông tin chung và các thiết lập cốt lõi của không gian làm việc.</p>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light font-weight-bold border-bottom">
            Thông tin chung
        </div>
        <div class="card-body p-4">
            <form action="/workspace/settings/update" method="POST">
                <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">

                <div class="form-group mb-4">
                    <label for="name" class="font-weight-bold">Tên Workspace <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" 
                           value="<?= Sanitizer::escape($workspace['name']) ?>" required>
                    <?php if (!empty($errors['name'])): ?>
                        <div class="invalid-feedback"><?= Sanitizer::escape($errors['name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group mb-4">
                    <label for="description" class="font-weight-bold">Mô tả</label>
                    <textarea name="description" id="description" class="form-control" rows="3"><?= Sanitizer::escape($workspace['description'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary px-4">Lưu cấu hình</button>
            </form>
        </div>
    </div>

    <?php if ($isOwner): ?>
        <div class="card border-danger shadow-sm mt-5 danger-zone-card">
            <div class="card-header bg-danger text-white font-weight-bold">
                ⚠️ Vùng nguy hiểm (Danger Zone)
            </div>
            <div class="card-body p-4">
                
                <div class="border-bottom pb-4 mb-4">
                    <h5 class="text-danger">Chuyển giao quyền sở hữu (Transfer Ownership)</h5>
                    <p class="text-muted text-small">
                        Khi chuyển giao thành công, bạn sẽ tự động bị giáng xuống quyền Admin và không thể hoàn tác hành động này. Bạn chỉ có thể chuyển quyền cho những thành viên đang là Admin.
                    </p>
                    
                    <form action="/workspace/transfer-ownership" method="POST" class="form-inline mt-3" onsubmit="return confirm('CẢNH BÁO: Bạn sắp mất quyền Owner. Bạn có chắc chắn muốn thực hiện?');">
                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                        
                        <select name="new_owner_id" class="form-control mr-2" required style="min-width: 250px;">
                            <option value="">-- Chọn Admin kế nhiệm --</option>
                            <?php foreach ($admins as $admin): ?>
                                <option value="<?= Sanitizer::escape($admin['user_id']) ?>">
                                    <?= Sanitizer::escape($admin['name']) ?> (<?= Sanitizer::escape($admin['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-danger-outline" <?= empty($admins) ? 'disabled' : '' ?>>
                            Chuyển giao
                        </button>
                    </form>
                    <?php if (empty($admins)): ?>
                        <div class="text-small text-warning mt-2">Workspace hiện chưa có Admin nào. Vui lòng cấp quyền Admin cho một thành viên trước khi chuyển giao.</div>
                    <?php endif; ?>
                </div>

                <div>
                    <h5 class="text-danger">Xóa Không gian làm việc</h5>
                    <p class="text-muted text-small">
                        Hành động này sẽ đánh dấu xóa (Soft delete) Workspace và giải phóng toàn bộ tệp đính kèm vật lý (tiết kiệm Inode). Bạn không thể tự phục hồi.
                    </p>
                    
                    <form action="/workspace/delete" method="POST" onsubmit="return confirm('CẢNH BÁO TỐI CAO: Toàn bộ dữ liệu Dự án, Issue, Thành viên thuộc Workspace này sẽ không thể truy cập được nữa. Bạn chắc chắn chứ?');">
                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                        <button type="submit" class="btn btn-danger">🗑️ Xóa Workspace vĩnh viễn</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>