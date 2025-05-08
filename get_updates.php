<?php
/**
 * اسکریپت ساده برای بررسی آپدیت‌های تلگرام
 */
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$token = $_ENV['TELEGRAM_TOKEN'];

// شناسه آخرین آپدیت را دریافت کنید
$lastUpdateId = 0;
if (file_exists('last_update_id.txt')) {
    $lastUpdateId = (int)file_get_contents('last_update_id.txt');
}

// دریافت آپدیت‌ها از تلگرام
$url = "https://api.telegram.org/bot{$token}/getUpdates?offset={$lastUpdateId}&limit=10";
$response = file_get_contents($url);
$updates = json_decode($response, true);

echo "<h1>آخرین آپدیت‌های دریافتی از تلگرام</h1>";

if ($updates && $updates['ok'] && !empty($updates['result'])) {
    echo "<pre>";
    foreach ($updates['result'] as $update) {
        echo "آپدیت ID: " . $update['update_id'] . "\n";
        
        // ذخیره آخرین آپدیت + 1
        $lastUpdateId = $update['update_id'] + 1;
        file_put_contents('last_update_id.txt', $lastUpdateId);
        
        if (isset($update['message'])) {
            echo "پیام از: " . $update['message']['from']['first_name'] . "\n";
            echo "آیدی چت: " . $update['message']['chat']['id'] . "\n";
            
            if (isset($update['message']['text'])) {
                echo "متن پیام: " . $update['message']['text'] . "\n";
                
                // اگر پیام /cancel بود، بازی در انتظار را حذف کن
                if ($update['message']['text'] == '/cancel') {
                    try {
                        include(__DIR__ . "/system/Loader.php");
                        $player_id = $update['message']['from']['id'];
                        
                        // پاک کردن همه بازی‌های در انتظار کاربر
                        $deleted = \Application\Model\DB::table('matches')
                            ->where(['player1' => $player_id, 'status' => 'pending'])
                            ->delete();
                        
                        // ارسال پیام تأیید
                        $chat_id = $update['message']['chat']['id'];
                        $reply = "✅ جستجوی بازیکن لغو شد.";
                        
                        $sendUrl = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($reply);
                        file_get_contents($sendUrl);
                        
                        echo "وضعیت: بازی در انتظار حذف شد و پیام تأیید ارسال شد" . ($deleted ? " (✓)" : " (X)") . "\n";
                    } catch (Exception $e) {
                        echo "خطا: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        echo "--------------------\n";
    }
    echo "</pre>";
    
    echo "<p>آخرین آپدیت ذخیره شده: {$lastUpdateId}</p>";
} else {
    echo "<p>هیچ آپدیت جدیدی وجود ندارد یا خطایی رخ داده است.</p>";
}
?>

<p><a href="?refresh=1">بررسی دوباره آپدیت‌ها</a></p>