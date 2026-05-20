<?php
/**
 * @var array $current_user Thông tin người dùng đang đăng nhập
 * @var int $unread_notifs  Số lượng thông báo chưa đọc [cite: 265]
 * @var string $csrf_token  Token bảo mật cho thao tác đăng xuất [cite: 180]
 */
use App\Helpers\Sanitizer;
?>

<div class="header-inner d-flex justify-content-between align-items-center w-100 px-4 py-2 bg-white border-bottom shadow-sm">
    
    <div class="header-search position-relative w-50">
        <div class="input-group">
            <span class="input-group-prepend text-muted border-0 bg-light px-3 py-2 rounded-left">🔍</span>
            <input type="text" id="globalSearchInput" class="form-control border-0 bg-light rounded-right" placeholder="Tìm kiếm Issue (BT-012), Dự án, Thành viên... (Nhấn '/' để tìm)" autocomplete="off">
        </div>
        <div id="globalSearchResults" class="search-results-dropdown position-absolute w-100 bg-white border shadow-sm rounded mt-1 d-none z-index-dropdown"></div>
    </div>

    <div class="header-tools d-flex align-items-center">
        
        <div class="notification-wrapper position-relative mr-4 dropdown">
            <button class="btn btn-icon-only text-muted dropdown-toggle" id="notificationDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Thông báo">
                🔔
                <span class="badge badge-danger badge-pill position-absolute notification-badge <?= $unread_notifs > 0 ? '' : 'd-none' ?>" id="notificationCount">
                    <?= Sanitizer::escape($unread_notifs) ?>
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-right shadow-sm" style="width: 300px; max-height: 400px; overflow-y: auto;" aria-labelledby="notificationDropdown" id="notificationList">
                <div class="dropdown-header font-weight-bold border-bottom">Thông báo mới</div>
                <div class="text-center text-muted p-3 text-small" id="notificationEmptyState">Đang tải...</div>
            </div>
        </div>

        <div class="user-profile-wrapper dropdown">
            <button class="btn btn-link dropdown-toggle text-decoration-none d-flex align-items-center p-0" id="userProfileDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <img src="<?= Sanitizer::escape($current_user['avatar_path'] ?? '/assets/img/avatar-default.svg') ?>" class="avatar-sm rounded-circle border mr-2" alt="Avatar">
                <span class="text-dark font-weight-bold mr-1"><?= Sanitizer::escape($current_user['name']) ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-right shadow-sm mt-2" aria-labelledby="userProfileDropdown">
                <div class="px-3 py-2 border-bottom">
                    <p class="mb-0 text-dark font-weight-bold"><?= Sanitizer::escape($current_user['name']) ?></p>
                    <p class="mb-0 text-muted text-small text-truncate"><?= Sanitizer::escape($current_user['email']) ?></p>
                </div>
                <a class="dropdown-item mt-2" href="/profile">👤 Cài đặt cá nhân</a>
                <div class="dropdown-divider"></div>
                <form action="/logout" method="POST" class="m-0 p-0">
                    <input type="hidden" name="csrf_token" value="<?= Sanitizer::escape($csrf_token) ?>">
                    <button type="submit" class="dropdown-item text-danger">🚪 Đăng xuất</button>
                </form>
            </div>
        </div>
    </div>
</div>