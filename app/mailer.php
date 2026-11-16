<?php

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function password_reset_mail_configured(): bool
{
    $mail = cfg()['mail'];
    $smtpHost = strtolower(trim($mail['host']));
    $localSmtp = in_array($smtpHost, ['localhost', '127.0.0.1', '::1'], true);
    $hasUsername = trim($mail['username']) !== '';
    $hasPassword = trim($mail['password']) !== '';
    $smtpAuthenticationReady = $hasUsername && $hasPassword
        || ($localSmtp && $mail['secure'] === 'none' && !$hasUsername && !$hasPassword);
    $secureTransport = in_array($mail['secure'], ['tls', 'ssl'], true)
        || ($mail['secure'] === 'none' && $localSmtp);

    return class_exists(PHPMailer::class)
        && ($mail['secure'] === 'none' && $localSmtp || extension_loaded('openssl'))
        && $smtpHost !== ''
        && $mail['port'] >= 1
        && $mail['port'] <= 65535
        && $secureTransport
        && $smtpAuthenticationReady
        && filter_var($mail['from_email'], FILTER_VALIDATE_EMAIL) !== false
        && trim($mail['from_name']) !== '';
}

function send_password_reset_otp(string $recipient, string $otp): void
{
    $mailConfig = cfg()['mail'];
    $mailer = new PHPMailer(true);

    try {
        $mailer->isSMTP();
        $mailer->Host = $mailConfig['host'];
        $mailer->Port = $mailConfig['port'];
        $mailer->SMTPAuth = trim($mailConfig['username']) !== '';
        $mailer->Username = $mailConfig['username'];
        $mailer->Password = $mailConfig['password'];
        $mailer->SMTPSecure = match ($mailConfig['secure']) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $mailer->SMTPAutoTLS = $mailConfig['secure'] === 'tls';
        $mailer->SMTPDebug = 0;
        $mailer->Timeout = 15;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mailer->addAddress($recipient);
        $mailer->isHTML(true);
        $mailer->Subject = 'Reset Password Akun Se.Noesa';
        $mailer->Body = password_reset_otp_email_html($otp);
        $mailer->AltBody = "Se.Noesa Catering\n\nKami menerima permintaan reset password untuk akun Anda.\n"
            . "Kode OTP Anda: {$otp}\nKode berlaku selama 10 menit dan hanya dapat digunakan sekali.\n\n"
            . "Jika Anda tidak meminta reset password, abaikan email ini.";
        $mailer->send();
    } catch (MailException $error) {
        throw new RuntimeException('Email reset tidak dapat dikirim.', 0, $error);
    }
}

function password_reset_otp_email_html(string $otp): string
{
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return <<<HTML
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Reset Password Se.Noesa</title>
</head>
<body style="margin:0;padding:24px 12px;background:#eef2ec;color:#243128;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:20px;overflow:hidden;">
    <tr>
      <td style="padding:30px 28px;background:#7a8f6f;color:#ffffff;text-align:center;">
        <p style="margin:0;font-size:13px;letter-spacing:2px;text-transform:uppercase;">Se.Noesa Catering</p>
        <h1 style="margin:12px 0 0;font-size:27px;line-height:1.25;">Reset Password Akun</h1>
      </td>
    </tr>
    <tr>
      <td style="padding:30px 28px;font-size:16px;line-height:1.65;">
        <p style="margin:0 0 16px;">Kami menerima permintaan untuk mengatur ulang password akun Se.Noesa Anda.</p>
        <p style="margin:0 0 24px;">Masukkan kode berikut pada halaman Forgot Password untuk membuat password baru. Kode ini hanya dapat digunakan sekali dan berlaku selama <strong>10 menit</strong>.</p>
        <p style="margin:0 0 26px;text-align:center;">
          <span style="display:inline-block;padding:14px 26px;border:2px solid #7a8f6f;border-radius:14px;background:#f6f8f4;color:#2d3a33;font-size:30px;font-weight:bold;letter-spacing:8px;">{$safeOtp}</span>
        </p>
        <p style="margin:0 0 16px;font-size:13px;color:#58635a;">Jangan bagikan kode ini kepada siapa pun.</p>
        <p style="margin:0;font-size:13px;color:#58635a;">Jika Anda tidak merasa meminta reset password, abaikan email ini. Password Anda tidak akan berubah.</p>
      </td>
    </tr>
    <tr>
      <td style="padding:18px 28px;background:#f6f8f4;text-align:center;color:#718071;font-size:12px;">Se.Noesa Catering · Email otomatis, mohon tidak membalas.</td>
    </tr>
  </table>
</body>
</html>
HTML;
}
