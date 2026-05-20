<?php
/**
 * @var array  $active_members       Danh sách thành viên chính thức trong Workspace
 * @var array  $pending_invitations  Danh sách lời mời đang chờ xác nhận
 * @var string $current_user_role    Vai trò của user đang đăng nhập (owner, admin, member, guest)
 * @var int    $current_user_id      ID của user đang đăng nhập (để tránh tự kick chính mình)
 * @var string $csrf_token           Token chống CSRF
 */

// Xác định quyền hạn hiển thị UI
$canManageMembers = in_array($current_user_role, ['owner', 'admin']);
?>

<div class="page-container">
    <div class="page-header">
        <div class="header-title">
            <h1>Quản lý Thành viên</h1>
            <p class="subtitle">Xem danh sách, phân quyền và mời đồng nghiệp tham gia không gian làm việc.</p>
        </div>
        <div class="header-actions">
            <?php if ($canManageMembers): ?>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#inviteMemberModal">
                    <span class="icon">✉️</span> Mời thành viên
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card table-card mb-5">
        <div class="card-header bg-light">
            <h3 class="card-title font-weight-bold">Thành viên chính thức (<?= count($active_members) ?>)</h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Thành viên</th>
                    <th style="width: 150px;">Vai trò</th>
                    <th>Ngày tham gia</th>
                    <th class="text-right" style="width: 120px;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_members as $member): ?>
                    <tr>
                        <td>
                            <div class="user-info-cell d-flex align-items-center">
                                <img src="<?= Sanitizer::escape($member['avatar_path'] ?? '/assets/img/avatar-default.svg') ?>" class="avatar-md mr-3 border" alt="Avatar">
                                <div>
                                    <strong class="text-dark d-block"><?= Sanitizer::escape($member['name']) ?></strong>
                                    <span class="text-muted text-small"><?= Sanitizer::escape($member['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php 
                                // Màu sắc hiển thị theo từng Role
                                $roleClass = 'badge-light';
                                if ($member['role'] === 'owner') $roleClass = 'badge-danger bg-dark';
                                elseif ($member['role'] === 'admin') $roleClass = 'badge-danger';
                                elseif ($member['role'] === 'member') $roleClass = 'badge-primary';
                            ?>
                            <span class="badge <?= $roleClass ?> text-uppercase"><?= Sanitizer::escape($member['role']) ?></span>
                        </td>
                        <td><span class="text-small text-muted"><?= Sanitizer::escape($member['joined_at']) ?></span></td>
                        <td class="text-right">
                            <?php 
                            // Logic hiển thị hành động:
                            [cite_start]// 1. Chỉ Owner/Admin mới thấy nút [cite: 75, 76]
                            [cite_start]// 2. Không được thao tác lên Owner [cite: 67]
                            // 3. Không tự thao tác lên chính mình
                            if ($canManageMembers && $member['role'] !== 'owner' && $member['user_id'] !== $current_user_id): 
                            ?>
                                <div class="dropdown-actions">
                                    <form action="/workspace/members/change-role/<?= Sanitizer::escape($member['id']) ?>" method="POST" class="d-inline-block">
                                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                                        <select name="new_role" class="form-control form-control-sm d-inline-block w-auto mr-1" onchange="this.form.submit()">
                                            <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?> <?= $current_user_role !== 'owner' ? 'disabled' : '' ?>>Admin</option>
                                            <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                            <option value="guest" <?= $member['role'] === 'guest' ? 'selected' : '' ?>>Guest</option>
                                        </select>
                                    </form>

                                    <form action="/workspace/members/kick/<?= Sanitizer::escape($member['id']) ?>" method="POST" class="d-inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa thành viên này khỏi Workspace?');">
                                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger-outline btn-icon-only" title="Kick khỏi Workspace">❌</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="text-muted text-small">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($canManageMembers): ?>
        <div class="card table-card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold">Lời mời chờ xác nhận (<?= count($pending_invitations) ?>)</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Email được mời</th>
                        <th>Vai trò cấp sẵn</th>
                        <th>Ngày hết hạn</th>
                        <th class="text-right">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_invitations)): ?>
                        <tr>
                            <td colspan="4" class="text-center empty-state text-muted py-4">Không có lời mời nào đang chờ xử lý.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_invitations as $invite): ?>
                            <tr>
                                <td>
                                    <strong class="text-dark"><?= Sanitizer::escape($invite['email']) ?></strong>
                                    <?php if ($invite['is_pre_registered']): ?>
                                        <span class="badge badge-light-warning ml-2 text-small">Chưa có tài khoản</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-light text-uppercase"><?= Sanitizer::escape($invite['role']) ?></span></td>
                                <td>
                                    <span class="text-small <?= strtotime($invite['expires_at']) < time() ? 'text-danger' : 'text-muted' ?>">
                                        <?= Sanitizer::escape($invite['expires_at']) ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <form action="/workspace/invitations/resend/<?= Sanitizer::escape($invite['id']) ?>" method="POST" class="d-inline-block mr-1">
                                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary-outline">Gửi lại</button>
                                    </form>
                                    <form action="/workspace/invitations/revoke/<?= Sanitizer::escape($invite['id']) ?>" method="POST" class="d-inline-block" onsubmit="return confirm('Hủy lời mời này? Người nhận sẽ không thể sử dụng liên kết nữa.');">
                                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger-outline btn-icon-only">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="modal" id="inviteMemberModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="/workspace/invitations/store" method="POST" id="inviteForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                        
                        <div class="modal-header">
                            <h3 class="modal-title">Gửi lời mời tham gia</h3>
                            <button type="button" class="close-modal" data-dismiss="modal" aria-label="Close">×</button>
                        </div>
                        
                        <div class="modal-body">
                            <div class="alert alert-info text-small mb-4">
                                Nhập địa chỉ email của đồng nghiệp. [cite_start]Hệ thống sẽ tự động gửi email hướng dẫn kèm liên kết bảo mật có thời hạn 7 ngày[cite: 292].
                            </div>
                            
                            <div class="form-group">
                                <label for="invite_email">Địa chỉ Email người nhận <span class="text-danger">*</span></label>
                                <input type="email" name="email" id="invite_email" class="form-control" placeholder="name@company.com" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="invite_role">Chỉ định Vai trò <span class="text-danger">*</span></label>
                                <select name="role" id="invite_role" class="form-control" required>
                                    <?php if ($current_user_role === 'owner'): ?>
                                        <option value="admin">Admin (Quản trị viên)</option>
                                    <?php endif; ?>
                                    <option value="member" selected>Member (Nhà phát triển / QA Tester)</option>
                                    <option value="guest">Guest (Khách / Người báo cáo)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="modal-footer d-flex justify-content-end">
                            <button type="button" class="btn btn-link mr-2" data-dismiss="modal">Hủy bỏ</button>
                            <button type="submit" class="btn btn-primary">Gửi lời mời</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>