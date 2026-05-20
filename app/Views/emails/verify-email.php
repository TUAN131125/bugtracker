<?php
/**
 * @var string $user_name      Tên người dùng
 * @var string $verify_url     Đường link chứa token xác minh
 * @var int $expires_hours     Thời gian hết hạn (24 giờ)
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; line-height: 1.6; color: #333333; }
        .container { max-width: 600px; background-color: #ffffff; padding: 30px; border-radius: 8px; margin: 0 auto; border: 1px solid #e1e4e8; }
        .header { border-bottom: 2px solid #2E86AB; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #2E86AB; margin: 0; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #2E86AB; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 20px 0; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #7f8c8d; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Xác minh địa chỉ Email</h2>
        </div>
        <p>Chào <strong><?= Sanitizer::escape($user_name) ?></strong>,</p>
        <p>Cảm ơn bạn đã đăng ký tài khoản tại hệ thống BugTracker. Để hoàn tất quá trình đăng ký và kích hoạt tài khoản, vui lòng nhấn vào nút bên dưới:</p>
        
        <div style="text-align: center;">
            <a href="<?= Sanitizer::escape($verify_url) ?>" class="btn">Xác minh Email ngay</a>
        </div>
        
        <p style="font-size: 14px; color: #e74c3c;"><em>Lưu ý: Liên kết này chỉ có hiệu lực trong vòng <?= Sanitizer::escape($expires_hours) ?> giờ.</em></p>
        <p>Nếu nút bấm không hoạt động, bạn có thể copy và dán đường dẫn sau vào trình duyệt:<br>
            <a href="<?= Sanitizer::escape($verify_url) ?>" style="color: #2E86AB; word-break: break-all;"><?= Sanitizer::escape($verify_url) ?></a>
        </p>
        <p>Nếu bạn không thực hiện đăng ký này, vui lòng bỏ qua email.</p>
        
        <div class="footer">
            &copy; <?= date('Y') ?> BugTracker. Hệ thống quản lý lỗi làm việc nhóm.
        </div>
    </div>
</body>
</html>