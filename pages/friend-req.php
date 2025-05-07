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
function rm($input)
{
    // Use preg_replace to remove all characters except letters and numbers
    $cleaned = preg_replace('/[^A-Za-z0-9]/', '', $input);
    return $cleaned;
}

//$_POST = ['player_number' => '1', 'hash' => 'wBjeGsaQN2OXUEVtqcnakWGLpP8Xkxj4dVJgCGFJejQPVZ4KZoLmpYbpfsb0rwZnmbWDq3qqHuICjbG4MdSRTxAPnKiMgccgeXKQq0YzDS64R69ex4Mi67sdCgo1GZ8k'];
if (!in_array($_POST['player_number'],[1,2])) {exit('no such a player');}
$friendPLayerNumber = $_POST['player_number'] == 1 ? 2 : 1;

$q = "SELECT matches.id,matches.player1,matches.player2, u1.username AS player1_name, u2.username AS player2_name 
      FROM matches 
      JOIN users u1 ON matches.player1 = u1.telegram_id 
      JOIN users u2 ON matches.player2 = u2.telegram_id 
      WHERE matches.player" . $_POST['player_number'] . "_hash = ?";

$match = DB::rawQuery($q, [rm($_POST['hash'])])[0];


$data = [
//    "update_id" => 173012869,
    "message" => [
        "message_id" => 6305,
        "from" => [
            "id" => $match["player{$_POST['player_number']}"],
            "is_bot" => false,
            "first_name" => "404",
            "username" => "404",
            "language_code" => "en"
        ],
        "chat" => [
            "id" => $match["player{$_POST['player_number']}"],
            "first_name" => "404",
            "username" => "E404",
            "type" => "private"
        ],
        "date" => 1733149589,
        "text" => ("httpreqfriend_".$match["player{$friendPLayerNumber}_name"])
    ]
];

$ch = curl_init('https://'.$_SERVER['HTTP_HOST'].'/XO/index.php');

// Configure cURL options
curl_setopt($ch, CURLOPT_POST, true); // Send as POST
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json', // Specify JSON content
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Get the response as a string
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // JSON encode the data

// Execute cURL request
$response = curl_exec($ch);

//// Check for errors
//if (curl_errno($ch)) {
//    echo "cURL Error: " . curl_error($ch);
//} else {
//    echo "Response: " . $response;
//}

// Close cURL resource
curl_close($ch);