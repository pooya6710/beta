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

function api($method , $parameters)
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

// updated updated_at for matches without any moves
DB::rawQuery("UPDATE matches
SET updated_at = NOW()
WHERE status != 'finished'
  AND player2 IS NOT NULL
  AND updated_at IS NULL;
");

// select anonymous inactive match
$matches = DB::rawQuery("SELECT *
FROM matches
WHERE player2 IS NOT NULL
  AND updated_at <= NOW() - INTERVAL 2 MINUTE
  AND status != 'finished';

");
foreach($matches as $match){
    api('sendMessage' , ['chat_id' => 2122742198, 'text' => json_encode($match)]);

    if ($match['turn'] == 1){
        $loser_tg = $match['player1'];
        $winner_tg = $match['player2'];
        $winner = 2;
    }else{
        $loser_tg = $match['player2'];
        $winner_tg = $match['player1'];
        $winner = 1;
    }

    $loser_username = DB::table('users')->where('telegram_id' , $loser_tg)->select('username')->get()[0]['username'];
    $winner_username = DB::table('users')->where('telegram_id' , $winner_tg)->select('username')->get()[0]['username'];


    // loser
    DB::rawQuery(" UPDATE users_extra SET cups = GREATEST(cups - 3, 0) WHERE user_id = (SELECT id FROM users WHERE telegram_id = $loser_tg);");
    DB::rawQuery(" UPDATE users_extra SET doz_coin = GREATEST(doz_coin - 0.1, 0) WHERE user_id = (SELECT id FROM users WHERE telegram_id = $loser_tg);");
    DB::rawQuery("UPDATE users_extra SET matches = matches + 1 WHERE user_id = (SELECT id FROM users WHERE telegram_id = $loser_tg);");
    DB::rawQuery("UPDATE users_extra SET loses = loses + 1 WHERE user_id = (SELECT id FROM users WHERE telegram_id = $loser_tg);");
    DB::table('users')->where('telegram_id' , $loser_tg)->update(['step' => NULL]);


    $loser_extra = DB::rawQuery("SELECT cups, doz_coin FROM users_extra WHERE user_id = ( SELECT id FROM users WHERE telegram_id = $loser_tg);")[0];
    $loser_cups = $loser_extra['cups'];
    $loser_dozCoin = $loser_extra['doz_coin'];

    api("sendMessage" , ['text' => "❌ بازی شما با کاربر /$winner_username به دلیل عدم فعالیت شما به اتمام رسید و تعداد 3 🏆 و 0.1 دوزکوین از شما کسر گردید .". PHP_EOL . PHP_EOL .
        "موجودی  جدید🏆 : $loser_cups عدد " . PHP_EOL . "موجودیِ جدید دوزکوین : $loser_dozCoin دوزکوین" ,
        'reply_markup' =>[
            'keyboard' => $keyboard->get('main.home'),
            'resize_keyboard' => true,
        ] ,
        'chat_id' => $loser_tg]);



    // winner
    DB::rawQuery("UPDATE users_extra SET cups = cups + 5 WHERE user_id = (SELECT id FROM users WHERE telegram_id = $winner_tg);");
    DB::rawQuery("UPDATE users_extra SET doz_coin = doz_coin + 0.2 WHERE user_id = (SELECT id FROM users WHERE telegram_id = $winner_tg);");
    DB::rawQuery("UPDATE users_extra SET matches = matches + 1 WHERE user_id = (SELECT id FROM users WHERE telegram_id = $winner_tg);");
    DB::rawQuery("UPDATE users_extra SET wins = wins + 1 WHERE user_id = (SELECT id FROM users WHERE telegram_id = $winner_tg);");
    DB::table('users')->where('telegram_id' , $winner_tg)->update(['step' => NULL]);


    $winner_extra = DB::rawQuery("SELECT cups, doz_coin FROM users_extra WHERE user_id = ( SELECT id FROM users WHERE telegram_id = $winner_tg);")[0];
    $winner_cups = $winner_extra['cups'];
    $winner_dozCoin = $winner_extra['doz_coin'];

    
    api("sendMessage" , ['text' => "❌ بازی شما با کاربر /$loser_username به دلیل عدم فعالیت طرف مقابل به اتمام رسید و تعداد 5 🏆 و 0.2 دوزکوین دریافت کردید .". PHP_EOL . PHP_EOL .
        "موجودی  جدید🏆 : $winner_cups عدد " . PHP_EOL . "موجودیِ جدید دوزکوین : $winner_dozCoin دوزکوین" ,
        'reply_markup' =>[
            'keyboard' => $keyboard->get('main.home'),
            'resize_keyboard' => true,
        ] ,
        'chat_id' => $winner_tg]);


    DB::rawQuery("UPDATE matches
SET status = 'finished',
    winner_id = $winner;
");

}




//var_dump($x);