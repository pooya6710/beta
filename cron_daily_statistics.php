<?php
/**
 * اسکریپت روزانه برای جمع‌آوری و ثبت آمار
 * این اسکریپت را می‌توانید با Cron Job هر شب اجرا کنید
 * 
 * مثال اجرا با کرون:
 * 0 0 * * * php /path/to/cron_daily_statistics.php
 */

// بارگذاری فایل‌های مورد نیاز
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/application/controllers/StatisticsController.php';

// بارگذاری متغیرهای محیطی از فایل .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ذخیره آمار روزانه
try {
    $result = \application\controllers\StatisticsController::saveDaily();
    
    if ($result) {
        echo "آمار روزانه با موفقیت ثبت شد.\n";
    } else {
        echo "خطا در ثبت آمار روزانه.\n";
    }
} catch (Exception $e) {
    echo "خطا: " . $e->getMessage() . "\n";
}