<?php
/**
 * @var string $actor_name     Tên người đã thay đổi trạng thái
 * @var string $issue_id       Mã Issue (VD: BT-001)
 * @var string $old_status     Trạng thái cũ
 * @var string $new_status     Trạng thái mới
 * @var string $issue_url      Đường link trực tiếp đến trang chi tiết Issue
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; line-height: 1.6; color: #333333; }
        .container { max-width: 600px; background-color: #ffffff; padding: 30px; border-radius: 8px; margin: 0 auto; border: 1px solid #e1e4e8; }
        .header { border-bottom: 2px solid #8e44ad; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #8e44ad; margin: 0; font-size: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #8e44ad; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 15px 0; }
        .status-box { background-color: #f8f9fa; border-radius: 4px; padding: 15px; margin: 15px 0; text-align: center; }
        .status-badge { padding: 4px 8px; border-radius: 3px; font-size: 14px; font-weight: bold; background-color: #e0e0e0; color: #333; }
        .arrow { margin: 0 10px; font-size: 18px; color: #7f8c8d; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #7f8c8d; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Cập nhật trạng thái Issue [<?= Sanitizer::escape($issue_id) ?>]</h2>
        </div>
        <p>Xin chào,</p>
        <p>Thành viên <strong><?= Sanitizer::escape($actor_name) ?></strong> vừa cập nhật trạng thái cho Issue mà bạn đang theo dõi.</p>
        
        <div class="status-box">
            <span class="status-badge"><?= Sanitizer::escape(strtoupper(str_replace('_', ' ', $old_status))) ?></span>
            <span class="arrow">➔</span>
            <span class="status-badge" style="background-color: #8e44ad; color: white;"><?= Sanitizer::escape(strtoupper(str_replace('_', ' ', $new_status))) ?></span>
        </div>

        <div style="text-align: center;">
            <a href="<?= Sanitizer::escape($issue_url) ?>" class="btn">Xem chi tiết Issue</a>
        </div>
        
        <p style="font-size: 13px; color: #7f8c8d; margin-top: 20px;">Bạn nhận được email này vì bạn là Reporter, Assignee, hoặc đã bật theo dõi (Watch) Issue này. Bạn có thể tắt thông báo trong mục Cài đặt tài khoản (Profile Settings).</p>
        
        <div class="footer">
            &copy; <?= date('Y') ?> BugTracker Notification System.
        </div>
    </div>
</body>
</html>