<?php

if($arrJSON === null){
    print json_encode(array('error'=>'GET/POST data is not a valid JSON format'));
    die();//quit
}


if(!isset($arrJSON['api_version']) || $arrJSON['api_version']<3){
    print json_encode(array('error'=>'JSON format does not specify api_version or a supported value'));
    die();//quit
}

if(!isset($arrJSON['country'])){
    print json_encode(array('error'=>'JSON format does not specify country'));
    die();//quit
}

if(!isset($arrJSON['asin'])){
    print json_encode(array('error'=>'JSON format does not specify asin'));
    die();//quit
}

if(!isset($arrJSON["parent_asin"]) && $arrJSON["parent_asin"] !== NULL){
    print json_encode(array('error'=>'JSON format does not specify parent_asin'));
    die();//quit
}
 
if(!isset($arrJSON['etv'])){
    print json_encode(array('error'=>'JSON format does not specify etv'));
    die();//quit
}   
    
$queue = null;
if(isset($arrJSON['queue'])){
    if(in_array($arrJSON['queue'], ["potluck", "last_chance", "encore"]))
        $queue = $arrJSON['queue'];
}   




//Record the ETV
if($arrJSON['parent_asin'] == null){
    $stmt = $mysqli->prepare(
        "INSERT INTO product_etv (`country`, `asin`, `parent_asin`, `etv`, `queue`) VALUES(?, ?, null, ?, ?)"
        . " ON DUPLICATE KEY UPDATE `etv`=?, `queue`=?"
        );
    $stmt->bind_param("ssssss", $arrJSON['country'], $arrJSON['asin'], $arrJSON['etv'], $queue, $arrJSON['etv'], $queue);
}else{
    $stmt = $mysqli->prepare(
        "INSERT INTO product_etv (`country`, `asin`, `parent_asin`, `etv`, `queue`) VALUES(?, ?, ?, ?, ?)"
        . " ON DUPLICATE KEY UPDATE `etv`=?, `queue`=?"
        );
    $stmt->bind_param("sssssss", $arrJSON['country'], $arrJSON['asin'], $arrJSON['parent_asin'], $arrJSON['etv'], $queue, $arrJSON['etv'], $queue);
}

$stmt->execute();




//Delete products over 90 days old
$stmt = $mysqli->prepare("DELETE FROM product_etv WHERE date_added < UTC_TIMESTAMP() - interval 90 DAY;");
$stmt->execute();


print json_encode(array('ok'=>'ok'));
