<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
include(__DIR__ . "/system/Loader.php");
date_default_timezone_set('Asia/Tehran');

// شناسه آخرین آپدیت که در هر اجرا از فایل خوانده می‌شود
$lastUpdateIdFile = __DIR__ . '/last_update_id.txt';

echo "ربات تلگرام پیشرفته با Long Polling بهینه‌شده در حال اجرا...\n";
echo "برای توقف، کلید Ctrl+C را فشار دهید.\n\n";

// حلقه اصلی برای دریافت پیام‌ها
while (true) {
    // خواندن آخرین آپدیت از فایل در هر بار تکرار برای اطمینان از به‌روز بودن
    if (file_exists($lastUpdateIdFile)) {
        $lastUpdateId = (int)file_get_contents($lastUpdateIdFile);
    } else {
        $lastUpdateId = 0;
        file_put_contents($lastUpdateIdFile, $lastUpdateId);
    }

    echo "در انتظار پیام‌های جدید... (آخرین آپدیت: {$lastUpdateId})\n";
    
    // دریافت آپدیت‌ها با استفاده از روش کرل با پارامترهای پایدار
    $updates = getUpdatesWithCurl($_ENV['TELEGRAM_TOKEN'], $lastUpdateId);
    
    if (!$updates || !isset($updates['result']) || empty($updates['result'])) {
        // خطایی رخ داده یا هیچ به‌روزرسانی جدیدی وجود ندارد
        usleep(500000); // 0.5 ثانیه صبر کن تا سرور تلگرام بار نیاید
        continue;
    }
    
    // اگر آپدیت‌های جدید دریافت شدند
    foreach ($updates['result'] as $update) {
        // به‌روزرسانی آخرین آی‌دی آپدیت
        $newUpdateId = $update['update_id'] + 1;
        file_put_contents($lastUpdateIdFile, $newUpdateId);
        
        // تبدیل آپدیت از آرایه به شیء
        $updateObj = json_decode(json_encode($update));
        
        try {
            // پردازش آپدیت
            processUpdate($updateObj);
            
            // چاپ اطلاعات آپدیت
            echo "آپدیت پردازش شد (ID: {$update['update_id']})\n";
            if (isset($update['message']['text'])) {
                echo "پیام: {$update['message']['text']}\n";
                echo "از کاربر: {$update['message']['from']['first_name']} (ID: {$update['message']['from']['id']})\n";
            } elseif (isset($update['callback_query'])) {
                echo "کالبک: {$update['callback_query']['data']}\n";
                echo "از کاربر: {$update['callback_query']['from']['first_name']} (ID: {$update['callback_query']['from']['id']})\n";
            }
            echo "===================================\n";
        } catch (Exception $e) {
            echo "خطا در پردازش آپدیت: " . $e->getMessage() . "\n";
        }
    }
}

/**
 * دریافت آپدیت‌ها از API تلگرام با استفاده از CURL - بهینه‌سازی شده برای ثبات
 */
function getUpdatesWithCurl($token, $offset = 0) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates";
    
    // پارامترها برای درخواست به API تلگرام
    $params = [
        'offset' => $offset,
        'timeout' => 2,        // زمان انتظار برای long polling - کوتاه برای پاسخ سریع‌تر
        'limit' => 50,         // حداکثر تعداد آپدیت‌های دریافتی
        'allowed_updates' => json_encode(['message', 'callback_query', 'inline_query'])
    ];
    
    // ایجاد URL کامل با پارامترها
    $url .= '?' . http_build_query($params);
    
    // تنظیم و اجرای CURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);   // زمان انتظار برای اتصال
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);          // زمان انتظار کلی
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // غیرفعال کردن بررسی SSL - فقط برای آزمایش
    
    // اجرای درخواست
    $response = curl_exec($ch);
    
    // بررسی خطاها
    if ($response === false) {
        $error = curl_error($ch);
        $errorCode = curl_errno($ch);
        curl_close($ch);
        echo "خطا در CURL: {$error} (کد: {$errorCode})\n";
        return false;
    }
    
    curl_close($ch);
    
    // تبدیل پاسخ JSON به آرایه PHP
    $decodedResponse = json_decode($response, true);
    
    if (!isset($decodedResponse['ok']) || $decodedResponse['ok'] !== true) {
        $error = isset($decodedResponse['description']) ? $decodedResponse['description'] : 'خطای نامشخص';
        echo "خطا از API تلگرام: {$error}\n";
    }
    
    return $decodedResponse;
}

/**
 * پردازش آپدیت با استفاده از کد ربات
 */
function processUpdate($update) {
    // ایجاد نمونه‌های مورد نیاز
    global $telegram, $locale, $keyboard, $option, $helper, $user, $step;
    $telegram = new application\controllers\TelegramClass($update);
    $locale = new application\controllers\LocaleController();
    $keyboard = new application\controllers\KeyboardController();
    $option = new application\controllers\OptionController();
    $helper = new application\controllers\HelperController();
    $user = new application\controllers\UserController($update);
    $step = new application\controllers\StepController($update);
    
    // نمایش اطلاعات پیام دریافتی
    if (isset($update->message->text)) {
        echo "پیام دریافتی: {$update->message->text}\n";
    }
    
    $first_name = isset($update->message->from->first_name) ? $update->message->from->first_name : 'کاربر';

    // رسیدگی به دستور /start
    if (isset($update->message->text) && str_starts_with($update->message->text, "/start")) {
        $exploded_text = explode(" ", $update->message->text);
        if (isset($exploded_text[1])) {
            $exploded_text = explode("_", $exploded_text[1]);
            if ($exploded_text[0] === "re") {
                $ref_user_id = DB::table('users')->where('username', $exploded_text[1])->select('id')->first()['id'];
                $user = new User($update, $ref_user_id);
                if (!$ref_user_id) {
                    $telegram->sendMessage('%message.wrong_ref_link%')->send();
                    return;
                }
                if ($user->is_ref == 0) {
                    $telegram->sendMessage('%message.warning_joined_before%')->send();
                    return;
                } elseif ($user->is_ref == 1) {
                    $ref_telegram_id = DB::table('users')->where('username', $exploded_text[1])->select('telegram_id')->first()['telegram_id'];
                    DB::rawQuery("UPDATE users_extra SET doz_coin = doz_coin + 0.5 WHERE user_id = $ref_user_id;");
                    $doz_coin = DB::rawQuery("SELECT doz_coin FROM users_extra WHERE user_id = ( SELECT id FROM users WHERE telegram_id = $ref_telegram_id);")[0]['doz_coin'];

                    $telegram->sendMessage("%message.ref_joined[doz_coin:$doz_coin]%")->send($ref_telegram_id);
                }
            }
        } else {
            // دستور /start ساده
            $user = new User($update);
            $telegram->sendMessage("%message.start[firstname:$first_name]%")->keyboard('main.home')->replay()->send();
            return;
        }
    }

    // بررسی عضویت کاربر در کانال‌ها
    $user = new User($update);
    $step = new Step($update);
    
    if ($option->forced_to_join && !(isset($update->callback_query->data) && str_starts_with($update->callback_query->data, "p1"))) {
        $should_to_join = [];
        foreach ($option->channels as $channel) {
            $status = $telegram->getChatMember($channel);
            if ($status == 'left' || $status == 'kicked') {
                $channel = $telegram->getChat($channel);
                if ($channel['ok']) {
                    $should_to_join[] = [['text' => $channel['result']['title'], 'url' => $channel['result']['invite_link']]];
                }
            }
        }
        if ($should_to_join) {
            $should_to_join[] = [['text' => $locale->trans('message.i_join'), 'callback_data' => 'i_join']];
            if (isset($update->callback_query) && $update->callback_query->data == 'i_join') {
                $telegram->deleteMessage();
                $telegram->sendMessage('%message.force_rejoin%')->inline_keyboard($should_to_join)->send();
            } else {
                $telegram->sendMessage('%message.force_join%')->inline_keyboard($should_to_join)->send();
            }
            return;
        }
    }

    // بررسی تنظیم نام کاربری
    if ($step && method_exists($step, 'get') && $step->get() == 'set_username') {
        if (isset($update->message->text) && preg_match('/^[a-zA-Z][a-zA-Z0-9]{4,11}$/', $update->message->text)) {
            $is_username_exist = DB::table('users')->where('username', $update->message->text)->select('id')->first();
            if ($is_username_exist) {
                $telegram->sendMessage("%message.username_exits%")->send();
                return;
            }
            DB::table('users')->where('telegram_id', $telegram->from_id)->update(['username' => $update->message->text]);
            $step->clear();
            $telegram->sendMessage("%message.username_ok[username:{$update->message->text}]%")->keyboard('main.home')->send();
            return;
        } else {
            $telegram->sendMessage('%message.bad_username%')->send();
            return;
        }
    }

    if ($user && method_exists($user, 'userData') && $user->userData() && isset($user->userData()['username']) && $user->userData()['username'] == NULL) {
        $telegram->sendMessage("%message.set_username[firstname:$first_name]%")->send();
        $step->set('set_username');
        return;
    }

    // بررسی دسترسی ادمین
    if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
        include(__DIR__ . '/handler/handle_admin.php');
    }

    // پردازش بر اساس نوع درخواست
    if (isset($update->message)) {
        include(__DIR__ . '/handle_message_fixed.php');
    } elseif (isset($update->inline_query)) {
        include(__DIR__ . '/handler/handle_inline_query.php');
    } elseif (isset($update->callback_query)) {
        include(__DIR__ . '/handler/handle_callback_query.php');
    }
}
?>