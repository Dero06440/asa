<?php
// Charge PHPMailer (inclus dans lib/)
require_once __DIR__ . '/../lib/phpmailer/Exception.php';
require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envoie le code OTP par email.
 * Retourne true si succes, false sinon.
 */
function sendOTPEmail(string $toEmail, string $toName, string $code): bool {
    if (defined('SMTP_ENABLED') && SMTP_ENABLED === false) {
        error_log(sprintf('OTP local pour %s : %s', $toEmail, $code));
        return true;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Votre code de connexion ASA Paillon';
        $mail->Body = emailOTPTemplate($code, $toName);
        $mail->AltBody = "Bonjour $toName,\n\nVotre code de connexion ASA Paillon est : $code\n\nCe code est valable " . OTP_VALIDITY_MINUTES . " minutes.\n\nSi vous n'avez pas demande ce code, ignorez cet email.";

        $mail->send();
        return true;
    } catch (MailerException $e) {
        error_log('Erreur envoi email OTP : ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Template HTML de l'email OTP
 */
function emailOTPTemplate(string $code, string $nom): string {
    $validity = OTP_VALIDITY_MINUTES;
    $appName = APP_NAME;
    return <<<HTML
    <!DOCTYPE html>
    <html lang="fr">
    <head><meta charset="UTF-8"></head>
    <body style="font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px;">
      <div style="max-width: 500px; margin: 0 auto; background: #fff; border-radius: 8px;
                  box-shadow: 0 2px 8px rgba(0,0,0,.1); overflow: hidden;">
        <div style="background: #2c6e49; padding: 24px; text-align: center;">
          <h1 style="color: #fff; margin: 0; font-size: 22px;">{$appName}</h1>
        </div>
        <div style="padding: 32px;">
          <p style="margin: 0 0 16px; color: #333;">Bonjour <strong>{$nom}</strong>,</p>
          <p style="margin: 0 0 24px; color: #555;">
            Voici votre code de connexion :
          </p>
          <div style="text-align: center; margin: 0 0 24px;">
            <span style="display: inline-block; font-size: 36px; font-weight: bold;
                         letter-spacing: 8px; color: #2c6e49; background: #e8f5e9;
                         padding: 16px 24px; border-radius: 8px; border: 2px solid #2c6e49;">
              {$code}
            </span>
          </div>
          <p style="margin: 0 0 8px; color: #888; font-size: 13px; text-align: center;">
            Ce code est valable <strong>{$validity} minutes</strong>.
          </p>
          <p style="margin: 0; color: #aaa; font-size: 12px; text-align: center;">
            Si vous n'avez pas demande ce code, ignorez cet email.
          </p>
        </div>
      </div>
    </body>
    </html>
    HTML;
}
