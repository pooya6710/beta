<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$token = $_ENV['TELEGRAM_TOKEN'];

// آیدی چت کاربر را اینجا وارد کنید (برای ارسال پیام آزمایشی)
$chat_id = ""; // آیدی چت خود را اینجا قرار دهید

// ارسال پیام آزمایشی
$text = "پیام آزمایشی: ربات کار می‌کند! 🎉";
$url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($text);

$response = file_get_contents($url);
$result = json_decode($response, true);

echo "نتیجه ارسال پیام:<br>";
print_r($result);

if ($result['ok']) {
    echo "<br>پیام با موفقیت ارسال شد ✅";
} else {
    echo "<br>خطا در ارسال پیام ❌";
}
?>