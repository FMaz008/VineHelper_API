<?php

if($arrJSON === null){
    print json_encode(array('error'=>'GET/POST data is not a valid JSON format'));
    die();//quit
}


if(!isset($arrJSON['api_version']) || $arrJSON['api_version']!=4){
    print json_encode(array('error'=>'JSON format does not specify api_version or a supported value'));
    die();//quit
}
    

if(!isset($arrJSON['uuid'])){
    print json_encode(array('error'=>'JSON format does not specify uuid'));
    die();//quit
}




//In case the product was ordered before any vote was casted, create the product first:

$stmt = $mysqli->prepare("SELECT user_id, uuid FROM user WHERE uuid=? LIMIT 1;");
$stmt->bind_param("s", $arrJSON['uuid']);
$stmt->execute();
$stmt->store_result();
$uuid = null;
$user_id = null;
$stmt->bind_result($user_id, $uuid);
$stmt->fetch();


if($uuid == null)
    print json_encode(array('error'=>'UUID invalid'));
else
    print json_encode(array('ok'=>'ok', 'uuid'=>$uuid));
