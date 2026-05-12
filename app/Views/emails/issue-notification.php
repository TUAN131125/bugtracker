<?php
// Biến: $actor_name, $issue_key, $issue_title,
//       $issue_url, $notification_type, $old_status (optional), $new_status (optional)
$type_messages = [
    'assigned'       => "đã gán Issue cho bạn",
    'status_changed' => "đã đổi trạng thái Issue",
    'commented'      => "đã bình luận vào Issue",
    'mentioned'      => "đã nhắc đến bạn trong Issue",
];
$message = $type_messages[$notification_type ?? 'assigned'] ?? "đã cập nhật Issue";
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>Thông báo Issue – BugTracker</title></head>
<body style="margin:0;padding:0;background-color:#F8FAFC;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#F8FAFC;padding:40px 0;">
  <tr>
    <td align="center">
      <table role="presentation" width="520" cellpadding="0" cellspacing="0"
             style="background-color:#FFFFFF;border-radius:8px;border:1px solid #E2E8F0;overflow:hidden;">
        <tr>
          <td style="background-color:#2563EB;padding:28px 40px;text-align:center;">
            <p style="margin:0;color:#FFFFFF;font-size:22px;font-weight:700;">🐛 BugTracker</p>
          </td>
        </tr>
        <tr>
          <td style="padding:40px;">
            <p style="margin:0 0 20px;font-size:15px;color:#334155;line-height:1.7;">
              <strong><?= htmlspecialchars($actor_name, ENT_QUOTES, 'UTF-8') ?></strong>
              <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>:
            </p>
            <!-- Issue Card -->
            <div style="padding:16px 20px;background-color:#F1F5F9;
                        border-radius:6px;border-left:4px solid #2563EB;margin-bottom:28px;">
              <p style="margin:0 0 6px;font-size:12px;color:#64748B;
                        font-family:Courier New,monospace;font-weight:700;">
                <?= htmlspecialchars($issue_key, ENT_QUOTES, 'UTF-8') ?>
              </p>
              <p style="margin:0;font-size:15px;color:#0F172A;font-weight:600;line-height:1.5;">
                <?= htmlspecialchars($issue_title, ENT_QUOTES, 'UTF-8') ?>
              </p>
              <?php if (!empty($old_status) && !empty($new_status)): ?>
                <p style="margin:8px 0 0;font-size:13px;color:#64748B;">
                  Trạng thái:
                  <span style="text-decoration:line-through;">
                    <?= htmlspecialchars($old_status, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  →
                  <strong style="color:#0F172A;">
                    <?= htmlspecialchars($new_status, ENT_QUOTES, 'UTF-8') ?>
                  </strong>
                </p>
              <?php endif; ?>
            </div>
            <table role="presentation" cellpadding="0" cellspacing="0">
              <tr>
                <td style="background-color:#2563EB;border-radius:6px;">
                  <a href="<?= htmlspecialchars($issue_url, ENT_QUOTES, 'UTF-8') ?>"
                     style="display:inline-block;padding:12px 28px;color:#FFFFFF;
                            font-size:14px;font-weight:600;text-decoration:none;">
                    Xem Issue
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 40px;background-color:#F8FAFC;
                     border-top:1px solid #E2E8F0;text-align:center;">
            <p style="margin:0;font-size:12px;color:#94A3B8;">
              © <?= date('Y') ?> BugTracker –
              <a href="<?= htmlspecialchars(APP_URL ?? '#', ENT_QUOTES, 'UTF-8') ?>/profile/notification-settings"
                 style="color:#94A3B8;">Quản lý thông báo</a>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>