<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();
$userId = currentUserId();
$db = databaseOrFail();
$db->prepare('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE user_id = :user_id')
    ->execute(['user_id' => $userId]);
respond(['ok' => true]);
