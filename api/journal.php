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
$ifMethod=strtolower((string)($input['_method']??''));
if($ifMethod==='delete')$method='DELETE';
if($method!=='GET') requireCsrf($input);

try{
    if($method==='GET'){
        $stmt=$pdo->prepare('SELECT id,entry_date,mood,energy,gratitude,reflection,wins FROM journal_entries WHERE user_id=:uid ORDER BY entry_date DESC LIMIT 14');
        $stmt->execute(['uid'=>$uid]);
        echo json_encode(['entries'=>$stmt->fetchAll()],JSON_PRETTY_PRINT);
        exit;
    }
    if($method==='POST'||$method==='PUT'){
        $date=trim((string)($input['entry_date']??date('Y-m-d')));
        $parsed=preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        $errors=$parsed?DateTimeImmutable::getLastErrors():false;
        if($errors&&($errors['warning_count']>0||$errors['error_count']>0))$parsed=false;
        if(!$parsed){http_response_code(422);echo json_encode(['error'=>'Valid journal date is required']);exit;}
        $mood=$input['mood']??'okay';
        if(!in_array($mood,['great','good','okay','low','rough'],true)) $mood='okay';
        $energy=max(1,min(5,(int)($input['energy']??3)));
        $gratitude=trim((string)($input['gratitude']??''));
        $wins=trim((string)($input['wins']??''));
        $reflection=trim((string)($input['reflection']??''));
        if(mb_strlen($gratitude)>65535||mb_strlen($wins)>65535||mb_strlen($reflection)>65535){
            http_response_code(422);
            echo json_encode(['error'=>'Journal text exceeds the supported length']);
            exit;
        }
        $lookup=$pdo->prepare('SELECT id FROM journal_entries WHERE user_id=:uid AND entry_date=:date');
        $lookup->execute(['uid'=>$uid,'date'=>$parsed->format('Y-m-d')]);
        $existing=(int)$lookup->fetchColumn();
        $values=[
            'uid'=>$uid,
            'date'=>$parsed->format('Y-m-d'),
            'mood'=>$mood,
            'energy'=>$energy,
            'gratitude'=>$gratitude,
            'wins'=>$wins,
            'reflection'=>$reflection,
        ];
        if($existing>0){
            $stmt=$pdo->prepare('UPDATE journal_entries SET mood=:mood,energy=:energy,gratitude=:gratitude,reflection=:reflection,wins=:wins,updated_at=NOW() WHERE id=:id AND user_id=:uid');
            $stmt->execute(['mood'=>$mood,'energy'=>$energy,'gratitude'=>$values['gratitude'],'reflection'=>$values['reflection'],'wins'=>$values['wins'],'id'=>$existing,'uid'=>$uid]);
        }else{
            $stmt=$pdo->prepare('INSERT INTO journal_entries(user_id,entry_date,mood,energy,gratitude,reflection,wins) VALUES(:uid,:date,:mood,:energy,:gratitude,:reflection,:wins)');
            $stmt->execute($values);
        }
        echo json_encode(['message'=>'Journal entry saved']);
        exit;
    }
    if($method==='DELETE'){
        $id=(int)($input['id']??0);
        $date=trim((string)($input['entry_date']??''));
        if($id>0){
            $stmt=$pdo->prepare('DELETE FROM journal_entries WHERE id=:id AND user_id=:uid');
            $stmt->execute(['id'=>$id,'uid'=>$uid]);
        }elseif(preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){
            $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            $errors=DateTimeImmutable::getLastErrors();
            if($parsed&&(!$errors||($errors['warning_count']===0&&$errors['error_count']===0))){
                $stmt=$pdo->prepare('DELETE FROM journal_entries WHERE entry_date=:date AND user_id=:uid');
                $stmt->execute(['date'=>$parsed->format('Y-m-d'),'uid'=>$uid]);
            }
        }else{
            http_response_code(422);
            echo json_encode(['error'=>'Valid journal ID or date is required']);
            exit;
        }
        echo json_encode(['message'=>'Journal entry deleted']);
        exit;
    }
    http_response_code(405);
    echo json_encode(['error'=>'Method not allowed']);
}catch(Throwable $exception){
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'Unable to save journal entry']);
}
