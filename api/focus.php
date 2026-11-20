<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/functions.php';
requireAuthenticatedSession();
$pdo=Database::getInstance()->connection();
$uid=(int)$_SESSION['user_id'];
$method=$_SERVER['REQUEST_METHOD']??'GET';
$raw=(string)file_get_contents('php://input');
$input=json_decode($raw,true);
if(!is_array($input)) parse_str($raw,$input);
if(!is_array($input)) $input=$_POST;
if($method!=='GET') requireCsrf($input);
try{
 if($method==='GET'){
  $stmt=$pdo->prepare('SELECT id,task_id,duration_minutes,started_at,completed_at,status FROM focus_sessions WHERE user_id=:uid ORDER BY started_at DESC LIMIT 20');
  $stmt->execute(['uid'=>$uid]);
  echo json_encode(['sessions'=>$stmt->fetchAll(),'summary'=>getFocusSummary($pdo,$uid)],JSON_PRETTY_PRINT);
  exit;
 }
 if($method==='POST'){
  $duration=max(1,min(180,(int)($input['duration_minutes']??25)));
  $taskId=(int)($input['task_id']??0);
  if($taskId>0){
   $owned=$pdo->prepare('SELECT id FROM tasks WHERE id=:id AND user_id=:uid');
   $owned->execute(['id'=>$taskId,'uid'=>$uid]);
   if(!$owned->fetch()){http_response_code(404);echo json_encode(['error'=>'Task not found']);exit;}
  }
  $stmt=$pdo->prepare('INSERT INTO focus_sessions(user_id,task_id,duration_minutes,started_at,status) VALUES(:uid,:task_id,:duration,NOW(),"running")');
  $stmt->execute(['uid'=>$uid,'task_id'=>$taskId>0?$taskId:null,'duration'=>$duration]);
  echo json_encode(['id'=>(int)$pdo->lastInsertId(),'message'=>'Focus session started'],201);
  exit;
 }
 if($method==='PATCH'){
  $id=(int)($input['id']??0);
  $status=$input['status']??'completed';
  if(!in_array($status,['running','completed','cancelled'],true)){$status='cancelled';}
  if($id<1){http_response_code(422);echo json_encode(['error'=>'Valid focus session ID is required']);exit;}
  $owned=$pdo->prepare('SELECT id FROM focus_sessions WHERE id=:id AND user_id=:uid');
  $owned->execute(['id'=>$id,'uid'=>$uid]);
  if(!$owned->fetch()){http_response_code(404);echo json_encode(['error'=>'Focus session not found']);exit;}
  $stmt=$pdo->prepare('UPDATE focus_sessions SET status=:status,completed_at=CASE WHEN :status!="running" THEN NOW() ELSE NULL END WHERE id=:id AND user_id=:uid');
  $stmt->execute(['status'=>$status,'id'=>$id,'uid'=>$uid]);
  echo json_encode(['message'=>'Focus session updated']);
  exit;
 }
 http_response_code(405);echo json_encode(['error'=>'Method not allowed']);
}catch(Throwable $exception){error_log($exception->getMessage());http_response_code(500);echo json_encode(['error'=>'Unable to process focus session']);}
