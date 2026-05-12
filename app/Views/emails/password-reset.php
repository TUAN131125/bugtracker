<?php
// Biến: $user_name, $reset_url, $expires_hours
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>Đặt lại mật khẩu – BugTracker</title></head>
<body style="margin:0;padding:0;background-color:#F8FAFC;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#F8FAFC;padding:40px 0;">
  <tr>
    <td align="center">
      <table role="presentation" width="520" cellpadding="0" cellspacing="0"
             style="background-color:#FFFFFF;border-radius:8px;border:1px solid #E2E8F0;overflow:hidden;">
        <tr>
          <td style="background-color:#DC2626;padding:28px 40px;text-align:center;">
            <p style="margin:0;color:#FFFFFF;font-size:22px;font-weight:700;">🐛 BugTracker</p>
          </td>
        </tr>
        <tr>
          <td style="padding:40px;">
            <p style="margin:0 0 16px;font-size:16px;color:#0F172A;">
              Xin chào <strong><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></strong>,
            </p>
            <p style="margin:0 0 8px;font-size:15px;color:#334155;line-height:1.7;">
              Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.
              Nhấn nút bên dưới để tiếp tục:
            </p>
            <!-- Cảnh báo bảo mật -->
            <div style="margin:0 0 24px;padding:12px 16px;background-color:#FEF2F2;
                        border-left:4px solid #EF4444;border-radius:0 4px 4px 0;">
              <p style="margin:0;font-size:13px;color:#991B1B;">
                ⚠ Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.
                Tài khoản của bạn vẫn an toàn.
              </p>
            </div>
            <table role="presentation" cellpadding="0" cellspacing="0"
                   style="margin:0 auto 28px;">
              <tr>
                <td style="background-color:#DC2626;border-radius:6px;">
                  <a href="<?= htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') ?>"
                     style="display:inline-block;padding:14px 32px;color:#FFFFFF;
                            font-size:15px;font-weight:600;text-decoration:none;">
                    Đặt lại mật khẩu
                  </a>
                </td>
              </tr>
            </table>
            <p style="margin:0;font-size:13px;color:#94A3B8;">
              ⏱ Link chỉ có hiệu lực <strong><?= (int) $expires_hours ?> giờ</strong>
              và <strong>chỉ dùng được một lần</strong>.
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