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



// send To Player 1
$winner_telegram_id = $match['winner_id'] == 1 ? $match['player1'] : $match['player2'];
$loser_telegram_id = $match['winner_id'] == 1 ? $match['player2'] : $match['player1'];

if ($data['collusion']){
    api('sendMessage' , ['chat_id' => $winner_telegram_id , 'text' => '😡 شما به دلیل تبانی در بردن امتیاز نمیگیرید .']);
}else{
    $loser_username = DB::rawQuery("SELECT username FROM users WHERE telegram_id = $loser_telegram_id ")[0]['username'];

    $winner_extra = DB::rawQuery("SELECT cups, doz_coin FROM users_extra WHERE user_id = ( SELECT id FROM users WHERE telegram_id = $winner_telegram_id);")[0];
    $winner_cups = $winner_extra['cups'];
    $winner_dozCoin = $winner_extra['doz_coin'];

    api('sendMessage' , ['chat_id' => $winner_telegram_id , 'text' => "ایول🔥 شما رقیبتون /$loser_username رو بردید و مقدار 5 🏆 و 0.2 دوز کوین به موجودی شما اضافه شد." . PHP_EOL . PHP_EOL . "موجودی  جدید🏆 : $winner_cups عدد" . PHP_EOL . "موجودیِ جدید دوزکوین : $winner_dozCoin دوزکوین"]);

}

$winner_username = DB::rawQuery("SELECT username FROM users WHERE telegram_id = $winner_telegram_id ")[0]['username'];
$loser_extra = DB::rawQuery("SELECT cups, doz_coin FROM users_extra WHERE user_id = ( SELECT id FROM users WHERE telegram_id = $loser_telegram_id);")[0];
$loser_cups = $loser_extra['cups'];
$loser_dozCoin = $loser_extra['doz_coin'];

api('sendMessage' , ['chat_id' => $loser_telegram_id , 'text' => "اوپس💔 شما به رقیب خود /$winner_username باختید و مقدار 3 🏆 و 0.1 دوز کوین از موجودی شما کسر گردید." . PHP_EOL . PHP_EOL . "موجودی  جدید🏆 : $loser_cups عدد" . PHP_EOL . "موجودیِ جدید دوزکوین : $loser_dozCoin دوزکوین"]);

api('sendMessage' , ['chat_id' => $match['player1'] , 'text' => '⚠بازی شما به اتمام رسید 🎮

از آنجاییکه ربات تازه شروع به کار کرده است چنانچه مشکلی در روند ربات مشاهده کردید به پشتیبانی @Doz_Sup اطلاع دهید .

چه کاری میتونم برات انجام بدم ؟ 👇' , 'reply_markup' =>[
    'keyboard' => $keyboard->get('main.home'),
    'resize_keyboard' => true,
]
]);
DB::table('users')->where('telegram_id' , $match['player1'])->update(['step' => NULL]);
// send To Player 2
api('sendMessage' , ['chat_id' => $match['player2'] , 'text' => 'بازی شما به اتمام رسید 🎮

از آنجاییکه ربات تازه شروع به کار کرده است چنانچه مشکلی در روند ربات مشاهده کردید به پشتیبانی @Doz_Sup اطلاع دهید .

چه کاری میتونم برات انجام بدم ؟ 👇' , 'reply_markup' =>[
    'keyboard' => $keyboard->get('main.home'),
    'resize_keyboard' => true,
]
]);
DB::table('users')->where('telegram_id' , $match['player2'])->update(['step' => NULL]);

