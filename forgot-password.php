<?php
$app = require __DIR__ . '/config/app.php';
if (!$app['password_reset_enabled']) {
    http_response_code(404);
    header('Location: login.php');
    exit;
}
$authMode = 'forgot';
require __DIR__ . '/includes/auth-page.php';
