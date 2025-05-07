<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Application\Model\DB;
use application\controllers\KeyboardController as Keyboard;


require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once(dirname(__DIR__) . '/application/Model/Model.php');
require_once(dirname(__DIR__) . '/application/Model/DB.php');
$dotenv = \Dotenv\Dotenv::createImmutable((dirname(__DIR__) . '/'));
$dotenv->safeLoad();
include(dirname(__DIR__) . "/system/Loader.php");

$keyboard = new Keyboard();

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


header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data (JSON payload)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
}else
    exit();


$player_column = $data['player_number'] == 1 ? 'player1_hash' : 'player2_hash';


$match = DB::table('matches')
    ->where($player_column, $data['player_hash'])
    ->select()
    ->get()[0];

$player1 = $match['player1'];
$player2 = $match['player2'];
api('sendMessage' , ['chat_id' => $player1 , 'text' => "بازی شما مساوی شد."]);
api('sendMessage' , ['chat_id' => $player2 , 'text' => "بازی شما مساوی شد."]);

api('sendMessage' , ['chat_id' => $match['player1'] , 'text' => 'بازی شما به اتمام رسید 🎮
چه کاری میتونم برات انجام بدم ؟ 👇' , 'reply_markup' =>[
    'keyboard' => $keyboard->get('main.home'),
    'resize_keyboard' => true,
]
]);
api('sendMessage' , ['chat_id' => $match['player2'] , 'text' => 'بازی شما به اتمام رسید 🎮
چه کاری میتونم برات انجام بدم ؟ 👇' , 'reply_markup' =>[
    'keyboard' => $keyboard->get('main.home'),
    'resize_keyboard' => true,
]
]);
DB::table('users')->where('telegram_id' , $player1)->update(['step' => NULL]);
DB::table('users')->where('telegram_id' , $player2)->update(['step' => NULL]);