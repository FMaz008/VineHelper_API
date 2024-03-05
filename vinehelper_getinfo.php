<?php

if(!isset($arrJSON['arr_asin'])){
    print json_encode(array('error'=>'JSON format does not specify arr_asin'));
    die();//quit
}

if(isset($arrJSON['domain']) && !isset($arrJSON['country']))
    $arrJSON['country'] = $arrJSON["domain"];


if(!isset($arrJSON['uuid']) || $arrJSON['uuid'] == null){
	print json_encode(array('error'=>'JSON format does not specify UUID, please update your extension.'));
    die();//quit
}
$uuid = $arrJSON['uuid']; //>=V1.20
    





$arrResults = []; //Api v4


$stmti = $mysqli->prepare(
	"SELECT vote_fees, vote_nofees, p.order_success, p.order_failed, v.fees, h.created_at"
	. " FROM product_etv as p"
	. " LEFT JOIN product_url as pu ON (pu.asin = p.asin AND pu.country=p.country)"
	. " LEFT JOIN `user` as u ON (u.uuid = ?)"
	. " LEFT JOIN product_vote as v ON(v.asin = p.asin AND v.country = p.country AND v.user_id=u.user_id)"
	. " LEFT JOIN product_hidden as h ON (h.user_id=u.user_id AND h.asin=p.asin)"
	. " WHERE p.country = ? AND p.asin = ? LIMIT 1; ");

foreach ($arrJSON['arr_asin'] as $asin){
    
    $stmti->bind_param("sss", $uuid, $arrJSON['country'], $asin);
    $stmti->execute();
    $stmti->store_result();
    
    $vote_fees = 0; 
    $vote_nofees = 0;
    $order_success =0;
    $order_failed=0;
    $selectedFees = null;
    $hidden = null;
    
    
    $stmti->bind_result($vote_fees, $vote_nofees, $order_success, $order_failed, $selectedFees, $hidden);
    $stmti->fetch();
    $hidden= $hidden == null ? null : true;
    
    if($vote_fees == null) $vote_fees = 0;
    if($vote_nofees == null) $vote_nofees = 0;
    if($order_success == null) $order_success = 0;
    if($order_failed == null) $order_failed = 0;
    
    
    //We no longer rely on the fees field, we simply calculate it:
    $fees = null; //null means votes are needed to reach a concensus
    if($vote_fees - $vote_nofees >= 2){
        $fees = 1;
    }
    
    if($vote_nofees - $vote_fees >= 2){
        $fees = 0;
    }
    
    //Api v4
    $arrResults[$asin]["s"] = $selectedFees;
    $arrResults[$asin]["v1"] = $vote_fees;
    $arrResults[$asin]["v0"] = $vote_nofees;
    $arrResults[$asin]["order_success"] = $order_success;
    $arrResults[$asin]["order_failed"] = $order_failed;
    $arrResults[$asin]["hidden"] = $hidden;
    
    
}


//Get the ETV results
$addedCounter =0;
$stmt_e = $mysqli->prepare("SELECT etv, date_added, parent_asin, queue FROM product_etv WHERE asin = ? AND country = ? LIMIT 1");
$stmt_i = $mysqli->prepare("INSERT IGNORE INTO product_etv (country, asin, queue, date_added) VALUES (?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE queue=?");
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
    
    
    if($date_added == null || $queue == null){
        //The product does not exist, add it, so the date first seen will be created
        $queue = null;
        if(isset($arrJSON['queue']))
            $queue = $arrJSON['queue'];
        $stmt_i->bind_param("ssss", $arrJSON['country'], $asin, $queue, $queue);
        $stmt_i->execute();
        
        $addedCounter++;
        
        $arrResults[$asin]["date_added"] = date('Y-m-d h:i:s', time()); //Send temporary value
    }else{
        $arrResults[$asin]["date_added"] = $date_added;
    }
    
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

//If the extension pass on product details, save them
$entityBody = file_get_contents('php://input');
if(!empty($entityBody)){
	$arr = json_decode($entityBody, true);
	$arrCount = count($arr);
	$query = "INSERT IGNORE INTO product_detail (country, asin, title, img_url) VALUES (?,?,?,?)";
	$stmt = $mysqli->prepare($query);
	foreach($arr as $item){
		//{ asin: asin, title: title, thumbnail: thumbnail }
		$stmt->bind_param("ssss", $arrJSON['country'], $item['asin'], $item['title'], $item['thumbnail']);
    	$stmt->execute();
	}
}

$time_elapsed_secs = microtime(true) - $start;
$date = new \DateTime("now", new \DateTimeZone("UTC"));
$currentTime = $date->format('Y-m-d H:i:s');
if($arrJSON['api_version'] == 4){
    $arrJSONResult = array("api_version" => 4, "crunch_time" => $time_elapsed_secs, "products" => $arrResults, "current_time"=>$currentTime, "added"=>$addedCounter, "notification"=>[]);
}else{
    $arrJSONResult = array();
}

print json_encode($arrJSONResult);