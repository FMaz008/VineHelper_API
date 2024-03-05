<?php
$start = microtime(true);

if($arrJSON === null){
    print json_encode(array('error'=>'GET/POST data is not a valid JSON format'));
    die();//quit
}


if(!isset($arrJSON['api_version']) || $arrJSON['api_version']!=4){
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

if(!isset($arrJSON["parent_asin"])){
    $arrJSON["parent_asin"] = null;
}
 
if(!isset($arrJSON['order_status'])){
    print json_encode(array('error'=>'JSON format does not specify order_status'));
    die();//quit
}   


$orderStatus = $arrJSON['order_status'] == "success" ? 1 : 0;


if(!isset($arrJSON['uuid'])){ //Prior to V1.15, uuid was not specified
	print json_encode(array('error'=>'JSON format does not specify UUID, please update your extension'));
    die();//quit
}

//Get the user_id from the uuid
$stmt = $mysqli->prepare("SELECT user_id FROM user WHERE uuid=? LIMIT 1;");
$stmt->bind_param("s", $arrJSON['uuid']);
$stmt->execute();
$stmt->store_result();
$user_id = null;
$stmt->bind_result($user_id);
$stmt->fetch();
    
    
    
if($user_id==null){
	print json_encode(array('error'=>'uuid invalid'));
	die();//quit
}








//Record the order
if($arrJSON['parent_asin'] == null){
    $stmt = $mysqli->prepare(
        "INSERT INTO product_order (`country`, `asin`, `parent_asin`, `user_id`, `success`) VALUES(?, ?, null, ?, ?)"
        . " ON DUPLICATE KEY UPDATE `success`=?, date_ordered = UTC_TIMESTAMP()"
        );
    $stmt->bind_param("ssiss", $arrJSON['country'], $arrJSON['asin'], $user_id, $orderStatus, $orderStatus);
    $stmt->execute();
}else{
    try {
    $stmt = $mysqli->prepare(
        "INSERT INTO product_order (`country`, `asin`, `parent_asin`, `user_id`, `success`) VALUES(?, ?, ?, ?, ?)"
        . " ON DUPLICATE KEY UPDATE `success`=?, date_ordered = UTC_TIMESTAMP()"
        );
    $stmt->bind_param("sssiss", $arrJSON['country'], $arrJSON['asin'], $arrJSON['parent_asin'], $user_id, $orderStatus, $orderStatus);
    $stmt->execute();
    } catch(Exception $e){
        //variant never created, ETV was not shared.
        print json_encode(array('error'=>'Canot insert ' . $arrJSON['country'] . "/".$arrJSON['asin']. "/". $arrJSON['parent_asin'] . ". Likely not found in product_etv."));
        die();//quit
    }
}



$asin = $arrJSON['asin'];
if($arrJSON['parent_asin'] != null){
    $asin = $arrJSON['parent_asin'];
    $stmtN = $mysqli->prepare("SELECT COUNT(*) FROM product_order WHERE country=? AND parent_asin=? AND success=0;");
    $stmtY = $mysqli->prepare("SELECT COUNT(*) FROM product_order WHERE country=? AND parent_asin=? AND success=1;");
}else{
    $stmtN = $mysqli->prepare("SELECT COUNT(*) FROM product_order WHERE country=? AND asin=? AND success=0;");
    $stmtY = $mysqli->prepare("SELECT COUNT(*) FROM product_order WHERE country=? AND asin=? AND success=1;");
}
    
$stmtN->bind_param("ss", $arrJSON['country'], $asin);
$stmtY->bind_param("ss", $arrJSON['country'], $asin);

$stmtN->execute();
$stmtN->store_result();

$stmtY->execute();
$stmtY->store_result();

$orderN = 0;
$orderY = 0;
$stmtN->bind_result($orderN);
$stmtY->bind_result($orderY);

$stmtN->fetch();
$stmtY->fetch();


$stmt = $mysqli->prepare("UPDATE product_etv SET order_failed=?, order_success=? WHERE country=? AND asin=?;");
$stmt->bind_param("iiss", $orderN, $orderY, $arrJSON['country'], $asin);
$stmt->execute();


//Delete votes that have confirmed orders:
$asin = null;
$country = null;
$stmt = $mysqli->prepare(
    "SELECT items.asin as asin, v.country as country"
    . " FROM product_etv as items"
    . " INNER JOIN product_vote as v ON (v.country = items.country AND v.asin = items.asin)"
    . " WHERE order_success <>0 OR order_failed <>0;");
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($asin, $country);

$stmtd = $mysqli->prepare("DELETE FROM product_vote WHERE asin=? AND country=? LIMIT 1;");
$deletedCounter = 0;
while($stmt->fetch()){
    $stmtd->bind_param("ss", $asin, $country);
    $stmtd->execute();
    $deletedCounter++;
}


//Delete products over 90 days old
$stmt = $mysqli->prepare("DELETE FROM product_order WHERE date_ordered < UTC_TIMESTAMP() - interval 90 DAY;");
$stmt->execute();

$time_elapsed_secs = microtime(true) - $start;

print json_encode(array('ok'=>'ok', "votes_deleted"=>$deletedCounter, "crunch_time"=>$time_elapsed_secs));
