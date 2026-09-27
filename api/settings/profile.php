<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$statement = $db->prepare(
    "SELECT u.id,u.first_name,u.last_name,u.email,u.avatar_path,u.currency,u.locale,
            COALESCE(p.notify_new_expense,1) AS notify_new_expense,
            COALESCE(p.notify_payment,1) AS notify_payment,
            COALESCE(p.notify_payment_reminder,1) AS notify_payment_reminder,
            COALESCE(p.notify_group_updates,1) AS notify_group_updates,
            COALESCE(p.dark_mode,0) AS dark_mode
     FROM users u LEFT JOIN user_preferences p ON p.user_id=u.id
     WHERE u.id=:id AND u.is_active=1 LIMIT 1"
);
$statement->execute(['id' => $userId]);
$profile = $statement->fetch();
if (!$profile) {
    respond(['ok' => false, 'error' => 'not_found', 'message' => 'No se pudo cargar el perfil.'], 404);
}
respond(['ok' => true, 'profile' => [
    'id' => (int) $profile['id'],
    'firstName' => $profile['first_name'],
    'lastName' => $profile['last_name'] ?? '',
    'name' => trim($profile['first_name'] . ' ' . ($profile['last_name'] ?? '')),
    'email' => $profile['email'],
    'avatarUrl' => $profile['avatar_path'] ?: null,
    'currency' => $profile['currency'],
    'locale' => $profile['locale'],
    'notifyNewExpense' => (bool) $profile['notify_new_expense'],
    'notifyPayment' => (bool) $profile['notify_payment'],
    'notifyPaymentReminder' => (bool) $profile['notify_payment_reminder'],
    'notifyGroupUpdates' => (bool) $profile['notify_group_updates'],
    'darkMode' => (bool) $profile['dark_mode'],
]]);
