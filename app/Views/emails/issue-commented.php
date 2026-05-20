<?php
/**
 * @var string $commenter_name   Tên người vừa bình luận 
 * @var string $issue_id         Mã Issue (VD: BT-001) 
 * @var string $comment_preview  Trích đoạn nội dung bình luận (cắt ngắn để tránh email quá dài) 
 * @var string $issue_url        Đường link trực tiếp đến trang chi tiết Issue 
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; line-height: 1.6; color: #333333; }
        .container { max-width: 600px; background-color: #ffffff; padding: 30px; border-radius: 8px; margin: 0 auto; border: 1px solid #e1e4e8; }
        .header { border-bottom: 2px solid #16a085; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #16a085; margin: 0; font-size: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #16a085; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 15px 0; }
        .comment-box { background-color: #f9f9f9; padding: 15px; border-radius: 4px; border: 1px solid #e0e0e0; margin: 15px 0; font-style: italic; color: #555; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #7f8c8d; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Bình luận mới trên Issue [<?= Sanitizer::escape($issue_id) ?>]</h2>
        </div>
        <p>Xin chào,</p>
        <p>Thành viên <strong><?= Sanitizer::escape($commenter_name) ?></strong> vừa thêm một bình luận mới vào hạng mục công việc mà bạn đang tham gia hoặc theo dõi.</p>
        
        <div class="comment-box">
            "<?= Sanitizer::escape($comment_preview) ?>..."
        </div>

        <div style="text-align: center;">
            <a href="<?= Sanitizer::escape($issue_url) ?>" class="btn">Xem toàn bộ trao đổi</a>
        </div>
        
        [cite_start]<p style="font-size: 13px; color: #7f8c8d; margin-top: 20px;">Bạn nhận được email này do cài đặt nhận thông báo khi có bình luận mới[cite: 367]. Bạn có thể thay đổi thiết lập này trong trang hồ sơ cá nhân.</p>
        
        <div class="footer">
            &copy; <?= date('Y') ?> BugTracker Notification System.
        </div>
    </div>
</body>
</html>