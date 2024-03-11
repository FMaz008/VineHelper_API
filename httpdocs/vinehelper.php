<?php

//Usage:
//francoismazerolle.ca?data={'api_version':3, 'country':'ca', 'arr_asin'=>["asin1", "asin2", "asin3"]}

//return a JSON array of the URL and 0 for no fees, 1 for fees, null for more votes required (not implemented)


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
require ("../vinehelper_config.php");

header('Content-Type: application/json; charset=utf-8');


//Sketchy, will clean up once I get something working
if(isset($_GET['data']))
    $_POST['data'] = $_GET['data'];
    
//Check if we have a valid request
if(!isset($_POST['data'])){
    print json_encode(array('error'=>'POST data missing'));
    die();//quit
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


if(!isset($arrJSON["action"])){
    $arrJSON["action"]= "getinfo";
}



//Establish the MySQL connection
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try{
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_BASE);
}
catch(Exception $e){
    $arrJSONResult = array("api_version" => 4, "crunch_time" => -1, "products" => [], "current_time"=>date("Y-m-d H:i:s"), "added"=>0, "notification"=>["Server at capacity."]);
    print json_encode($arrJSONResult);
    die();
}



switch($arrJSON["action"]){
    case "get_uuid":
        require("../vinehelper_getuuid.php");
        break;
    case "validate_uuid":
        require("../vinehelper_validateuuid.php");
        break;
    case "report_order":
        require("../vinehelper_recordorder.php");
        break;
    case "report_etv":
        require("../vinehelper_recordetv.php");
        break;
    case "report_fee":
        require("../vinehelper_recordfee.php");
        break;
    case "save_hidden_list":
        require("../vinehelper_savehiddenlist.php");
        break;
    case "getinfo":
        require("../vinehelper_getinfo.php");
        break;
     case "getinfoonly":
        require("../vinehelper_getinfoonly.php");
        break;
	case "date":
        print json_encode(array('date'=>date("Y-m-d H:i:s")));
        die();
    default:
        print json_encode(array('error'=>'Action type unsupported.'));
        die();//quit
}





