<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/vendor/autoload.php');
$dotenv = \Dotenv\Dotenv::createImmutable((__DIR__));
$dotenv->safeLoad();
include(__DIR__ . "/system/Loader.php");
date_default_timezone_set('Asia/Tehran');

use application\controllers\TelegramClass as Telegram;
use application\controllers\UserController as User;
use application\controllers\StepController as Step;

// توکن ربات تلگرام
$token = $_ENV['TELEGRAM_TOKEN'];

// تنظیم آخرین آپدیت دریافت شده
$lastUpdateId = 0;

echo "ربات تلگرام در حال اجرا...\n";
echo "برای توقف، کلید Ctrl+C را فشار دهید.\n\n";

// حلقه اصلی برای دریافت پیام‌ها
while (true) {
    // دریافت آپدیت‌ها از تلگرام
    $updates = getUpdates($token, $lastUpdateId);
    
    if (!$updates || !isset($updates['result']) || empty($updates['result'])) {
        // اگر آپدیتی نبود، 1 ثانیه صبر کن و دوباره تلاش کن
        sleep(1);
        continue;
    }
    
    // پردازش هر آپدیت
    foreach ($updates['result'] as $update) {
        // به‌روزرسانی آخرین آی‌دی آپدیت
        $lastUpdateId = $update['update_id'] + 1;
        
        // تبدیل آپدیت به شیء برای استفاده در کلاس‌های موجود
        $updateObj = json_decode(json_encode($update));
        
        // ایجاد نمونه‌های مورد نیاز
        $telegram = new Telegram($updateObj);
        $user = new User($updateObj);
        $step = new Step($updateObj);
        
        // بررسی نوع پیام و پردازش آن
        if (isset($update['message'])) {
            processMessage($update, $telegram, $user, $step);
        } elseif (isset($update['callback_query'])) {
            processCallbackQuery($update, $telegram, $user, $step);
        }
        
        // چاپ اطلاعات آپدیت
        echo "آپدیت جدید دریافت شد (ID: {$update['update_id']})\n";
        if (isset($update['message']['text'])) {
            echo "پیام: {$update['message']['text']}\n";
            echo "از کاربر: {$update['message']['from']['first_name']} (ID: {$update['message']['from']['id']})\n";
        } elseif (isset($update['callback_query'])) {
            echo "کالبک: {$update['callback_query']['data']}\n";
            echo "از کاربر: {$update['callback_query']['from']['first_name']} (ID: {$update['callback_query']['from']['id']})\n";
        }
        echo "-------------------\n";
    }
}

/**
 * دریافت آپدیت‌ها از API تلگرام
 */
function getUpdates($token, $offset = 0) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates";
    $params = [
        'offset' => $offset,
        'timeout' => 30 // زمان انتظار طولانی برای کاهش درخواست‌ها
    ];
    
    $url .= '?' . http_build_query($params);
    
    $response = @file_get_contents($url);
    if ($response === false) {
        echo "خطا در دریافت آپدیت‌ها\n";
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * پردازش پیام‌های دریافتی
 */
function processMessage($update, $telegram, $user, $step) {
    $message = $update['message'];
    $text = $message['text'] ?? '';
    
    // اگر پیام با دستور /start شروع شود
    if (strpos($text, '/start') === 0) {
        // بررسی کاربر جدید یا موجود
        if (!$user->userData()) {
            // کاربر جدید
            $telegram->sendMessage("سلام! به ربات خوش آمدید. لطفاً نام کاربری خود را تنظیم کنید.")->send();
            $step->set('set_username');
        } else {
            // کاربر موجود
            $first_name = $message['from']['first_name'] ?? 'کاربر';
            $telegram->sendMessage("سلام {$first_name}! خوش برگشتی.")->send();
        }
    }
    // اگر کاربر در مرحله تنظیم نام کاربری است
    elseif ($step->get() == 'set_username') {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9]{4,11}$/', $text)) {
            // تنظیم نام کاربری
            $telegram->sendMessage("نام کاربری {$text} با موفقیت تنظیم شد!")->send();
            $step->clear();
        } else {
            // نام کاربری نامعتبر
            $telegram->sendMessage("نام کاربری باید با حروف انگلیسی شروع شود و حداقل 5 و حداکثر 12 کاراکتر باشد.")->send();
        }
    }
    // سایر پیام‌ها
    else {
        $telegram->sendMessage("پیام شما دریافت شد: {$text}")->send();
    }
}

/**
 * پردازش کالبک‌های دریافتی
 */
function processCallbackQuery($update, $telegram, $user, $step) {
    $callback_query = $update['callback_query'];
    $callback_data = $callback_query['data'];
    
    // پاسخ به کالبک
    $telegram->answerCallbackQuery($callback_query['id'], "دریافت شد: {$callback_data}");
    
    // پردازش بر اساس داده کالبک
    if ($callback_data == 'i_join') {
        $telegram->sendMessage("ممنون از اینکه کانال‌ها را دنبال کردید!")->send();
    } else {
        $telegram->sendMessage("دکمه {$callback_data} فشرده شد.")->send();
    }
}
?>