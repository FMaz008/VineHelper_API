<?php

//Usage:
//francoismazerolle.ca/vineHelperLatest.php?data={'api_version':4, 'country':'ca', 'orderby':'date'}

//return a JSON array of the 100 most recently added product

//Load Sentry
require_once('../vendor/autoload.php');

\Sentry\init([
  'dsn' => 'https://a6c3fa561b393db525bbae83e073e9c1@o4506847605817344.ingest.sentry.io/4506847607717888',
  // Specify a fixed sample rate
  'traces_sample_rate' => 0.2,
  // Set a sampling rate for profiling - this is relative to traces_sample_rate
  'profiles_sample_rate' => 1.0,
]);


$start = microtime(true);

//Import configuration file
require ("deactivateCORS.php");
require ("../vinehelper_config.php");


//Sketchy, will clean up once I get something working
if(isset($_GET['data']))
    $_POST['data'] = $_GET['data'];
    
//Check if we have a valid request
if(!isset($_POST['data'])){
    if(isset($_GET['list'])){
        require("vineHelperLastest_html.php");
    }
    die();//quit
}

header('Content-Type: application/json; charset=utf-8');

//Establish the MySQL connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try{
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_BASE);
}
catch(Exception $e){
    $arrJSONResult = array("api_version" => 4, "crunch_time" => -1, "current_time"=>date("Y-m-d H:i:s"), "products" =>[], "notification"=>["Server at capacity."]);
    print json_encode($arrJSONResult);
    die();
}



$arrJSON = json_decode($_POST['data'], true);

if($arrJSON === null){
    print json_encode(array('error'=>'GET/POST data is not a valid JSON format'));
    die();//quit
}

if(!isset($arrJSON['api_version'])){
    print json_encode(array('error'=>'JSON format does not specify api_version or a supported value'));
    die();//quit
}

if(!isset($arrJSON['country'])){
    print json_encode(array('error'=>'JSON format does not specify country'));
    die();//quit
}
if(!isset($arrJSON['orderby'])){
    print json_encode(array('error'=>'JSON format does not specify orderby'));
    die();//quit
}


$arrResults = []; //Api v4.

$orderby = "date_added DESC";
$where = "";
$limit = 50;
if(isset($arrJSON['limit'])){
    $limit = intval($arrJSON['limit']);
    if($limit>50)
        $limit = 50;
}

switch($arrJSON['orderby']){
    case "etv":
        $orderby = "p.etv DESC, p.date_added DESC";
        break;
    case "etv0":
        $orderby = "p.etv, p.date_added DESC";
        $where = " AND p.etv IS NOT null";
        break;
    case "date":
        $orderby = "p.date_added DESC";
        $where = " AND p.parent_asin IS NULL AND (p.`queue`!='potluck' AND p.`queue` IS NOT NULL)";
        break;
}

$stmt = $mysqli->prepare(
	"SELECT p.asin, p.etv, p.`queue`, p.date_added, d.title, d.img_url"
	. " FROM product_etv as p"
	. " LEFT JOIN product_detail as d ON (p.country = d.country AND p.asin = d.asin)"
	. " WHERE p.country = ? $where ORDER BY $orderby LIMIT ?");
$stmt->bind_param("si", $arrJSON['country'], $limit);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($asin, $etv, $queue, $date, $title, $img_url);


while($stmt->fetch()){
    $arrResults[] = array(
        "asin" => $asin,
        "queue"=>$queue,
        "etv" => $etv,
        "date" => $date, 
		"title" => $title, 
		"img_url" => $img_url
    );
}
    
    
$time_elapsed_secs = microtime(true) - $start;

$arrJSONResult = array("api_version" => 4, "crunch_time" => $time_elapsed_secs, "products" => $arrResults);

print json_encode($arrJSONResult);