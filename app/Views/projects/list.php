<?php
/**
 * @var array  $projects            Danh sách các dự án trong Workspace
 * @var string $current_user_role   Vai trò người dùng (owner, admin, member)
 * @var string $csrf_token          Token bảo mật
 */

// Chỉ Owner và Admin mới có quyền tạo/sửa Project
$canManageProjects = in_array($current_user_role, ['owner', 'admin']);
?>

<div class="page-container">
    <div class="page-header">
        <div class="header-title">
            <h1>Quản lý Dự án</h1>
            <p class="subtitle">Tổ chức và theo dõi các phân hệ sản phẩm trong không gian làm việc.</p>
        </div>
        <div class="header-actions">
            <?php if ($canManageProjects): ?>
                <a href="/projects/create" class="btn btn-primary">
                    <span class="icon">+</span> Tạo Dự án mới
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card table-card mt-4">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Key</th>
                    <th>Tên Dự án</th>
                    <th>Trạng thái</th>
                    <th>Mô tả</th>
                    <th style="width: 150px;">Ngày tạo</th>
                    <?php if ($canManageProjects): ?>
                        <th class="text-right" style="width: 120px;">Hành động</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="<?= $canManageProjects ? '6' : '5' ?>" class="text-center empty-state py-5">
                            <div class="text-muted">Chưa có dự án nào được tạo trong Workspace này.</div>
                            <?php if ($canManageProjects): ?>
                                <a href="/projects/create" class="btn btn-sm btn-outline-primary mt-3">Khởi tạo ngay</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td>
                                <span class="badge" style="background-color: <?= Sanitizer::escape($project['color'] ?? '#2E86AB') ?>; color: #fff;">
                                    <?= Sanitizer::escape($project['key']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="/issues?project_id=<?= Sanitizer::escape($project['id']) ?>" class="font-weight-bold text-dark text-decoration-none">
                                    <?= Sanitizer::escape($project['name']) ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($project['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="text-truncate d-inline-block text-muted text-small" style="max-width: 250px;" title="<?= Sanitizer::escape($project['description'] ?? '') ?>">
                                    <?= Sanitizer::escape($project['description'] ?? 'Không có mô tả') ?>
                                </span>
                            </td>
                            <td><span class="text-small text-muted"><?= Sanitizer::escape($project['created_at']) ?></span></td>
                            
                            <?php if ($canManageProjects): ?>
                                <td class="text-right">
                                    <div class="dropdown-actions">
                                        <a href="/projects/edit/<?= Sanitizer::escape($project['id']) ?>" class="btn btn-sm btn-icon-only text-primary" title="Chỉnh sửa">✏️</a>
                                        
                                        <form action="/projects/toggle-status/<?= Sanitizer::escape($project['id']) ?>" method="POST" class="d-inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn thay đổi trạng thái dự án này? Các Issue bên trong sẽ bị khóa nếu chuyển sang Archive.');">
                                            <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                                            <button type="submit" class="btn btn-sm btn-icon-only <?= $project['status'] === 'active' ? 'text-warning' : 'text-success' ?>" title="<?= $project['status'] === 'active' ? 'Lưu trữ (Archive)' : 'Kích hoạt lại' ?>">
                                                <?= $project['status'] === 'active' ? '📦' : '♻️' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>