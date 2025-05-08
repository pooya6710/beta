<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/vendor/autoload.php');
$dotenv = \Dotenv\Dotenv::createImmutable((__DIR__));
$dotenv->safeLoad();

// توکن ربات تلگرام
$token = $_ENV['TELEGRAM_TOKEN'];

// لینک دریافت آپدیت‌ها
$apiUrl = "https://api.telegram.org/bot{$token}/getUpdates";

// ارسال درخواست به API تلگرام
$response = file_get_contents($apiUrl);
$updates = json_decode($response, true);

// نمایش نتیجه
echo "<h1>پیام‌های دریافتی از ربات:</h1>";
echo "<pre>";
print_r($updates);
echo "</pre>";
?>