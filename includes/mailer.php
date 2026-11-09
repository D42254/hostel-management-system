<?php
// includes/mailer.php
define('MAIL_REAL', true);
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'yourgmail@gmail.com');
define('MAIL_PASS', 'your-app-password'); // Gmail app password, not your login
define('MAIL_FROM', 'yourgmail@gmail.com');

/**
 * Send an email. In dev mode (default) writes to logs/mail.log.
 * If PHPMailer is installed (composer require phpmailer/phpmailer)
 * and MAIL_REAL is true, sends via SMTP.
 */
function send_mail(string $to, string $subject, string $body): bool {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

    $entry  = "[" . date('c') . "] TO: $to | SUBJ: $subject\n";
    $entry .= $body . "\n" . str_repeat('-', 70) . "\n";
    @file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND);

    // real send (optional)
    if (defined('MAIL_REAL') && MAIL_REAL === true && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = MAIL_PORT;
            $mail->setFrom(MAIL_FROM, 'Hostel System');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mail error: ' . $e->getMessage());
            return false;
        }
    }
    return true; // logged only
}