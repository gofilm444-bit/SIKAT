<?php
/**
 * mailer.php
 * Util: kirim email via PHPMailer (jika tersedia) atau fallback mail()
 */
require_once __DIR__.'/config_mail.php';

function mailer_send(array $to, string $subject, string $htmlBody, string $textAlt = ''): bool {
    // normalisasi penerima
    $recipients = [];
    foreach ($to as $e) {
        if (is_array($e) && !empty($e['email'])) {
            $recipients[] = [$e['email'], $e['nama'] ?? ''];
        } elseif (is_string($e)) {
            $recipients[] = [$e, ''];
        }
    }
    if (empty($recipients)) return false;

    $subject = MAIL_SUBJECT_PREFIX . $subject;

    // === Coba PHPMailer (jika tersedia) ===
    $phpmailer_ok = false;
    try {
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            $phpmailer_ok = true;
        } else {
            // coba autoload composer
            $autoload = __DIR__.'/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
                if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) { $phpmailer_ok = true; }
            }
        }
    } catch (\Throwable $e) { $phpmailer_ok = false; }

    if ($phpmailer_ok) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            if (MAIL_MODE === 'smtp') {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->Port = SMTP_PORT;
                if (SMTP_SECURE) $mail->SMTPSecure = SMTP_SECURE;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

            foreach ($recipients as [$em,$nm]) { $mail->addAddress($em, $nm); }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textAlt ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            // jatuh ke fallback
        }
    }

    // === Fallback: mail() bawaan ===
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: '.MAIL_FROM_NAME.' <'.MAIL_FROM.'>';
    $hdr = implode("\r\n", $headers);

    // kirim terpisah untuk tiap penerima
    $okAll = true;
    foreach ($recipients as [$em, $nm]) {
        $ok = @mail($em, $subject, $htmlBody, $hdr);
        if (!$ok) $okAll = false;
    }
    return $okAll;
}

/**
 * Helper ambil daftar admin aktif dari DB
 */
function mailer_admin_list(mysqli $conn): array {
    $res = $conn->query("SELECT email, nama FROM mail_recipients WHERE aktif=1");
    $list = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $list[] = ['email'=>$r['email'], 'nama'=>$r['nama'] ?? ''];
        }
    }
    return $list;
}
