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

try {
    if($method==='GET'){
        echo json_encode(['goals'=>getGoals($pdo,$uid)],JSON_PRETTY_PRINT);
        exit;
    }
    if($method==='POST'){
        $title=trim((string)($input['title']??''));
        if($title===''){http_response_code(422);echo json_encode(['error'=>'Goal title is required']);exit;}
        if(mb_strlen($title)>180){http_response_code(422);echo json_encode(['error'=>'Goal title is too long']);exit;}
        $target=max(0.01,(float)($input['target_value']??100));
        $current=max(0,(float)($input['current_value']??0));
        $targetDate=$input['target_date']??null;
        $description=trim((string)($input['description']??''));
        $unit=substr(trim((string)($input['unit']??'percent')),0,30);
        if(mb_strlen($description)>65535){http_response_code(422);echo json_encode(['error'=>'Goal description is too long']);exit;}
        $date=false;
        if(is_string($targetDate)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$targetDate)){
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$targetDate);
            $errors=DateTimeImmutable::getLastErrors();
            if(!$date||($errors&&($errors['warning_count']>0||$errors['error_count']>0)))$date=false;
        }
        $statement=$pdo->prepare('INSERT INTO goals(user_id,title,description,target_value,current_value,unit,target_date,status) VALUES(:uid,:title,:description,:target,:current,:unit,:target_date,"active")');
        $statement->execute(['uid'=>$uid,'title'=>$title,'description'=>$description,'target'=>$target,'current'=>min($current,$target),'unit'=>$unit,'target_date'=>$date?$date->format('Y-m-d'):null]);
        echo json_encode(['id'=>(int)$pdo->lastInsertId(),'message'=>'Goal created'],201);
        exit;
    }
    if($method==='PATCH'){
        $id=(int)($input['id']??0);
        if($id<1){http_response_code(422);echo json_encode(['error'=>'Valid goal ID is required']);exit;}
        if(isset($input['current_value'])){
            $value=max(0,(float)$input['current_value']);
            $owned=$pdo->prepare('SELECT id FROM goals WHERE id=:id AND user_id=:uid');
            $owned->execute(['id'=>$id,'uid'=>$uid]);
            if(!$owned->fetch()){http_response_code(404);echo json_encode(['error'=>'Goal not found']);exit;}
            $stmt=$pdo->prepare('UPDATE goals SET current_value=:value,status=CASE WHEN :value>=target_value THEN "completed" ELSE "active" END WHERE id=:id AND user_id=:uid');
            $stmt->execute(['value'=>$value,'id'=>$id,'uid'=>$uid]);
        }elseif(isset($input['status'])&&in_array($input['status'],['active','completed','paused','archived'],true)){
            $stmt=$pdo->prepare('UPDATE goals SET status=:status WHERE id=:id AND user_id=:uid');
            $stmt->execute(['status'=>$input['status'],'id'=>$id,'uid'=>$uid]);
        }
        echo json_encode(['message'=>'Goal updated']);
        exit;
    }
    if($method==='DELETE'){
        $id=(int)($input['id']??0);
        $owned=$pdo->prepare('SELECT id FROM goals WHERE id=:id AND user_id=:uid');
        $owned->execute(['id'=>$id,'uid'=>$uid]);
        if(!$owned->fetch()){http_response_code(404);echo json_encode(['error'=>'Goal not found']);exit;}
        $stmt=$pdo->prepare('UPDATE goals SET status="archived" WHERE id=:id AND user_id=:uid');
        $stmt->execute(['id'=>$id,'uid'=>$uid]);
        echo json_encode(['message'=>'Goal archived']);
        exit;
    }
    http_response_code(405);
    echo json_encode(['error'=>'Method not allowed']);
}catch(Throwable $exception){
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'Unable to process goal']);
}
