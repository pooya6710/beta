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

DB::rawQuery('DELETE FROM friend_requests where created_at < NOW() - INTERVAL 1 DAY;');
