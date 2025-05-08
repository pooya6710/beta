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
        'timeout' => 5,
        'limit' => 100
    ];
    
    $url .= '?' . http_build_query($params);
    
    $response = @file_get_contents($url);
    if ($response === false) {
        return false;
    }
    
    return json_decode($response, true);
}

echo "در حال دریافت آپدیت‌های اخیر...\n";

// دریافت آپدیت‌ها از تلگرام
$updates = getUpdates($_ENV['TELEGRAM_TOKEN'], 0);

if ($updates && isset($updates['result']) && !empty($updates['result'])) {
    echo "تعداد آپدیت‌های دریافتی: " . count($updates['result']) . "\n\n";
    
    foreach ($updates['result'] as $update) {
        echo "آپدیت ID: " . $update['update_id'] . "\n";
        
        if (isset($update['message'])) {
            $user_id = $update['message']['from']['id'];
            $chat_id = $update['message']['chat']['id'];
            $username = $update['message']['from']['username'] ?? 'بدون نام کاربری';
            $first_name = $update['message']['from']['first_name'] ?? '';
            $last_name = $update['message']['from']['last_name'] ?? '';
            $text = $update['message']['text'] ?? '[بدون متن]';
            
            echo "نوع: پیام\n";
            echo "Telegram ID: {$user_id}\n";
            echo "Chat ID: {$chat_id}\n";
            echo "Username: {$username}\n";
            echo "Name: {$first_name} {$last_name}\n";
            echo "Text: {$text}\n";
        } else if (isset($update['callback_query'])) {
            $user_id = $update['callback_query']['from']['id'];
            $username = $update['callback_query']['from']['username'] ?? 'بدون نام کاربری';
            $data = $update['callback_query']['data'];
            
            echo "نوع: callback query\n";
            echo "Telegram ID: {$user_id}\n";
            echo "Username: {$username}\n";
            echo "Data: {$data}\n";
        }
        
        echo "-------------------\n";
    }
} else {
    echo "هیچ آپدیتی دریافت نشد یا خطایی رخ داده است.\n";
}