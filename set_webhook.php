<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/vendor/autoload.php');
$dotenv = \Dotenv\Dotenv::createImmutable((__DIR__));
$dotenv->safeLoad();

// تنظیم URL وب‌هوک با استفاده از دامنه Replit
$webhookUrl = "https://workspace.bejep92474.repl.co/index.php";

// تنظیم پارامترهای اضافی وب‌هوک (فقط پیام‌ها و پست‌های کانال را دریافت کن)
$allowedUpdates = json_encode(["message", "callback_query", "inline_query"]);

// توکن ربات تلگرام
$token = $_ENV['TELEGRAM_TOKEN'];

// لینک تنظیم وب‌هوک با پارامترهای اضافی
$apiUrl = "https://api.telegram.org/bot{$token}/setWebhook";

// پارامترهای مورد نیاز برای تنظیم وب‌هوک
$params = [
    'url' => $webhookUrl,
    'allowed_updates' => $allowedUpdates
];

// تنظیمات cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

// دریافت پاسخ
$response = curl_exec($ch);
curl_close($ch);

// نمایش نتیجه
echo "<h1>نتیجه تنظیم وب‌هوک:</h1>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";

// نمایش اطلاعات وب‌هوک فعلی
$webhookInfoUrl = "https://api.telegram.org/bot{$token}/getWebhookInfo";
$webhookInfo = file_get_contents($webhookInfoUrl);

echo "<h1>اطلاعات وب‌هوک فعلی:</h1>";
echo "<pre>";
print_r(json_decode($webhookInfo, true));
echo "</pre>";

// نمایش لینک ربات
echo "<h1>لینک ربات شما:</h1>";
$botInfo = json_decode(file_get_contents("https://api.telegram.org/bot{$token}/getMe"), true);
if ($botInfo['ok']) {
    $botUsername = $botInfo['result']['username'];
    echo "<a href='https://t.me/{$botUsername}' target='_blank'>t.me/{$botUsername}</a>";
}
?>