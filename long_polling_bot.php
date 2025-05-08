<?php

use application\controllers\HelperController as Helper;
use application\controllers\TelegramClass as Telegram;
use application\controllers\LocaleController as Locale;
use application\controllers\KeyboardController as Keyboard;
use application\controllers\StepController as Step;
use application\controllers\UserController as User;
use application\controllers\OptionController as Option;
use Application\Model\DB;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/vendor/autoload.php');
$dotenv = \Dotenv\Dotenv::createImmutable((__DIR__));
$dotenv->safeLoad();
include(__DIR__ . "/system/Loader.php");
date_default_timezone_set('Asia/Tehran');

// تنظیم آخرین آپدیت دریافت شده
$lastUpdateId = 0;

echo "ربات تلگرام اصلی در حال اجرا با روش Long Polling...\n";
echo "برای توقف، کلید Ctrl+C را فشار دهید.\n\n";

// حلقه اصلی برای دریافت پیام‌ها
while (true) {
    // دریافت آپدیت‌ها از تلگرام
    $updates = getUpdates($_ENV['TELEGRAM_TOKEN'], $lastUpdateId);
    
    if (!$updates || !isset($updates['result']) || empty($updates['result'])) {
        // اگر آپدیتی نبود، کمتر صبر کن و دوباره تلاش کن (افزایش سرعت پاسخ‌دهی)
        usleep(50000); // 0.05 second (کاهش زمان انتظار برای افزایش سرعت)
        continue;
    }
    
    // پردازش هر آپدیت
    foreach ($updates['result'] as $update) {
        // به‌روزرسانی آخرین آی‌دی آپدیت
        $lastUpdateId = $update['update_id'] + 1;
        
        // تبدیل آپدیت به شیء
        $updateObj = json_decode(json_encode($update));
        
        try {
            // اینجا کد اصلی index.php را اجرا می‌کنیم
            processUpdate($updateObj);
            
            // چاپ اطلاعات آپدیت
            echo "آپدیت جدید پردازش شد (ID: {$update['update_id']})\n";
            if (isset($update['message']['text'])) {
                echo "پیام: {$update['message']['text']}\n";
                echo "از کاربر: {$update['message']['from']['first_name']} (ID: {$update['message']['from']['id']})\n";
            } elseif (isset($update['callback_query'])) {
                echo "کالبک: {$update['callback_query']['data']}\n";
                echo "از کاربر: {$update['callback_query']['from']['first_name']} (ID: {$update['callback_query']['from']['id']})\n";
            }
            echo "-------------------\n";
        } catch (Exception $e) {
            echo "خطا در پردازش آپدیت: " . $e->getMessage() . "\n";
        }
    }
}

/**
 * دریافت آپدیت‌ها از API تلگرام
 */
function getUpdates($token, $offset = 0) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates";
    $params = [
        'offset' => $offset,
        'timeout' => 5,
        'allowed_updates' => json_encode(["message", "callback_query", "inline_query"])
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
 * پردازش آپدیت با استفاده از کد اصلی ربات
 */
function processUpdate($update) {
    // ایجاد نمونه‌های مورد نیاز که در متغیرهای سراسری گرفته می‌شوند
    global $telegram, $locale, $keyboard, $option, $helper, $user, $step;
    $telegram = new Telegram($update);
    $locale = new Locale();
    $keyboard = new Keyboard();
    $option = new Option();
    $helper = new Helper();
    $user = new User($update);
    $step = new Step($update);
    
    // چاپ فقط اطلاعات ضروری برای افزایش سرعت
    if (isset($update->message->text)) {
        echo "پیام دریافتی: {$update->message->text}\n";
    }
    
    $first_name = isset($update->message->from->first_name) ? $update->message->from->first_name : 'کاربر';

    // کد اصلی index.php از اینجا اجرا می‌شود
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
            // پاسخ به دستور /start ساده
            $user = new User($update);
            $telegram->sendMessage("%message.start[firstname:$first_name]%")->keyboard('main.home')->replay()->send();
            return; // خروج از تابع پس از ارسال پیام
        }
    }

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

    if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
        include(__DIR__ . '/handler/handle_admin.php');
    }

    if (isset($update->message)) {
        include(__DIR__ . '/handle_message_fixed.php');
    } elseif (isset($update->inline_query)) {
        include(__DIR__ . '/handler/handle_inline_query.php');
    } elseif (isset($update->callback_query)) {
        include(__DIR__ . '/handler/handle_callback_query.php');
    }
}
?>