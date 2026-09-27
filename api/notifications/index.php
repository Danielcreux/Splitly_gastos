<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
requireMethod('GET');
$db = databaseOrFail();
$userId = (int) authenticatedUser($db)['id'];
$pagination = paginationParams();
$count = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:user_id');
$count->execute(['user_id' => $userId]);
$total = (int) $count->fetchColumn();
$unread = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:user_id AND read_at IS NULL');
$unread->execute(['user_id' => $userId]);
$statement = $db->prepare(
    'SELECT id,type,title,message,action_url,read_at,created_at
     FROM notifications WHERE user_id=:user_id
     ORDER BY created_at DESC,id DESC LIMIT :per_page OFFSET :offset'
);
$statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
$statement->bindValue(':per_page', $pagination['per_page'], PDO::PARAM_INT);
$statement->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$statement->execute();
$items = array_map(static fn(array $row): array => [
    'id' => (int) $row['id'],
    'type' => $row['type'],
    'title' => $row['title'],
    'message' => $row['message'],
    'actionUrl' => $row['action_url'],
    'createdAt' => (new DateTimeImmutable((string) $row['created_at']))->format(DateTimeInterface::ATOM),
    'isRead' => $row['read_at'] !== null,
], $statement->fetchAll());
respond([
    'data' => $items,
    'pagination' => paginationMetadata($total, $pagination),
    'meta' => ['unread_count' => (int) $unread->fetchColumn()],
]);
