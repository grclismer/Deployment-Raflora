<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Email Verification Code</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f6f8fb;margin:0;padding:0;color:#374151;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;margin:40px 0;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.05);border:1px solid #e5e7eb;">
          <tr>
            <td style="padding:28px 32px;text-align:center;background:#581c87;color:#ffffff;">
              <h1 style="margin:0;font-size:24px;font-weight:700;letter-spacing:1px;">RAFLORA ENTERPRISES</h1>
              <p style="margin:6px 0 0;font-size:13px;opacity:0.85;text-transform:uppercase;letter-spacing:1px;">Account Security &amp; Verification</p>
            </td>
          </tr>
          <tr>
            <td style="padding:36px 32px;">
              <p style="font-size:16px;margin:0 0 16px;">Hello <strong>{{ $userName }}</strong>,</p>
              <p style="font-size:15px;line-height:1.5;color:#4b5563;margin:0 0 24px;">
                Thank you for joining Raflora Enterprises. To complete your account registration and verify your email address, please use the 6-digit verification code below:
              </p>
              
              <div style="background:#f3e8ff;border:2px dashed #9333ea;border-radius:8px;padding:20px;text-align:center;margin:28px 0;">
                <span style="font-size:36px;font-weight:800;letter-spacing:8px;color:#581c87;font-family:monospace;">{{ $otp }}</span>
              </div>

              <p style="font-size:14px;color:#6b7280;margin:0 0 8px;">
                <strong>Important:</strong> This verification code is valid for <strong>{{ $expireMinutes }} minutes</strong> and can only be used once.
              </p>
              <p style="font-size:14px;color:#6b7280;margin:0 0 24px;">
                If you did not request this verification code, please ignore this message. No changes will be made to your account.
              </p>

              <div style="border-top:1px solid #f3f4f6;padding-top:20px;font-size:12px;color:#9ca3af;text-align:center;">
                &copy; {{ date('Y') }} Raflora Enterprises. All rights reserved.
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
