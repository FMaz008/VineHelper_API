<?php

if($arrJSON === null){
    print json_encode(array('error'=>'GET/POST data is not a valid JSON format'));
    die();//quit
}


if(!isset($arrJSON['api_version']) || $arrJSON['api_version']!=4){
    print json_encode(array('error'=>'JSON format does not specify api_version or a supported value'));
    die();//quit
}



//In case the product was ordered before any vote was casted, create the product first:



$mysqli->begin_transaction();
$stmt = $mysqli->prepare("INSERT IGNORE INTO user (`uuid`, created_at, updated_at) VALUES(UUID(), UTC_TIMESTAMP(), UTC_TIMESTAMP())");
$stmt->execute();
$stmt = $mysqli->prepare("SELECT user_id, uuid FROM user WHERE user_id=LAST_INSERT_ID() LIMIT 1;");
$stmt->execute();
$stmt->store_result();
$uuid = null;
$user_id = null;
$stmt->bind_result($user_id, $uuid);
$stmt->fetch();
$mysqli->commit();





//Delete users over 90 days old
//$stmt = $mysqli->prepare("DELETE FROM user WHERE updated_at < now() - interval 90 DAY;");
//$stmt->execute();


print json_encode(array('ok'=>'ok', 'uuid'=>$uuid));
