<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection();$uid=(int)$_SESSION['user_id'];
$tasks=$pdo->prepare('SELECT COUNT(*) total,SUM(status="completed") completed,SUM(status="in_progress") active,SUM(status="todo") todo FROM tasks WHERE user_id=:uid');$tasks->execute(['uid'=>$uid]);$taskData=$tasks->fetch();
$categories=$pdo->prepare('SELECT category,COUNT(*) count FROM tasks WHERE user_id=:uid GROUP BY category ORDER BY count DESC');$categories->execute(['uid'=>$uid]);
echo json_encode(['analytics'=>$taskData,'categories'=>$categories->fetchAll()],JSON_PRETTY_PRINT);
