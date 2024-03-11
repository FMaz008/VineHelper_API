<?php

if(!isset($arrJSON['arr_asin'])){
    print json_encode(array('error'=>'JSON format does not specify arr_asin'));
    die();//quit
}

if(isset($arrJSON['domain']) && !isset($arrJSON['country']))
    $arrJSON['country'] = $arrJSON["domain"];

if(!isset($arrJSON['key']) || $arrJSON['key'] != "ultraviner123"){
    print json_encode(array('error'=>'Bad key'));
    die();//quit
}






$arrResults = []; //Api v4


$stmti = $mysqli->prepare(
	"SELECT vote_fees, vote_nofees, p.order_success, p.order_failed"
	. " FROM product_etv as p"
	. " LEFT JOIN product_url as pu ON (pu.asin = p.asin AND pu.country=p.country)"
	. " WHERE p.country = ? AND p.asin = ? LIMIT 1; ");

foreach ($arrJSON['arr_asin'] as $asin){
    
    $stmti->bind_param("ss", $arrJSON['country'], $asin);
    $stmti->execute();
    $stmti->store_result();
    
    $vote_fees = 0; 
    $vote_nofees = 0;
    $order_success =0;
    $order_failed=0;
    
    
    $stmti->bind_result($vote_fees, $vote_nofees, $order_success, $order_failed);
    $stmti->fetch();
    
    if($vote_fees == null) $vote_fees = 0;
    if($vote_nofees == null) $vote_nofees = 0;
    if($order_success == null) $order_success = 0;
    if($order_failed == null) $order_failed = 0;
    
    //Api v4
    $arrResults[$asin]["v1"] = $vote_fees;
    $arrResults[$asin]["v0"] = $vote_nofees;
    $arrResults[$asin]["order_success"] = $order_success;
    $arrResults[$asin]["order_failed"] = $order_failed;
    
    
}


//Get the ETV results
$stmt_e = $mysqli->prepare("SELECT etv, date_added, parent_asin, queue FROM product_etv WHERE asin = ? AND country = ? LIMIT 1");
$stmt_p = $mysqli->prepare("SELECT MIN(etv), MAX(etv), MIN(date_added) as date_added FROM product_etv WHERE parent_asin = ? AND country = ? GROUP BY parent_asin");
foreach ($arrJSON['arr_asin'] as $asin){
    
    
    //Get all regular products
    
    $stmt_e->bind_param("ss", $asin, $arrJSON['country']);
    $stmt_e->execute();
    $stmt_e->store_result();
    
    $etv = null;
    $date_added = null;
    $parent_asin = null;
    $queue = null;
    $stmt_e->bind_result($etv, $date_added, $parent_asin, $queue);
    $stmt_e->fetch();
    
    
    $arrResults[$asin]["date_added"] = $date_added;
    
    if($parent_asin == null && $etv != null){
        $arrResults[$asin]["etv_min"] = $etv;
        $arrResults[$asin]["etv_max"] = $etv;
        continue; //The product was retreived, no need to continue
    }
    
    
    
    //Get all products with a parent asin
    $stmt_p->bind_param("ss", $asin, $arrJSON['country']);
    $stmt_p->execute();
    $stmt_p->store_result();
    
    $etv_min = null;
    $etv_max = null;
    $stmt_p->bind_result($etv_min, $etv_max, $date_added);
    $stmt_p->fetch();
    
    if($etv_min != null){
        $arrResults[$asin]["etv_min"] = $etv_min;
        $arrResults[$asin]["etv_max"] = $etv_max;
        
        continue; //The product was retreived, no need to continue
    }
    
    //Item not found
    $arrResults[$asin]["etv_min"] = null;
    $arrResults[$asin]["etv_max"] = null;
}



$time_elapsed_secs = microtime(true) - $start;
$date = new \DateTime("now", new \DateTimeZone("UTC"));
$currentTime = $date->format('Y-m-d H:i:s');
if($arrJSON['api_version'] == 4){
    $arrJSONResult = array("api_version" => 4, "crunch_time" => $time_elapsed_secs, "products" => $arrResults, "current_time"=>$currentTime);
}else{
    $arrJSONResult = array();
}

print json_encode($arrJSONResult);