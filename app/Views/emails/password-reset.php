<?php
/**
 * @var string $user_name      Tên người dùng
 * @var string $reset_url      Đường link đặt lại mật khẩu chứa token
 * @var int $expires_hours     Thời gian hết hạn (1 giờ)
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; line-height: 1.6; color: #333333; }
        .container { max-width: 600px; background-color: #ffffff; padding: 30px; border-radius: 8px; margin: 0 auto; border: 1px solid #e1e4e8; }
        .header { border-bottom: 2px solid #e67e22; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #e67e22; margin: 0; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #e67e22; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #7f8c8d; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Yêu cầu đặt lại mật khẩu</h2>
        </div>
        <p>Chào <strong><?= Sanitizer::escape($user_name) ?></strong>,</p>
        <p>Hệ thống BugTracker vừa nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Vui lòng nhấn vào nút bên dưới để tạo mật khẩu mới:</p>
        
        <div style="text-align: center;">
            <a href="<?= Sanitizer::escape($reset_url) ?>" class="btn">Đặt lại Mật khẩu</a>
        </div>
        
        <p style="font-size: 14px; color: #e74c3c;"><em>Lưu ý: Để đảm bảo bảo mật, liên kết này chỉ có hiệu lực trong vòng <?= Sanitizer::escape($expires_hours) ?> giờ.</em></p>
        <p>Nếu bạn không yêu cầu thay đổi mật khẩu, vui lòng bỏ qua email này. Tài khoản của bạn vẫn được an toàn.</p>
        
        <div class="footer">
            &copy; <?= date('Y') ?> BugTracker.
        </div>
    </div>
</body>
</html>