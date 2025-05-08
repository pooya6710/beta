<?php

use application\controllers\LocaleController as Locale;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/vendor/autoload.php');
$dotenv = \Dotenv\Dotenv::createImmutable((__DIR__));
$dotenv->safeLoad();
include(__DIR__ . "/system/Loader.php");

// ایجاد نمونه کلاس Locale
$locale = new Locale();

// متنی که از تلگرام دریافت می‌شود (از طریق API)
$telegram_texts = [
    "👀بازی با ناشناس •",
    "• دوستان👤",
    "• حساب کاربری •",
    "🥇نفرات برتر •",
    "⁉️راهنما •",
    "• پشتیبانی👨‍💻"
];

// متن‌های ترجمه شده از فایل‌های locale
$locale_keys = [
    'keyboard.home.play_with_unknown',
    'keyboard.home.friend',
    'keyboard.home.account',
    'keyboard.home.leaderboard',
    'keyboard.home.info',
    'keyboard.home.support'
];

echo "=== بررسی مقایسه رشته‌ها ===\n\n";

foreach ($locale_keys as $key) {
    $translated = $locale->trans($key);
    echo "کلید: $key\n";
    echo "ترجمه: [$translated]\n";
    echo "طول رشته: " . strlen($translated) . "\n";
    echo "کد ASCII رشته: ";
    for ($i = 0; $i < strlen($translated); $i++) {
        echo ord($translated[$i]) . " ";
    }
    echo "\n\n";
}

echo "=== مقایسه با متن‌های تلگرام ===\n\n";

foreach ($telegram_texts as $ttext) {
    echo "متن تلگرام: [$ttext]\n";
    echo "طول رشته: " . strlen($ttext) . "\n";
    echo "کد ASCII رشته: ";
    for ($i = 0; $i < strlen($ttext); $i++) {
        echo ord($ttext[$i]) . " ";
    }
    echo "\n";
    
    foreach ($locale_keys as $key) {
        $translated = $locale->trans($key);
        echo "  مقایسه با $key:\n";
        echo "  عادی: " . ($ttext == $translated ? "یکسان" : "متفاوت") . "\n";
        echo "  تریم شده: " . (trim($ttext) == trim($translated) ? "یکسان" : "متفاوت") . "\n";
        echo "  strpos: " . (strpos($ttext, $translated) !== false ? "پیدا شد" : "پیدا نشد") . "\n";
        echo "  strpos بر عکس: " . (strpos($translated, $ttext) !== false ? "پیدا شد" : "پیدا نشد") . "\n";
        
        // یافتن کلمات کلیدی
        $keywords = [
            'بازی با ناشناس',
            'دوستان',
            'حساب کاربری',
            'نفرات برتر',
            'راهنما',
            'پشتیبانی'
        ];
        
        foreach ($keywords as $keyword) {
            if (strpos($ttext, $keyword) !== false) {
                echo "  کلمه کلیدی $keyword: در متن تلگرام پیدا شد\n";
            }
            if (strpos($translated, $keyword) !== false) {
                echo "  کلمه کلیدی $keyword: در ترجمه پیدا شد\n";
            }
        }
        echo "\n";
    }
    echo "----------------------------\n\n";
}
?>