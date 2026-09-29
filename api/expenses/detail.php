<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
requireMethod('GET'); $db=databaseOrFail(); $userId=(int)authenticatedUser($db)['id']; $expenseId=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$expenseId) respond(['ok'=>false,'error'=>'invalid_expense','message'=>'El gasto no es válido.'],422);
$statement=$db->prepare("SELECT e.*,g.name AS group_name,c.name AS category,c.icon AS category_icon,c.color AS category_color,TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS paid_by_name,COALESCE(mine.amount_owed,0) AS your_share,gm.role FROM expenses e INNER JOIN expense_groups g ON g.id=e.group_id INNER JOIN group_members gm ON gm.group_id=e.group_id AND gm.user_id=:user_id AND gm.status='active' INNER JOIN users u ON u.id=e.paid_by LEFT JOIN categories c ON c.id=e.category_id LEFT JOIN expense_splits mine ON mine.expense_id=e.id AND mine.user_id=:share_user WHERE e.id=:id AND e.status='active' LIMIT 1");
$statement->execute(['user_id'=>$userId,'share_user'=>$userId,'id'=>$expenseId]); $expense=$statement->fetch();
if(!$expense) respond(['ok'=>false,'error'=>'not_found','message'=>'El gasto no existe o no tienes acceso.'],404);
$splits=$db->prepare("SELECT s.user_id,TRIM(CONCAT(u.first_name,' ',COALESCE(u.last_name,''))) AS user_name,s.amount_owed,s.percentage FROM expense_splits s INNER JOIN users u ON u.id=s.user_id WHERE s.expense_id=:id ORDER BY u.first_name,u.id"); $splits->execute(['id'=>$expenseId]);
$payload=expensePayload($expense); $payload['splits']=array_map(static fn($s)=>['userId'=>(int)$s['user_id'],'userName'=>$s['user_name'],'amount'=>(float)$s['amount_owed'],'percentage'=>$s['percentage']===null?null:(float)$s['percentage']],$splits->fetchAll());
$payload['splitCount']=count($payload['splits']);
$payload['canEdit']=(int)$expense['created_by']===$userId||in_array($expense['role'],['owner','admin'],true); $payload['canDelete']=$payload['canEdit'];
respond(['ok'=>true,'expense'=>$payload]);
