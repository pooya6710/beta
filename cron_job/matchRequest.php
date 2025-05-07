<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Application\Model\DB;
use application\controllers\TelegramClass as Telegram;

require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once(dirname(__DIR__) . '/application/Model/Model.php');
require_once(dirname(__DIR__) . '/application/Model/DB.php');
$dotenv = \Dotenv\Dotenv::createImmutable((dirname(__DIR__) . '/'));
$dotenv->safeLoad();



DB::rawQuery("DELETE FROM matches
WHERE player2 IS NULL
  AND player2_hash IS NULL
  AND type = 'in_bot'
  AND status = 'pending'
  AND created_at < NOW() - INTERVAL 2 MINUTE;
");