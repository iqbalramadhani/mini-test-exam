<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        try {
            $this->mail->isSMTP();
            $this->mail->Host       = SMTP_HOST;
            $this->mail->SMTPAuth   = !empty(SMTP_USER);
            $this->mail->Username   = SMTP_USER;
            $this->mail->Password   = SMTP_PASS;
            // Gunakan TLS jika port 587, SSL jika 465. Karena port bisa beda-beda (cth 2525 Mailtrap), kita set berdasarkan port
            if (SMTP_PORT == 587) {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (SMTP_PORT == 465) {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mail->SMTPAutoTLS = false;
            }
            $this->mail->Port       = SMTP_PORT;

            $this->mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mail->isHTML(true);
        } catch (Exception $e) {
            if (class_exists('AppErrorHandler')) {
                AppErrorHandler::getLogger()->error("Mailer init failed: " . $e->getMessage());
            }
        }
    }

    public function sendConfirmationEmail($toEmail, $toName, $token)
    {
        try {
            $this->mail->addAddress($toEmail, $toName);

            // Gunakan FRONTEND_URL dari .env jika di-set secara eksplisit.
            // Jika tidak, otomatis mendeteksi URL saat ini (berguna untuk production).
            $baseUrl = getenv('FRONTEND_URL');
            if (!$baseUrl) {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $subFolder = defined('URL_SUB_FOLDER') ? URL_SUB_FOLDER : '';
                $baseUrl = rtrim($protocol . $host . $subFolder, '/');
            }
            $verifyLink = $baseUrl . '/verify-email?token=' . urlencode($token);

            $this->mail->Subject = 'Konfirmasi Pendaftaran Akun';
            $this->mail->Body    = "
                <h3>Halo, {$toName}!</h3>
                <p>Terima kasih telah mendaftar. Silakan klik tautan di bawah ini untuk mengaktifkan akun Anda:</p>
                <p><a href=\"{$verifyLink}\" style=\"padding:10px 15px; background:#4f46e5; color:#ffffff; text-decoration:none; border-radius:5px;\">Verifikasi Email Saya</a></p>
                <p>Atau copy paste link berikut di browser Anda: <br> <a href=\"{$verifyLink}\">{$verifyLink}</a></p>
                <p>Link ini akan kadaluarsa dalam 24 jam.</p>
            ";
            $this->mail->AltBody = "Halo {$toName}!\n\nSilakan kunjungi link berikut untuk verifikasi email Anda:\n{$verifyLink}\n\nTerima kasih.";

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            if (class_exists('AppErrorHandler')) {
                AppErrorHandler::getLogger()->error("Email sending failed to {$toEmail}. Error: {$this->mail->ErrorInfo}");
            }
            return false;
        }
    }
}
