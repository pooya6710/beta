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

// توکن تلگرام
$token = $_ENV['TELEGRAM_TOKEN'];

// تنظیم آخرین آپدیت دریافت شده
$lastUpdateId = 0;

echo "ربات تلگرام ساده در حال اجرا...\n";
echo "برای توقف، کلید Ctrl+C را فشار دهید.\n\n";

// حلقه اصلی برای دریافت پیام‌ها
while (true) {
    // دریافت آپدیت‌ها از تلگرام
    $updates = getUpdates($token, $lastUpdateId);
    
    if (!$updates || !isset($updates['result']) || empty($updates['result'])) {
        // اگر آپدیتی نبود، 1 ثانیه صبر کن و دوباره تلاش کن
        sleep(1);
        continue;
    }
    
    // پردازش هر آپدیت
    foreach ($updates['result'] as $update) {
        try {
            // به‌روزرسانی آخرین آی‌دی آپدیت
            $lastUpdateId = $update['update_id'] + 1;
            
            // تبدیل آپدیت به شیء
            $updateObj = json_decode(json_encode($update));
            
            // ایجاد متغیرهای کلی برای استفاده در include
            $GLOBALS['telegram'] = new Telegram($updateObj);
            $GLOBALS['locale'] = new Locale();
            $GLOBALS['keyboard'] = new Keyboard();
            $GLOBALS['option'] = new Option();
            $GLOBALS['helper'] = new Helper();
            $GLOBALS['user'] = new User($updateObj);
            $GLOBALS['step'] = new Step($updateObj);
            
            // دسترسی سریع به متغیرهای مهم
            $telegram = $GLOBALS['telegram'];
            $locale = $GLOBALS['locale'];
            $keyboard = $GLOBALS['keyboard'];
            $option = $GLOBALS['option'];
            $helper = $GLOBALS['helper'];
            $user = $GLOBALS['user'];
            $step = $GLOBALS['step'];
            
            // چاپ اطلاعات آپدیت
            echo "آپدیت جدید دریافت شد (ID: {$update['update_id']})\n";
            if (isset($update['message']['text'])) {
                echo "پیام: {$update['message']['text']}\n";
                echo "از کاربر: {$update['message']['from']['first_name']} (ID: {$update['message']['from']['id']})\n";
            } elseif (isset($update['callback_query'])) {
                echo "کالبک: {$update['callback_query']['data']}\n";
                echo "از کاربر: {$update['callback_query']['from']['first_name']} (ID: {$update['callback_query']['from']['id']})\n";
            }
            
            // اجرای کد مشابه index.php
            if (isset($update['message']['text']) && str_starts_with($update['message']['text'], "/start")) {
                $exploded_text = explode(" ", $update['message']['text']);
                if (isset($exploded_text[1])) {
                    $exploded_text = explode("_", $exploded_text[1]);
                    if ($exploded_text[0] === "re") {
                        $ref_user_id = DB::table('users')->where('username', $exploded_text[1])->select('id')->first()['id'];
                        $user = new User($updateObj, $ref_user_id);
                        if (!$ref_user_id) {
                            $telegram->sendMessage('%message.wrong_ref_link%')->send();
                            continue;
                        }
                        if ($user->is_ref == 0) {
                            $telegram->sendMessage('%message.warning_joined_before%')->send();
                            continue;
                        } elseif ($user->is_ref == 1) {
                            $ref_telegram_id = DB::table('users')->where('username', $exploded_text[1])->select('telegram_id')->first()['telegram_id'];
                            DB::rawQuery("UPDATE users_extra SET doz_coin = doz_coin + 0.5 WHERE user_id = $ref_user_id;");
                            $doz_coin = DB::rawQuery("SELECT doz_coin FROM users_extra WHERE user_id = ( SELECT id FROM users WHERE telegram_id = $ref_telegram_id);")[0]['doz_coin'];
                            $telegram->sendMessage("%message.ref_joined[doz_coin:$doz_coin]%")->send($ref_telegram_id);
                        }
                    }
                }
            }

            // بررسی اجبار عضویت در کانال‌ها
            if ($option->forced_to_join && !(isset($updateObj->callback_query->data) && str_starts_with($updateObj->callback_query->data, "p1"))) {
                $should_to_join = [];
                foreach ($option->channels as $channel) {
                    $status = $telegram->getChatMember($channel);
                    if ($status == 'left' || $status == 'kicked') {
                        $channel_info = $telegram->getChat($channel);
                        if ($channel_info['ok']) {
                            $should_to_join[] = [['text' => $channel_info['result']['title'], 'url' => $channel_info['result']['invite_link']]];
                        }
                    }
                }
                if ($should_to_join) {
                    $should_to_join[] = [['text' => $locale->trans('message.i_join'), 'callback_data' => 'i_join']];
                    if (isset($updateObj->callback_query) && $updateObj->callback_query->data == 'i_join') {
                        $telegram->deleteMessage();
                        $telegram->sendMessage('%message.force_rejoin%')->inline_keyboard($should_to_join)->send();
                    } else {
                        $telegram->sendMessage('%message.force_join%')->inline_keyboard($should_to_join)->send();
                    }
                    continue;
                }
            }

            // بررسی تنظیم نام کاربری
            if ($step->get() == 'set_username') {
                if (isset($updateObj->message->text) && preg_match('/^[a-zA-Z][a-zA-Z0-9]{4,11}$/', $updateObj->message->text)) {
                    $is_username_exist = DB::table('users')->where('username', $updateObj->message->text)->select('id')->first();
                    if ($is_username_exist) {
                        $telegram->sendMessage("%message.username_exits%")->send();
                        continue;
                    }
                    DB::table('users')->where('telegram_id', $telegram->from_id)->update(['username' => $updateObj->message->text]);
                    $step->clear();
                    $telegram->sendMessage("%message.username_ok[username:{$updateObj->message->text}]%")->keyboard('main.home')->send();
                    continue;
                } else {
                    $telegram->sendMessage('%message.bad_username%')->send();
                    continue;
                }
            }

            // بررسی وضعیت نام کاربری
            if ($user->userData() && isset($user->userData()['username']) && $user->userData()['username'] == NULL) {
                $first_name = isset($updateObj->message->from->first_name) ? $updateObj->message->from->first_name : 'کاربر';
                $telegram->sendMessage("%message.set_username[firstname:$first_name]%")->send();
                $step->set('set_username');
                continue;
            }

            // بررسی دسترسی ادمین
            if ($user->isAdmin()) {
                if (isset($updateObj->message)) {
                    include(__DIR__ . '/handler/handle_admin.php');
                }
            }

            // پردازش براساس نوع آپدیت
            if (isset($updateObj->message)) {
                include(__DIR__ . '/handler/handle_message.php');
            } elseif (isset($updateObj->inline_query)) {
                include(__DIR__ . '/handler/handle_inline_query.php');
            } elseif (isset($updateObj->callback_query)) {
                include(__DIR__ . '/handler/handle_callback_query.php');
            }
            
            echo "پردازش موفقیت‌آمیز بود.\n";
            echo "-------------------\n";
        } catch (Exception $e) {
            // در صورت بروز خطا، آن را نمایش بده و به آپدیت بعدی برو
            echo "خطا در پردازش آپدیت: " . $e->getMessage() . "\n";
            echo "در خط: " . $e->getLine() . "\n";
            echo "در فایل: " . $e->getFile() . "\n";
            echo "-------------------\n";
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
        'timeout' => 30,
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
?>