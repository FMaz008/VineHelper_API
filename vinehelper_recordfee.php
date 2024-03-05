<?php

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

if(!isset($arrJSON['fees'])){
    print json_encode(array('error'=>'JSON format does not specify the fees'));
    die();//quit
}

if(!isset($arrJSON['uuid'])){
    print json_encode(array('error'=>'JSON format does not specify uuid'));
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




//Ensure the product is registered
$stmt = $mysqli->prepare(
    "INSERT INTO product_url (`country`, `asin`) VALUES(?, ?)"
    . " ON DUPLICATE KEY UPDATE `date_updated`=UTC_TIMESTAMP()"
    );

$stmt->bind_param("ss", $arrJSON['country'], $arrJSON['asin']);
$stmt->execute();


//Cast the vote
$stmt = $mysqli->prepare(
    "INSERT INTO product_vote (`country`, `asin`, `user_id`, `fees`) VALUES(?, ?, ?, ?)"
    . " ON DUPLICATE KEY UPDATE `date_updated`=UTC_TIMESTAMP(), `fees`=?"
    );

$stmt->bind_param("ssiii", $arrJSON['country'], $arrJSON['asin'], $user_id, $arrJSON['fees'], $arrJSON['fees']);
$stmt->execute();



    
//Compile the votes
$stmtN = $mysqli->prepare("SELECT COUNT(*) FROM product_vote WHERE country=? AND asin=? AND fees=0;");
$stmtY = $mysqli->prepare("SELECT COUNT(*) FROM product_vote WHERE country=? AND asin=? AND fees=1;");

$stmtN->bind_param("ss", $arrJSON['country'], $arrJSON['asin']);
$stmtY->bind_param("ss", $arrJSON['country'], $arrJSON['asin']);

$stmtN->execute();
$stmtN->store_result();

$stmtY->execute();
$stmtY->store_result();

$feesN = 0;
$feesY = 0;
$stmtN->bind_result($feesN);
$stmtY->bind_result($feesY);


$stmtN->fetch();
$stmtY->fetch();


$stmt = $mysqli->prepare("UPDATE product_url SET vote_fees=?, vote_nofees=? WHERE country=? AND asin=?;");
$stmt->bind_param("iiss", $feesY, $feesN, $arrJSON['country'], $arrJSON['asin']);
$stmt->execute();



//Delete products over 90 days old (votes are auto-deleted by referencial integrity)
$stmt = $mysqli->prepare("DELETE FROM product_url WHERE date_updated < UTC_TIMESTAMP() - interval 90 DAY;");
$stmt->execute();


print json_encode(array('ok'=>'ok'));
