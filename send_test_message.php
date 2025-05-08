<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/vendor/autoload.php');
$dotenv = \Dotenv\Dotenv::createImmutable((__DIR__));
$dotenv->safeLoad();

// توکن ربات تلگرام
$token = $_ENV['TELEGRAM_TOKEN'];

// آی‌دی چت کاربر (این مقدار را باید با آی‌دی چت خود جایگزین کنید)
$chatId = "YOUR_CHAT_ID";

// متن پیام
$message = "سلام! این یک پیام تست از ربات است.";

// لینک ارسال پیام به API تلگرام
$apiUrl = "https://api.telegram.org/bot{$token}/sendMessage";

// پارامترهای ارسال پیام
$params = [
    'chat_id' => $chatId,
    'text' => $message
];

// تنظیمات cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

// دریافت پاسخ
$response = curl_exec($ch);
curl_close($ch);

// نمایش نتیجه
echo "<h1>نتیجه ارسال پیام:</h1>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>