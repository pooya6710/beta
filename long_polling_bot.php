<?php
/**
 * ربات ساده Long Polling فقط با قابلیت لغو بازی
 */
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
include(__DIR__ . "/system/Loader.php");
date_default_timezone_set('Asia/Tehran');

// تنظیم آخرین آپدیت دریافت شده
$lastUpdateIdFile = __DIR__ . '/last_update_id.txt';
if (file_exists($lastUpdateIdFile)) {
    $lastUpdateId = (int)file_get_contents($lastUpdateIdFile);
} else {
    $lastUpdateId = 0;
}

// ذخیره آخرین شناسه پردازش شده جدید
if (!file_exists($lastUpdateIdFile)) {
    file_put_contents($lastUpdateIdFile, "603369409");
    $lastUpdateId = 603369409; // آخرین آپدیت شناسایی شده فعلی
}

echo "ربات تلگرام اصلی (نسخه کمینه) در حال اجرا با روش Long Polling...\n";
echo "آخرین آپدیت شروع: {$lastUpdateId}\n";
echo "برای توقف، کلید Ctrl+C را فشار دهید.\n\n";

// حلقه اصلی برای دریافت پیام‌ها
while (true) {
    // دریافت آپدیت‌ها از تلگرام
    $updates = getUpdatesViaFopen($_ENV['TELEGRAM_TOKEN'], $lastUpdateId);
    
    if (!$updates || !isset($updates['result']) || empty($updates['result'])) {
        // اگر آپدیتی نبود، کمی صبر کن و دوباره تلاش کن
        sleep(1);
        echo ".";
        continue;
    }
    
    // پردازش هر آپدیت
    foreach ($updates['result'] as $update) {
        // به‌روزرسانی آخرین آی‌دی آپدیت و ذخیره در فایل
        $lastUpdateId = $update['update_id'] + 1;
        file_put_contents($lastUpdateIdFile, $lastUpdateId);
        
        echo "\nآپدیت جدید (ID: {$update['update_id']})\n";
        
        // پردازش پیام‌های متنی
        if (isset($update['message']) && isset($update['message']['text'])) {
            $text = $update['message']['text'];
            $chat_id = $update['message']['chat']['id'];
            $user_id = $update['message']['from']['id'];
            $username = isset($update['message']['from']['username']) ? 
                        $update['message']['from']['username'] : 'بدون نام کاربری';
            
            echo "پیام از {$username}: {$text}\n";
            
            // پردازش دستور /cancel
            if ($text === '/cancel') {
                echo "دستور cancel دریافت شد - در حال حذف بازی‌های در انتظار...\n";
                
                // حذف بازی‌های در انتظار
                try {
                    $deleted = \Application\Model\DB::table('matches')
                        ->where(['player1' => $user_id, 'status' => 'pending'])
                        ->delete();
                    
                    $response_text = "✅ جستجوی بازیکن لغو شد.";
                    
                    // ارسال پاسخ
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                    echo "پاسخ ارسال شد: {$response_text}\n";
                } catch (Exception $e) {
                    echo "خطا: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در لغو جستجو: " . $e->getMessage());
                }
            }
            
            // پاسخ به دکمه بازی با ناشناس
            else if (strpos($text, 'بازی با ناشناس') !== false) {
                try {
                    // ارسال پیام در حال یافتن بازیکن
                    $response_text = "در حال یافتن بازیکن 🕔\n\nبرای لغو جستجو، دستور /cancel را ارسال کنید.";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                    echo "پاسخ ارسال شد: {$response_text}\n";
                    
                    // ثبت در پایگاه داده بازی جدید در وضعیت pending
                    $helper = new application\controllers\HelperController();
                    \Application\Model\DB::table('matches')->insert([
                        'player1' => $user_id, 
                        'player1_hash' => $helper->Hash(), 
                        'type' => 'anonymous'
                    ]);
                    
                    echo "بازی جدید در وضعیت pending ایجاد شد\n";
                } catch (Exception $e) {
                    echo "خطا در ایجاد بازی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در ایجاد بازی: " . $e->getMessage());
                }
            }
            
            // پاسخ به دستور /start
            else if (strpos($text, '/start') === 0) {
                $first_name = isset($update['message']['from']['first_name']) ? $update['message']['from']['first_name'] : 'کاربر';
                $response_text = "سلااام {$first_name} عزیززز به ربات بازی ما خوشومدییی❤️‍🔥\n\nقراره اینجا کلی خوشبگذره بهت😼\n\nبا افراد ناشناس بازی کنی و دوست پیدا کنی 😁\n\nبرای لغو جستجوی بازیکن، دستور /cancel را ارسال کنید.";
                
                // ارسال پاسخ
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                echo "پاسخ ارسال شد: {$response_text}\n";
                
                // ارسال مجدد منوی اصلی - اختیاری
                try {
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                            [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                            [['text' => '👤 حساب کاربری'], ['text' => '🏆نفرات برتر•']],
                            [['text' => '• پشتیبانی👨‍💻'], ['text' => '⁉️راهنما •']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    $url = "https://api.telegram.org/bot{$_ENV['TELEGRAM_TOKEN']}/sendMessage";
                    $params = [
                        'chat_id' => $chat_id,
                        'text' => '🎮 منوی اصلی:',
                        'reply_markup' => $keyboard
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $result = curl_exec($ch);
                    curl_close($ch);
                    
                    echo "کیبورد ارسال شد!\n";
                } catch (Exception $e) {
                    echo "خطا در ارسال کیبورد: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

/**
 * دریافت آپدیت‌ها از API تلگرام
 */
function getUpdatesViaFopen($token, $offset = 0) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates";
    $params = [
        'offset' => $offset,
        'timeout' => 1,
        'limit' => 10,
        'allowed_updates' => json_encode(["message"])
    ];
    
    $url .= '?' . http_build_query($params);
    
    $response = @file_get_contents($url);
    if ($response === false) {
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * ارسال پیام به کاربر
 */
function sendMessage($token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $params = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    
    $url .= '?' . http_build_query($params);
    return file_get_contents($url);
}
?>