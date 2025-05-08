<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// نصب و تنظیم webhook
$token = $_ENV['TELEGRAM_TOKEN'];
$webhook_url = "https://YOUR_REPLIT_URL_HERE/index.php"; // آدرس ریپلیت خود را اینجا بگذارید

$url = "https://api.telegram.org/bot{$token}/setWebhook?url={$webhook_url}";
$response = file_get_contents($url);

echo "نتیجه تنظیم webhook:<br>";
print_r(json_decode($response, true));
?>