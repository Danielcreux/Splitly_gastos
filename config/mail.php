<?php
declare(strict_types=1);

$app = require __DIR__ . '/app.php';
$transport = getenv('MAIL_TRANSPORT') ?: 'log';
if ($app['production'] && $app['password_reset_enabled'] && $transport === 'log') {
    throw new RuntimeException('La recuperación de contraseña requiere un transporte de correo real.');
}

return [
    // `log` para Laragon; `mail` cuando PHP tenga un servidor SMTP configurado.
    'transport' => $transport,
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@splitly.local',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Splitly',
];
