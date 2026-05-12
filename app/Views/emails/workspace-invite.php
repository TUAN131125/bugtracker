<?php
// Biến: $inviter_name, $workspace_name, $role, $invite_url, $expires_days
$role_labels = [
    'admin'  => 'Quản trị viên',
    'member' => 'Thành viên',
    'guest'  => 'Khách/Reporter',
];
$role_display = $role_labels[$role ?? 'member'] ?? 'Thành viên';
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>Lời mời tham gia – BugTracker</title></head>
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
            <p style="margin:0 0 16px;font-size:16px;color:#0F172A;line-height:1.6;">
              <strong><?= htmlspecialchars($inviter_name, ENT_QUOTES, 'UTF-8') ?></strong>
              đã mời bạn tham gia Workspace
              <strong>"<?= htmlspecialchars($workspace_name, ENT_QUOTES, 'UTF-8') ?>"</strong>
              với vai trò <strong><?= htmlspecialchars($role_display, ENT_QUOTES, 'UTF-8') ?></strong>.
            </p>
            <p style="margin:0 0 28px;font-size:15px;color:#334155;line-height:1.7;">
              Nhấn nút bên dưới để chấp nhận lời mời và bắt đầu cộng tác.
            </p>
            <table role="presentation" cellpadding="0" cellspacing="0"
                   style="margin:0 auto 28px;">
              <tr>
                <td style="background-color:#16A34A;border-radius:6px;">
                  <a href="<?= htmlspecialchars($invite_url, ENT_QUOTES, 'UTF-8') ?>"
                     style="display:inline-block;padding:14px 32px;color:#FFFFFF;
                            font-size:15px;font-weight:600;text-decoration:none;">
                    Chấp nhận lời mời
                  </a>
                </td>
              </tr>
            </table>
            <p style="margin:0;font-size:13px;color:#94A3B8;">
              ⏱ Lời mời có hiệu lực trong <strong><?= (int) $expires_days ?> ngày</strong>.
              Nếu bạn không biết về lời mời này, hãy bỏ qua email này.
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 40px;background-color:#F8FAFC;
                     border-top:1px solid #E2E8F0;text-align:center;">
            <p style="margin:0;font-size:12px;color:#94A3B8;">
              © <?= date('Y') ?> BugTracker
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>