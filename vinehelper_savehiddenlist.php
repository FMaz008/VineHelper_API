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
    print json_encode(array('error'=>'Missing uuid'));
    die();//quit
}

if(!isset($arrJSON['arr']) || !is_array($arrJSON['arr'])){
    print json_encode(array('error'=>'Missing array of asin'));
    die();//quit
}

    



//Get the user_id from the uuid.
$user_id = null;
$stmt = $mysqli->prepare(
    "SELECT user_id"
    . " FROM `user`"
    . " WHERE uuid=?"
    . " LIMIT 1;"
    );
$stmt->bind_param("s", $arrJSON['uuid']);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($user_id);
$stmt->fetch();

if($user_id == null){
    print json_encode(array('error'=>'UUID invalid'));
    die();//quit
}

$stmt_h = $mysqli->prepare("INSERT IGNORE INTO product_hidden (`user_id`, `asin`, created_at) VALUES(?, ?, UTC_TIMESTAMP())");
$stmt_s = $mysqli->prepare("DELETE FROM product_hidden WHERE `user_id` = ? AND `asin` = ? LIMIT 1");
foreach ($arrJSON['arr'] as &$item) {
    if(!isset($item['asin']) || !isset($item['hidden']) || !in_array($item['hidden'], [true, false])){
        print json_encode(array('error'=>'Malformed request'));
        die();//quit
    }
    
    if($item['hidden']){
        $stmt_h->bind_param("ss", $user_id, $item['asin']);
        $stmt_h->execute();
    }else{
        $stmt_s->bind_param("ss", $user_id, $item['asin']);
        $stmt_s->execute();
    }

}

//Delete products over 90 days old
$stmt = $mysqli->prepare("DELETE FROM product_hidden WHERE created_at < UTC_TIMESTAMP() - interval 90 DAY;");
$stmt->execute();


print json_encode(array('ok'=>'ok'));
