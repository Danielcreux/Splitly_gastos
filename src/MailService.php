<?php
declare(strict_types=1);

final class MailService
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/mail.php';
    }

    public function sendPasswordReset(string $recipient, string $resetUrl): bool
    {
        if ($this->config['transport'] !== 'mail') {
            error_log("[Splitly mail preview] {$recipient}: {$resetUrl}");
            return false;
        }

        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject = 'Restablece tu contraseña de Splitly';
        $message = "<!doctype html><html><body style=\"font-family:Arial,sans-serif;color:#17211e\">"
            . '<h2>Restablece tu contraseña</h2>'
            . '<p>Este enlace es válido durante una hora y solo puede utilizarse una vez.</p>'
            . "<p><a href=\"{$safeUrl}\" style=\"background:#075a45;color:#fff;padding:12px 18px;text-decoration:none;border-radius:7px\">Crear nueva contraseña</a></p>"
            . '<p>Si no solicitaste este cambio, ignora este correo.</p></body></html>';
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $this->config['from_name'], $this->config['from_address']),
        ];
        return mail($recipient, $subject, $message, implode("\r\n", $headers));
    }
}
