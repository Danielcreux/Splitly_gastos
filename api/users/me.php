<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requireMethod('GET');
$user = authenticatedUser(databaseOrFail());
respond(['ok' => true, 'user' => userPayload($user)]);
