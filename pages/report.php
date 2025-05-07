<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Application\Model\DB;

require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once(dirname(__DIR__) . '/application/Model/Model.php');
require_once(dirname(__DIR__) . '/application/Model/DB.php');
$dotenv = \Dotenv\Dotenv::createImmutable((dirname(__DIR__) . '/'));
$dotenv->safeLoad();

function sendApi($method , $parameters): bool|string
{
    if (!$parameters) {
        $parameters = array();
    }
    $parameters["method"] = $method;
    $handle = curl_init('https://api.telegram.org/bot' . $_ENV['TELEGRAM_TOKEN'] . "/");
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($handle, CURLOPT_TIMEOUT, 60);
    curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    return curl_exec($handle);
}

//sendApi('sendMessage' , ['chat_id' => $_ENV['reportLog'] , 'text' =>  json_encode($_POST)]);
sendApi('sendMessage' , ['chat_id' => $_ENV['reportLog'] , 'text' =>  '⚠️ گزارش جدید برای : ' .
    $_POST['player_number'] . PHP_EOL . PHP_EOL . "👥 نام کاربری : " . $_POST['username']
]);