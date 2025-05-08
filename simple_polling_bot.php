<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Application\Model\DB;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// جلوگیری از timeout در اجرای طولانی
set_time_limit(0);
date_default_timezone_set('Asia/Tehran');

// فایل ذخیره آخرین آپدیت
$lastUpdateIdFile = __DIR__ . '/last_update_id.txt';

// برای شروع از ابتدا، 0 را در فایل قرار دهید
if (!file_exists($lastUpdateIdFile)) {
    file_put_contents($lastUpdateIdFile, "0");
}

// خواندن آخرین آپدیت
$lastUpdateId = (int)file_get_contents($lastUpdateIdFile);

echo "ربات ساده در حال اجرا با Long Polling...\n";
echo "آخرین آپدیت: {$lastUpdateId}\n";
echo "برای توقف، کلید Ctrl+C را فشار دهید.\n\n";

// حلقه اصلی برای دریافت پیام‌ها
while (true) {
    try {
        // دریافت آپدیت‌ها با CURL
        $updates = getUpdatesViaCurl($_ENV['TELEGRAM_TOKEN'], $lastUpdateId);
        
        // بررسی وجود آپدیت‌های جدید
        if (isset($updates['result']) && !empty($updates['result'])) {
            foreach ($updates['result'] as $update) {
                // به روزرسانی شناسه آخرین آپدیت
                $newUpdateId = $update['update_id'] + 1;
                file_put_contents($lastUpdateIdFile, $newUpdateId);
                
                echo "دریافت آپدیت جدید (ID: {$update['update_id']})\n";
                
                // فقط پردازش پیام‌های متنی
                if (isset($update['message']) && isset($update['message']['text'])) {
                    $text = $update['message']['text'];
                    $chat_id = $update['message']['chat']['id'];
                    $username = isset($update['message']['from']['username']) ? $update['message']['from']['username'] : 'بدون نام کاربری';
                    
                    echo "پیام دریافتی از {$username}: {$text}\n";
                    
                    // پاسخ به دستور /start
                    if ($text === '/start') {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "سلام! ربات کار می‌کند. 🙌");
                    }
                    // پاسخ به دستور /cancel
                    elseif ($text === '/cancel') {
                        // پاک کردن همه بازی‌های در انتظار کاربر
                        $deleted = DB::table('matches')->where(['player1' => $chat_id, 'status' => 'pending'])->delete();
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ جستجوی بازیکن لغو شد.");
                    }
                    // پاسخ به هر پیام دیگر
                    else {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "پیام شما: {$text}\n\nبرای لغو جستجوی بازیکن، دستور /cancel را ارسال کنید.");
                    }
                }
            }
        } else {
            // هیچ آپدیت جدیدی وجود ندارد
            echo ".";
        }
    } catch (Exception $e) {
        echo "\nخطا: " . $e->getMessage() . "\n";
    }
    
    // انتظار کوتاه قبل از درخواست بعدی برای کاهش بار سرور
    sleep(1);
}

/**
 * ارسال پیام به کاربر
 */
function sendMessage($token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    echo "پیام ارسال شد: {$text}\n";
    return json_decode($result, true);
}

/**
 * دریافت آپدیت‌ها با CURL
 */
function getUpdatesViaCurl($token, $offset = 0) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates";
    $params = [
        'offset' => $offset,
        'timeout' => 1,
        'limit' => 10,
        'allowed_updates' => json_encode(['message'])
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
?>