<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requirePost();

$notificationId = filter_var(requestData()['notification_id'] ?? null, FILTER_VALIDATE_INT);
if (!$notificationId) {
    respond(['ok' => false, 'message' => 'La notificación no es válida.'], 422);
}

$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$statement = $db->prepare(
    'DELETE FROM notifications WHERE id = :notification_id AND user_id = :user_id'
);
$statement->execute([
    'notification_id' => $notificationId,
    'user_id' => $userId,
]);

if ($statement->rowCount() === 0) {
    respond(['ok' => false, 'message' => 'La notificación ya no existe.'], 404);
}

respond(['ok' => true, 'message' => 'Notificación eliminada.']);
