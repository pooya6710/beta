<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// تابع دریافت آپدیت‌ها از تلگرام
function getUpdates($token, $offset = 0) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates";
    $params = [
        'offset' => $offset,
        'timeout' => 30,
        'limit' => 10
    ];
    
    $url .= '?' . http_build_query($params);
    
    $response = @file_get_contents($url);
    if ($response === false) {
        return false;
    }
    
    return json_decode($response, true);
}

// تابع ارسال پیام به کاربر
function sendMessage($token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($params)
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    return $response !== false;
}

echo "در حال دریافت اطلاعات تلگرام آیدی...\n";

// دریافت آپدیت‌ها از تلگرام
$updates = getUpdates($_ENV['TELEGRAM_TOKEN'], 0);

if ($updates && isset($updates['result']) && !empty($updates['result'])) {
    foreach ($updates['result'] as $update) {
        if (isset($update['message'])) {
            $user_id = $update['message']['from']['id'];
            $chat_id = $update['message']['chat']['id'];
            $username = $update['message']['from']['username'] ?? 'بدون نام کاربری';
            $first_name = $update['message']['from']['first_name'] ?? '';
            $last_name = $update['message']['from']['last_name'] ?? '';
            
            echo "اطلاعات کاربر:\n";
            echo "Telegram ID: {$user_id}\n";
            echo "Chat ID: {$chat_id}\n";
            echo "Username: {$username}\n";
            echo "First Name: {$first_name}\n";
            echo "Last Name: {$last_name}\n";
            echo "-------------------\n";
            
            // ارسال پیام به کاربر با اطلاعات تلگرام آیدی
            $message = "اطلاعات شما:\n";
            $message .= "Telegram ID: {$user_id}\n";
            $message .= "Chat ID: {$chat_id}\n";
            $message .= "Username: {$username}\n";
            
            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
        }
    }
} else {
    echo "هیچ آپدیتی دریافت نشد یا خطایی رخ داده است.\n";
}