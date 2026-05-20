<?php
/**
 * @var string $assignee_name  Tên người được gán (người nhận email)
 * @var string $issue_id       Mã Issue (VD: BT-001)
 * @var string $issue_title    Tiêu đề Issue
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
        .header { border-bottom: 2px solid #2980b9; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #2980b9; margin: 0; font-size: 20px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #2980b9; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 20px 0; }
        .highlight { background-color: #f0f7fa; padding: 15px; border-radius: 4px; border-left: 4px solid #2980b9; margin: 15px 0; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #7f8c8d; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Bạn vừa được phân công một Issue mới</h2>
        </div>
        <p>Chào <strong><?= Sanitizer::escape($assignee_name) ?></strong>,</p>
        <p>Quản trị viên hoặc đồng nghiệp vừa chỉ định bạn là người phụ trách (Assignee) để xử lý một hạng mục công việc trên hệ thống BugTracker.</p>
        
        <div class="highlight">
            <p style="margin: 5px 0; font-size: 18px;"><strong>[<?= Sanitizer::escape($issue_id) ?>]</strong> <?= Sanitizer::escape($issue_title) ?></p>
        </div>

        <p>Vui lòng kiểm tra chi tiết lỗi, các tệp đính kèm (nếu có) và cập nhật trạng thái sang "In Progress" khi bạn bắt đầu tiến hành công việc.</p>
        
        <div style="text-align: center;">
            <a href="<?= Sanitizer::escape($issue_url) ?>" class="btn">Xem chi tiết và Xử lý</a>
        </div>
        
        <div class="footer">
            &copy; <?= date('Y') ?> BugTracker Notification System.
        </div>
    </div>
</body>
</html>