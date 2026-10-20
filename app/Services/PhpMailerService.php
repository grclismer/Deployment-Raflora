<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PhpMailerService
{
    protected PHPMailer $mailer;

    public function __construct(?PHPMailer $mailer = null)
    {
        if ($mailer !== null) {
            $this->mailer = $mailer;
            return;
        }

        $this->mailer = new PHPMailer(true);

        // SMTP settings from config repository
        $this->mailer->isSMTP();
        $this->mailer->Host = config('mail.mailers.smtp.host', '127.0.0.1');
        $this->mailer->Port = (int) config('mail.mailers.smtp.port', 1025);
        $username = config('mail.mailers.smtp.username');
        $this->mailer->SMTPAuth = ! empty($username);
        $this->mailer->Username = $username;
        $this->mailer->Password = config('mail.mailers.smtp.password_reset', config('mail.mailers.smtp.password'));
        
        $encryption = config('mail.mailers.smtp.encryption', config('mail.mailers.smtp.scheme'));
        if ($encryption && in_array(strtolower((string) $encryption), ['ssl','tls'])) {
            $this->mailer->SMTPSecure = strtolower((string) $encryption);
        }

        $fromAddress = config('mail.from.address', 'no-reply@example.com');
        $fromName = config('mail.from.name', config('app.name', 'App'));
        $this->mailer->setFrom($fromAddress, $fromName);
        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';
    }

    /**
     * Send a password reset email with a nice HTML template.
     *
     * This method is used by the custom AuthController reset flow so that the
     * app can send a Gmail/PHPMailer email with a branded button and expiry note.
     *
     * @param string $toEmail
     * @param string|null $toName
     * @param string $resetUrl
     * @return bool
     */
    public function sendPasswordReset(string $toEmail, ?string $toName, string $resetUrl): bool
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($toEmail, $toName ?? '');

            $subject = config('app.name', 'Application') . ' — Account Recovery';
            $this->mailer->Subject = $subject;

            $html = $this->buildResetHtml($toName ?? $toEmail, $resetUrl);

            $this->mailer->Body = $html;
            $this->mailer->AltBody = "Reset your password: $resetUrl";

            return $this->mailer->send();
        } catch (\Throwable $e) {
            \Log::error('PHPMailer error: ' . $this->sanitizeErrorMessage($e->getMessage()));
            return false;
        }
    }

    protected function buildResetHtml(string $name, string $resetUrl): string
    {
        $appName = config('app.name', 'Raflora Enterprises');
        $buttonColor = '#7e22ce';
        $expireMinutes = config('auth.passwords.users.expire', 60);
        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Account Recovery - $appName</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f6f8fb;margin:0;padding:0;color:#374151;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;margin:40px 0;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.05);border:1px solid #e5e7eb;">
          <tr>
            <td style="padding:28px 32px;text-align:center;background:#581c87;color:#ffffff;">
              <h1 style="margin:0;font-size:24px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">$appName</h1>
              <p style="margin:6px 0 0;font-size:13px;opacity:0.85;text-transform:uppercase;letter-spacing:1px;">Account Recovery</p>
            </td>
          </tr>
          <tr>
            <td style="padding:36px 32px;color:#374151;">
              <p style="font-size:16px;margin:0 0 16px;">Hello <strong>$name</strong>,</p>
              <p style="font-size:15px;line-height:1.5;color:#4b5563;margin:0 0 24px;">Your account recovery request was received. Click the button below to set a new password.</p>
              <p style="text-align:center;margin:28px 0;">
                <a href="$resetUrl" style="background:$buttonColor;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;display:inline-block;font-weight:700;letter-spacing:0.5px;">Recover My Account</a>
              </p>
              <p style="margin-top:6px;color:#6b7280;font-size:14px;"><strong>Important:</strong> This link is valid for <strong>$expireMinutes minutes</strong> and must be used before it expires.</p>
              <p style="font-size:14px;color:#6b7280;margin:16px 0 8px;">If the button doesn't work, copy and paste the link below into your browser:</p>
              <p style="word-break:break-all;color:#6b7280;font-size:13px;background:#f9fafb;padding:12px;border-radius:6px;">$resetUrl</p>
              <p style="margin-top:24px;color:#9ca3af;font-size:13px;">If you didn't request this, you can safely ignore this email.</p>
              <div style="border-top:1px solid #f3f4f6;margin-top:32px;padding-top:20px;font-size:12px;color:#9ca3af;text-align:center;">
                &copy; 2026 $appName. All rights reserved.
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }


    /**
     * Send an email verification OTP code.
     */
    public function sendVerificationOtp(string $toEmail, ?string $toName, string $otp, int $expireMinutes = 10): bool
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($toEmail, $toName ?? '');

            $subject = config('app.name', 'Raflora Enterprises') . ' — Email Verification Code';
            $this->mailer->Subject = $subject;

            $html = $this->buildOtpHtml($toName ?? $toEmail, $otp, $expireMinutes);

            $this->mailer->Body = $html;
            $this->mailer->AltBody = "Your verification code is: $otp. It will expire in $expireMinutes minutes.";

            return $this->mailer->send();
        } catch (\Throwable $e) {
            \Log::error('PHPMailer error sending verification OTP: ' . $this->sanitizeErrorMessage($e->getMessage(), $otp));
            return false;
        }
    }

    /**
     * Sanitize error message to prevent leaking OTPs, credentials, or sensitive data.
     */
    protected function sanitizeErrorMessage(string $message, ?string $otp = null): string
    {
        if ($otp !== null && $otp !== '') {
            $message = str_replace($otp, '[REDACTED_OTP]', $message);
        }

        $message = preg_replace('/\b\d{6}\b/', '[REDACTED_CODE]', $message);

        $passwords = array_filter([
            config('mail.mailers.smtp.password_reset'),
            config('mail.mailers.smtp.password'),
        ]);

        foreach ($passwords as $smtpPass) {
            if (! empty($smtpPass)) {
                $message = str_replace($smtpPass, '[REDACTED_PASSWORD]', $message);
            }
        }

        return $message;
    }

    /**
     * Set the underlying PHPMailer instance.
     */
    public function setMailer(PHPMailer $mailer): self
    {
        $this->mailer = $mailer;
        return $this;
    }

    /**
     * Get the underlying PHPMailer instance.
     */
    public function getMailer(): PHPMailer
    {
        return $this->mailer;
    }

    protected function buildOtpHtml(string $name, string $otp, int $expireMinutes): string
    {
        $appName = config('app.name', 'Raflora Enterprises');
        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Email Verification Code</title>
</head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f6f8fb;margin:0;padding:0;color:#374151;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;margin:40px 0;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
          <tr>
            <td style="padding:28px 32px;text-align:center;background:#581c87;color:#ffffff;">
              <h1 style="margin:0;font-size:24px;font-weight:700;">$appName</h1>
              <p style="margin:6px 0 0;font-size:13px;opacity:0.85;text-transform:uppercase;">Account Security &amp; Verification</p>
            </td>
          </tr>
          <tr>
            <td style="padding:36px 32px;">
              <p style="font-size:16px;margin:0 0 16px;">Hello <strong>$name</strong>,</p>
              <p style="font-size:15px;line-height:1.5;color:#4b5563;margin:0 0 24px;">
                Please use the following 6-digit verification code to verify your email address:
              </p>
              <div style="background:#f3e8ff;border:2px dashed #9333ea;border-radius:8px;padding:20px;text-align:center;margin:28px 0;">
                <span style="font-size:36px;font-weight:800;letter-spacing:8px;color:#581c87;font-family:monospace;">$otp</span>
              </div>
              <p style="font-size:14px;color:#6b7280;margin:0 0 8px;">
                <strong>Important:</strong> This verification code is valid for <strong>$expireMinutes minutes</strong> and can only be used once.
              </p>
              <p style="font-size:14px;color:#6b7280;margin:0 0 24px;">
                If you did not request this verification code, please ignore this email.
              </p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px;text-align:center;background:#f3f4f6;color:#9ca3af;font-size:12px;">$appName</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }
}
