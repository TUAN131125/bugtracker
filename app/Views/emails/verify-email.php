<?php
// Biến nhận từ EmailService::renderTemplate():
// $user_name    (string) – Tên người dùng
// $verify_url   (string) – Link xác minh có chứa token
// $expires_hours (int)   – Thời hạn token (mặc định 24h)
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác minh địa chỉ email – BugTracker</title>
</head>
<body style="margin:0;padding:0;background-color:#F8FAFC;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#F8FAFC;padding:40px 0;">
  <tr>
    <td align="center">
      <table role="presentation" width="520" cellpadding="0" cellspacing="0"
             style="background-color:#FFFFFF;border-radius:8px;
                    border:1px solid #E2E8F0;overflow:hidden;">

        <!-- Header -->
        <tr>
          <td style="background-color:#2563EB;padding:28px 40px;text-align:center;">
            <p style="margin:0;color:#FFFFFF;font-size:22px;font-weight:700;
                      letter-spacing:-0.3px;">🐛 BugTracker</p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:40px;">
            <p style="margin:0 0 16px;font-size:16px;color:#0F172A;line-height:1.6;">
              Xin chào <strong><?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') ?></strong>,
            </p>
            <p style="margin:0 0 24px;font-size:15px;color:#334155;line-height:1.7;">
              Cảm ơn bạn đã đăng ký tài khoản BugTracker. Vui lòng nhấn nút bên dưới
              để xác minh địa chỉ email và kích hoạt tài khoản của bạn.
            </p>

            <!-- CTA Button -->
            <table role="presentation" cellpadding="0" cellspacing="0"
                   style="margin:0 auto 28px;">
              <tr>
                <td style="background-color:#2563EB;border-radius:6px;">
                  <a href="<?= htmlspecialchars($verify_url, ENT_QUOTES, 'UTF-8') ?>"
                     style="display:inline-block;padding:14px 32px;
                            color:#FFFFFF;font-size:15px;font-weight:600;
                            text-decoration:none;letter-spacing:0.2px;">
                    Xác minh địa chỉ email
                  </a>
                </td>
              </tr>
            </table>

            <p style="margin:0 0 12px;font-size:13px;color:#64748B;line-height:1.6;">
              Nếu nút không hoạt động, hãy sao chép và dán đường dẫn sau vào trình duyệt:
            </p>
            <p style="margin:0 0 24px;padding:12px;background:#F1F5F9;
                      border-radius:4px;font-size:12px;color:#334155;
                      word-break:break-all;font-family:Courier New,monospace;">
              <?= htmlspecialchars($verify_url, ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p style="margin:0;font-size:13px;color:#94A3B8;line-height:1.6;">
              ⏱ Link có hiệu lực trong <strong><?= (int) $expires_hours ?> giờ</strong>.
              Nếu bạn không đăng ký tài khoản này, hãy bỏ qua email này.
            </p>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:20px 40px;background-color:#F8FAFC;
                     border-top:1px solid #E2E8F0;text-align:center;">
            <p style="margin:0;font-size:12px;color:#94A3B8;">
              © <?= date('Y') ?> BugTracker – Email này được gửi tự động, vui lòng không trả lời.
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>