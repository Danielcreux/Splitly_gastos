<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$data = requestData();
$userId = currentUserId();
$firstName = trim((string) ($data['first_name'] ?? ''));
$lastName = trim((string) ($data['last_name'] ?? ''));
$email = filter_var(trim((string) ($data['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$currency = (string) ($data['currency'] ?? 'EUR');
$locale = (string) ($data['locale'] ?? 'es-ES');
$booleanValue = static fn(string $key): int => isset($data[$key])
    && filter_var($data[$key], FILTER_VALIDATE_BOOL) ? 1 : 0;

if ($firstName === '' || mb_strlen($firstName) > 80 || mb_strlen($lastName) > 120 || !$email) {
    respond(['ok' => false, 'message' => 'Revisa tus datos personales.'], 422);
}
if (!in_array($currency, ['EUR', 'USD'], true) || !in_array($locale, ['es-ES', 'en-US'], true)) {
    respond(['ok' => false, 'message' => 'Moneda o idioma no válido.'], 422);
}

$db = databaseOrFail();
try {
    $db->beginTransaction();
    $db->prepare(
        'UPDATE users SET first_name = :first_name, last_name = :last_name,
         email = :email, currency = :currency, locale = :locale WHERE id = :id'
    )->execute([
        'first_name' => $firstName, 'last_name' => $lastName ?: null,
        'email' => mb_strtolower($email), 'currency' => $currency,
        'locale' => $locale, 'id' => $userId,
    ]);
    $db->prepare(
        'INSERT INTO user_preferences
           (user_id, notify_new_expense, notify_payment, notify_payment_reminder, notify_group_updates)
         VALUES (:user_id, :new_expense, :payment, :reminder, :group_updates)
         ON DUPLICATE KEY UPDATE notify_new_expense = VALUES(notify_new_expense),
           notify_payment = VALUES(notify_payment), notify_payment_reminder = VALUES(notify_payment_reminder),
           notify_group_updates = VALUES(notify_group_updates)'
    )->execute([
        'user_id' => $userId,
        'new_expense' => $booleanValue('notify_new_expense'),
        'payment' => $booleanValue('notify_payment'),
        'reminder' => $booleanValue('notify_payment_reminder'),
        'group_updates' => $booleanValue('notify_group_updates'),
    ]);
    $db->commit();
    $_SESSION['user_name'] = $firstName;
    respond(['ok' => true, 'message' => 'Configuración guardada correctamente.']);
} catch (PDOException $exception) {
    if ($db->inTransaction()) $db->rollBack();
    if ((string) $exception->getCode() === '23000') {
        respond(['ok' => false, 'message' => 'Ese correo ya está asociado a otra cuenta.'], 409);
    }
    error_log('[Update settings] ' . $exception->getMessage());
    respond(['ok' => false, 'message' => 'No se pudo guardar la configuración.'], 500);
}
