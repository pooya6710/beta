<?php




use Application\Model\DB;



require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once(dirname(__DIR__) . '/application/Model/Model.php');
require_once(dirname(__DIR__) . '/application/Model/DB.php');
$dotenv = \Dotenv\Dotenv::createImmutable((dirname(__DIR__) . '/'));
$dotenv->safeLoad();
include(dirname(__DIR__) . "/system/Loader.php");


$api_url = 'https://api.telegram.org/bot' . $_ENV['TELEGRAM_TOKEN'] . "/";
function api($method , $parameters): bool|string
{
    global $api_url;
    if (!$parameters) {
        $parameters = array();
    }
    $parameters["method"] = $method;
    $handle = curl_init($api_url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    return curl_exec($handle);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data (JSON payload)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
}else
    exit();


$user_telegram_id = DB::table('users')->where('id' , $data['refere_id'])->select('telegram_id')->get()[0]['telegram_id'];
api('sendMessage' , ['chat_id' => $user_telegram_id , 'text' => '🤩 مقدار 1.5 دلتاکوین به حساب کاربری شما به دلیل اولین برد زیر مجموعه شما به شما اضافه گردید . ']);
