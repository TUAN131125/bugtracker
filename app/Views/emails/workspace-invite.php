<?php
/**
 * @var string $inviter_name   Tên người gửi lời mời
 * @var string $workspace_name Tên Workspace
 * @var string $role           Vai trò được phân công (Admin/Member/Guest)
 * @var string $invite_url     Đường link tham gia chứa token
 * @var int $expires_days      Thời gian hết hạn (7 ngày)
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; line-height: 1.6; color: #333333; }
        .container { max-width: 600px; background-color: #ffffff; padding: 30px; border-radius: 8px; margin: 0 auto; border: 1px solid #e1e4e8; }
        .header { border-bottom: 2px solid #27ae60; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #27ae60; margin: 0; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #27ae60; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 20px 0; }
        .highlight { background-color: #f8f9fa; padding: 10px; border-radius: 4px; border-left: 4px solid #27ae60; margin: 15px 0; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #7f8c8d; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Lời mời tham gia không gian làm việc</h2>
        </div>
        <p>Xin chào,</p>
        <p>Bạn vừa nhận được lời mời tham gia làm việc trên BugTracker từ <strong><?= Sanitizer::escape($inviter_name) ?></strong>.</p>
        
        <div class="highlight">
            <p style="margin: 5px 0;"><strong>Workspace:</strong> <?= Sanitizer::escape($workspace_name) ?></p>
            <p style="margin: 5px 0;"><strong>Vai trò của bạn:</strong> <span style="text-transform: capitalize;"><?= Sanitizer::escape($role) ?></span></p>
        </div>

        <p>Để chấp nhận lời mời và bắt đầu làm việc, vui lòng nhấn vào nút bên dưới:</p>
        
        <div style="text-align: center;">
            <a href="<?= Sanitizer::escape($invite_url) ?>" class="btn">Tham gia Workspace</a>
        </div>
        
        <p style="font-size: 14px; color: #e74c3c;"><em>Lưu ý: Lời mời này sẽ hết hạn sau <?= Sanitizer::escape($expires_days) ?> ngày.</em></p>
        <p>Nếu bạn chưa có tài khoản BugTracker, hệ thống sẽ hướng dẫn bạn tạo tài khoản nhanh chóng sau khi bấm vào liên kết.</p>
        
        <div class="footer">
            &copy; <?= date('Y') ?> BugTracker. Email này được gửi tự động từ hệ thống.
        </div>
    </div>
</body>
</html>