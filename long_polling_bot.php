<?php
/**
 * ربات ساده Long Polling فقط با قابلیت لغو بازی
 */
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
include(__DIR__ . "/system/Loader.php");
date_default_timezone_set('Asia/Tehran');

// تنظیم آخرین آپدیت دریافت شده
$lastUpdateIdFile = __DIR__ . '/last_update_id.txt';
if (file_exists($lastUpdateIdFile)) {
    $lastUpdateId = (int)file_get_contents($lastUpdateIdFile);
} else {
    $lastUpdateId = 0;
}

// ذخیره آخرین شناسه پردازش شده جدید
if (!file_exists($lastUpdateIdFile)) {
    file_put_contents($lastUpdateIdFile, "603369409");
    $lastUpdateId = 603369409; // آخرین آپدیت شناسایی شده فعلی
}

echo "ربات تلگرام اصلی (نسخه کمینه) در حال اجرا با روش Long Polling...\n";
echo "آخرین آپدیت شروع: {$lastUpdateId}\n";
echo "برای توقف، کلید Ctrl+C را فشار دهید.\n\n";

// حلقه اصلی برای دریافت پیام‌ها
while (true) {
    // دریافت آپدیت‌ها از تلگرام
    $updates = getUpdatesViaFopen($_ENV['TELEGRAM_TOKEN'], $lastUpdateId);
    
    if (!$updates || !isset($updates['result']) || empty($updates['result'])) {
        // اگر آپدیتی نبود، کمی صبر کن و دوباره تلاش کن
        sleep(1);
        echo ".";
        continue;
    }
    
    // پردازش هر آپدیت
    foreach ($updates['result'] as $update) {
        // به‌روزرسانی آخرین آی‌دی آپدیت و ذخیره در فایل
        $lastUpdateId = $update['update_id'] + 1;
        file_put_contents($lastUpdateIdFile, $lastUpdateId);
        
        echo "\nآپدیت جدید (ID: {$update['update_id']})\n";
        
        // پردازش callback query (دکمه‌های inline)
        if (isset($update['callback_query'])) {
            $callback_query = $update['callback_query'];
            $callback_data = $callback_query['data'];
            $chat_id = $callback_query['message']['chat']['id'];
            $message_id = $callback_query['message']['message_id'];
            $user_id = $callback_query['from']['id'];
            
            echo "کالبک کوئری دریافت شد: {$callback_data}\n";
            
            // پردازش درخواست دوستی
            if (strpos($callback_data, 'friend_request:') === 0) {
                try {
                    // استخراج آیدی کاربر هدف
                    $target_user_id = substr($callback_data, strlen('friend_request:'));
                    
                    // بررسی اینکه آیا کاربر قبلاً در دیتابیس ثبت شده است
                    $user = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$user) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: شما هنوز در سیستم ثبت نشده‌اید!");
                        echo "خطا: کاربر درخواست دهنده در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // بررسی اینکه آیا کاربر هدف در دیتابیس ثبت شده است
                    $target_user = \Application\Model\DB::table('users')->where('id', $target_user_id)->first();
                    if (!$target_user) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: کاربر مورد نظر یافت نشد!");
                        echo "خطا: کاربر هدف در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // بررسی اینکه آیا کاربر قبلاً درخواست دوستی ارسال کرده است
                    $existing_request = \Application\Model\DB::table('friend_requests')
                        ->where('from_user_id', $user['id'])
                        ->where('to_user_id', $target_user_id)
                        ->where('status', 'pending')
                        ->first();
                        
                    if ($existing_request) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ شما قبلاً به این کاربر درخواست دوستی ارسال کرده‌اید!");
                        echo "خطا: درخواست دوستی تکراری\n";
                        continue;
                    }
                    
                    // بررسی اینکه آیا دو کاربر قبلاً دوست هستند
                    $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $user['id'])->first();
                    if ($userExtra && isset($userExtra['friends'])) {
                        $friends = json_decode($userExtra['friends'], true);
                        if (is_array($friends) && in_array($target_user_id, $friends)) {
                            answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ شما و این کاربر در حال حاضر دوست هستید!");
                            echo "خطا: کاربران قبلاً دوست هستند\n";
                            continue;
                        }
                    }
                    
                    // ثبت درخواست دوستی در جدول friend_requests
                    \Application\Model\DB::table('friend_requests')->insert([
                        'from_user_id' => $user['id'],
                        'to_user_id' => $target_user_id,
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // پاسخ به کاربر
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ درخواست دوستی با موفقیت ارسال شد!");
                    echo "درخواست دوستی از کاربر {$user['id']} به کاربر {$target_user_id} ثبت شد\n";
                    
                    // اطلاع‌رسانی به کاربر هدف
                    if (isset($target_user['telegram_id'])) {
                        $message = "🔔 شما یک درخواست دوستی جدید دارید!\n\nکاربر {$user['username']} شما را به عنوان دوست اضافه کرده است.\n\nبرای مشاهده درخواست‌های دوستی، به منوی دوستان > درخواست‌های دوستی بروید.";
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $target_user['telegram_id'], $message);
                        echo "اطلاع‌رسانی به کاربر هدف انجام شد\n";
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش درخواست دوستی: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در پردازش درخواست دوستی: " . $e->getMessage());
                }
            }
            
            // پردازش دکمه صدا زدن کاربر در بازی
            else if (strpos($callback_data, 'notify_opponent:') === 0) {
                try {
                    // استخراج آیدی بازی
                    $match_id = substr($callback_data, strlen('notify_opponent:'));
                    
                    // دریافت اطلاعات بازی
                    $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                    if (!$match) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                        echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // تعیین حریف کاربر فعلی
                    $opponent_id = ($match['player1'] == $user_id) ? $match['player2'] : $match['player1'];
                    
                    if (!$opponent_id) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات حریف کامل نیست!");
                        echo "خطا: اطلاعات حریف در بازی {$match_id} کامل نیست\n";
                        continue;
                    }
                    
                    // اطلاع‌رسانی به حریف
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $opponent_id, "🔔 نوبت توعه! بازی کن.");
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ به حریف شما اطلاع داده شد!");
                    echo "اطلاع‌رسانی به حریف با آیدی {$opponent_id} انجام شد\n";
                    
                } catch (Exception $e) {
                    echo "خطا در اطلاع‌رسانی به حریف: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در اطلاع‌رسانی به حریف: " . $e->getMessage());
                }
            }
            
            // پاسخ به نظرسنجی پایان بازی
            else if (strpos($callback_data, 'end_chat:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $match_id = $parts[1];
                    $action = $parts[2]; // extend یا end
                    
                    // دریافت اطلاعات بازی
                    $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                    if (!$match) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                        echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    if ($action === 'extend') {
                        // بررسی وجود ستون chat_end_time
                        try {
                            // افزایش زمان چت به 5 دقیقه
                            \Application\Model\DB::table('matches')
                                ->where('id', $match_id)
                                ->update(['chat_end_time' => date('Y-m-d H:i:s', strtotime('+5 minutes'))]);
                        } catch (Exception $e) {
                            // اگر ستون وجود نداشت، خطا را نادیده بگیر و تنها در لاگ ثبت کن
                            echo "خطا در به‌روزرسانی chat_end_time: " . $e->getMessage() . "\n";
                        }
                        
                        // اطلاع‌رسانی به هر دو بازیکن
                        $message = "مقدار زمان چتِ بعد از بازی شما به 5 دقیقه افزایش یافت";
                        
                        // ارسال به هر دو بازیکن
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $match['player1'], $message);
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $match['player2'], $message);
                        
                        // تنظیم تایمر برای اطلاع‌رسانی 30 ثانیه آخر
                        // در یک سیستم واقعی، این کار باید با کرون جاب انجام شود
                        
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ زمان چت به 5 دقیقه افزایش یافت.");
                        echo "زمان چت برای بازی {$match_id} به 5 دقیقه افزایش یافت\n";
                        
                        // ویرایش پیام نظرسنجی برای جلوگیری از انتخاب مجدد
                        $new_text = "زمان چت به 5 دقیقه افزایش یافت. ✅";
                        editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    } 
                    else if ($action === 'end') {
                        // درخواست تأیید برای قطع چت
                        $confirm_message = "آیا مطمئنید میخواهید قابلیت چت را غیرفعال کنید؟\nبا این اقدام دیگر در این بازی پیامی ارسال یا دریافت نخواهد شد!";
                        
                        $confirm_keyboard = json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => 'غیرفعال شود', 'callback_data' => "confirm_end_chat:{$match_id}:yes"],
                                    ['text' => 'فعال بماند', 'callback_data' => "confirm_end_chat:{$match_id}:no"]
                                ]
                            ]
                        ]);
                        
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $confirm_message, $confirm_keyboard);
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "درخواست تأیید برای غیرفعال کردن چت ارسال شد.");
                        
                        // ویرایش پیام نظرسنجی قبلی
                        $new_text = "در انتظار تأیید برای قطع چت...";
                        editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش نظرسنجی پایان بازی: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // پاسخ به تأیید تغییر نام کاربری
            else if (strpos($callback_data, 'confirm_username_change:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $new_username = $parts[1];
                    $response = $parts[2]; // yes یا no
                    
                    // دریافت اطلاعات کاربر
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->first();
                    if (!$userData) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات کاربری یافت نشد!");
                        continue;
                    }
                    
                    // حذف فایل وضعیت کاربر
                    $user_state_file = __DIR__ . "/user_states/{$user_id}.json";
                    if (file_exists($user_state_file)) {
                        unlink($user_state_file);
                    }
                    
                    if ($response === 'yes') {
                        // دریافت اطلاعات اضافی کاربر برای کسر هزینه
                        $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->first();
                        if (!$userExtra) {
                            answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات اضافی کاربر یافت نشد!");
                            continue;
                        }
                        
                        // بررسی کافی بودن موجودی
                        $delta_coins = isset($userExtra['delta_coins']) ? $userExtra['delta_coins'] : 0;
                        if ($delta_coins < 10) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ موجودی شما {$delta_coins} دلتاکوین میباشد. مقدار دلتاکوین موردنیاز جهت تغییر نام کاربری 10 عدد میباشد!");
                            continue;
                        }
                        
                        // به روزرسانی نام کاربری
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['username' => $new_username]);
                        
                        // کسر هزینه تغییر نام کاربری
                        \Application\Model\DB::table('users_extra')
                            ->where('user_id', $userData['id'])
                            ->update(['delta_coins' => $delta_coins - 10]);
                        
                        // ارسال پیام موفقیت
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ نام کاربری شما با موفقیت به «{$new_username}» تغییر یافت و 10 دلتاکوین از حساب شما کسر شد.");
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ نام کاربری با موفقیت تغییر یافت");
                    } else {
                        // لغو تغییر نام کاربری
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ تغییر نام کاربری لغو شد.");
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "❌ تغییر نام کاربری لغو شد");
                    }
                    
                    // ویرایش پیام کالبک
                    $new_text = $response === 'yes' 
                        ? "✅ نام کاربری به {$new_username} تغییر یافت."
                        : "❌ تغییر نام کاربری لغو شد.";
                    editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش تغییر نام کاربری: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // ری‌اکشن به پیام
            else if (strpos($callback_data, 'reaction:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $message_id = $parts[1];
                    $reaction = $parts[2];
                    
                    // لیست ایموجی‌های iPhone-style
                    $reactions = [
                        'like' => '👍',
                        'dislike' => '👎',
                        'love' => '❤️',
                        'laugh' => '😂',
                        'wow' => '😮',
                        'sad' => '😢',
                        'angry' => '😡',
                        'clap' => '👏',
                        'fire' => '🔥',
                        'party' => '🎉'
                    ];
                    
                    if (!isset($reactions[$reaction])) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: ری‌اکشن نامعتبر!");
                        continue;
                    }
                    
                    // ارسال ری‌اکشن (در تلگرام واقعی باید از متد reaction استفاده شود)
                    // اینجا فقط یک پیام نمایش می‌دهیم
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], $reactions[$reaction], true);
                    
                    echo "ری‌اکشن {$reactions[$reaction]} به پیام {$message_id} اضافه شد\n";
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش ری‌اکشن: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // درخواست فعال‌سازی مجدد چت بعد از غیرفعال شدن
            else if (strpos($callback_data, 'request_chat:') === 0) {
                try {
                    $match_id = substr($callback_data, strlen('request_chat:'));
                    
                    // دریافت اطلاعات بازی
                    $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                    if (!$match) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                        echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // بررسی اینکه آیا قبلاً درخواست فعال کردن چت داده شده است
                    try {
                        $has_pending_request = \Application\Model\DB::table('matches')
                            ->where('id', $match_id)
                            ->where('chat_request_pending', true)
                            ->exists();
                            
                        if ($has_pending_request) {
                            answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "درخواست چت قبلا ارسال شده منتظر پاسخ باشید");
                            echo "خطا: درخواست فعال‌سازی چت قبلاً ارسال شده است\n";
                            continue;
                        }
                    } catch (Exception $e) {
                        // اگر ستون وجود نداشت، نادیده بگیر
                        echo "خطا در بررسی وضعیت درخواست چت: " . $e->getMessage() . "\n";
                    }
                    
                    // تعیین کاربر درخواست کننده و حریف
                    $requester_id = $user_id;
                    $opponent_id = ($match['player1'] == $requester_id) ? $match['player2'] : $match['player1'];
                    
                    if (!$opponent_id) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: اطلاعات حریف کامل نیست!");
                        echo "خطا: اطلاعات حریف در بازی {$match_id} کامل نیست\n";
                        continue;
                    }
                    
                    // ثبت درخواست در دیتابیس
                    try {
                        \Application\Model\DB::table('matches')
                            ->where('id', $match_id)
                            ->update(['chat_request_pending' => true]);
                    } catch (Exception $e) {
                        // اگر ستون وجود نداشت، نادیده بگیر
                        echo "خطا در به‌روزرسانی وضعیت درخواست چت: " . $e->getMessage() . "\n";
                    }
                    
                    // اطلاع به درخواست کننده
                    $requester_message = "درخواست فعال شدن چت برای حریف ارسال شد منتظر پاسخ باشید";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $requester_id, $requester_message);
                    
                    // ارسال درخواست به حریف
                    $opponent_message = "حریف از شما درخواست فعال کردن چت را دارد\nبا قبول این درخواست شما میتوانید به یکدیگر پیام ارسال کنید!";
                    $opponent_keyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => 'فعال شود', 'callback_data' => "chat_response:{$match_id}:accept"],
                                ['text' => 'غیرفعال بماند', 'callback_data' => "chat_response:{$match_id}:reject"]
                            ]
                        ]
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $opponent_id, $opponent_message, $opponent_keyboard);
                    
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ درخواست فعال‌سازی چت ارسال شد.");
                    echo "درخواست فعال‌سازی چت از کاربر {$requester_id} به کاربر {$opponent_id} ارسال شد\n";
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش درخواست فعال‌سازی چت: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // پاسخ به درخواست فعال‌سازی چت
            else if (strpos($callback_data, 'chat_response:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $match_id = $parts[1];
                    $response = $parts[2]; // accept یا reject
                    
                    // دریافت اطلاعات بازی
                    $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                    if (!$match) {
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                        echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                        continue;
                    }
                    
                    // تعیین کاربر پاسخ دهنده و درخواست کننده
                    $responder_id = $user_id;
                    $requester_id = ($match['player1'] == $responder_id) ? $match['player2'] : $match['player1'];
                    
                    if ($response === 'accept') {
                        // فعال کردن چت
                        try {
                            \Application\Model\DB::table('matches')
                                ->where('id', $match_id)
                                ->update([
                                    'chat_enabled' => true,
                                    'chat_request_pending' => false
                                ]);
                        } catch (Exception $e) {
                            // اگر ستون وجود نداشت، نادیده بگیر
                            echo "خطا در به‌روزرسانی وضعیت چت: " . $e->getMessage() . "\n";
                        }
                        
                        // اعلام به هر دو کاربر
                        $notification = "✅ قابلیت چت فعال شد. اکنون می‌توانید با حریف خود چت کنید.";
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $requester_id, $notification);
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $responder_id, $notification);
                        
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ قابلیت چت فعال شد.");
                        echo "چت برای بازی {$match_id} فعال شد\n";
                    }
                    else if ($response === 'reject') {
                        // رد کردن درخواست
                        try {
                            \Application\Model\DB::table('matches')
                                ->where('id', $match_id)
                                ->update(['chat_request_pending' => false]);
                        } catch (Exception $e) {
                            // اگر ستون وجود نداشت، نادیده بگیر
                            echo "خطا در به‌روزرسانی وضعیت درخواست چت: " . $e->getMessage() . "\n";
                        }
                        
                        // اعلام به هر دو کاربر
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $requester_id, "❌ درخواست فعال کردن چت رد شد.");
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $responder_id, "❌ شما درخواست فعال کردن چت را رد کردید.");
                        
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "❌ درخواست فعال کردن چت رد شد.");
                        echo "درخواست فعال‌سازی چت برای بازی {$match_id} رد شد\n";
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش پاسخ به درخواست فعال‌سازی چت: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا در پردازش پاسخ: " . $e->getMessage());
                }
            }
            
            // تأیید یا رد درخواست قطع چت
            else if (strpos($callback_data, 'confirm_end_chat:') === 0) {
                try {
                    $parts = explode(':', $callback_data);
                    $match_id = $parts[1];
                    $response = $parts[2]; // yes یا no
                    
                    // پردازش مستقیم پاسخ
                    if ($response === 'yes') {
                        // کاربر تأیید کرده که چت قطع شود
                        $message = "بسیار خب. بازی شما به اتمام رسید چه کاری میتونم برات انجام بدم؟";
                        
                        try {
                            // به‌روزرسانی وضعیت چت در دیتابیس
                            \Application\Model\DB::table('matches')
                                ->where('id', $match_id)
                                ->update(['chat_enabled' => false]);
                        } catch (Exception $e) {
                            // اگر ستون وجود نداشت، نادیده بگیر
                            echo "خطا در به‌روزرسانی وضعیت چت: " . $e->getMessage() . "\n";
                        }
                        
                        // دریافت اطلاعات بازی
                        $match = \Application\Model\DB::table('matches')->where('id', $match_id)->first();
                        if (!$match) {
                            answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: بازی مورد نظر یافت نشد!");
                            echo "خطا: بازی {$match_id} در دیتابیس یافت نشد\n";
                            continue;
                        }
                        
                        // ارسال منوی اصلی به هر دو بازیکن
                        $keyboard = json_encode([
                            'keyboard' => [
                                [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                                [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                                [['text' => '👤 حساب کاربری'], ['text' => '🏆نفرات برتر•']],
                                [['text' => '• پشتیبانی👨‍💻'], ['text' => '⁉️راهنما •']]
                            ],
                            'resize_keyboard' => true
                        ]);
                        
                        // ارسال پیام اعلان به هر دو بازیکن
                        $notification = "قابلیت چت غیرفعال شد. برای فعال کردن مجدد از دکمه زیر استفاده کنید:";
                        $reactivate_keyboard = json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '🔄 فعال کردن مجدد چت', 'callback_data' => "request_chat:{$match_id}"]
                                ]
                            ]
                        ]);
                        
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $match['player1'], $notification, $reactivate_keyboard);
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $match['player2'], $notification, $reactivate_keyboard);
                        
                        // ارسال منوی اصلی
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $match['player1'], $message, $keyboard);
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $match['player2'], $message, $keyboard);
                        
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ چت پایان یافت و به منوی اصلی بازگشتید.");
                        echo "چت برای بازی {$match_id} پایان یافت\n";
                        
                        // ویرایش پیام نظرسنجی
                        $new_text = "چت پایان یافت. ✅";
                        editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    } else {
                        // کاربر درخواست قطع چت را لغو کرده
                        answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "✅ درخواست قطع چت لغو شد.");
                        
                        // ویرایش پیام تأیید
                        $new_text = "درخواست قطع چت لغو شد. چت همچنان فعال است.";
                        editMessageText($_ENV['TELEGRAM_TOKEN'], $chat_id, $message_id, $new_text);
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش تأیید قطع چت: " . $e->getMessage() . "\n";
                    answerCallbackQuery($_ENV['TELEGRAM_TOKEN'], $callback_query['id'], "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // در اینجا می‌توان سایر انواع callback_query را پردازش کرد
            
            continue;
        }
        
        // پردازش عکس (برای آپلود عکس پروفایل)
        if (isset($update['message']) && isset($update['message']['photo'])) {
            $chat_id = $update['message']['chat']['id'];
            $user_id = $update['message']['from']['id'];
            
            // دریافت اطلاعات کاربر و وضعیت فعلی
            try {
                $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                
                if (!$userData || !isset($userData['state']) || empty($userData['state'])) {
                    // اگر وضعیتی برای کاربر تعریف نشده، عکس را نادیده می‌گیریم
                    continue;
                }
                
                $userState = json_decode($userData['state'], true);
                
                // اگر کاربر در حال آپلود عکس پروفایل است
                if ($userState['state'] === 'profile' && $userState['step'] === 'photo') {
                    // دریافت بهترین کیفیت عکس
                    $photo = end($update['message']['photo']);
                    $file_id = $photo['file_id'];
                    
                    // ذخیره شناسه فایل عکس در پروفایل کاربر
                    $profileExists = \Application\Model\DB::table('user_profiles')
                        ->where('user_id', $userData['id'])
                        ->exists();
                    
                    if ($profileExists) {
                        \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->update(['photo_id' => $file_id, 'photo_approved' => false]);
                    } else {
                        \Application\Model\DB::table('user_profiles')->insert([
                            'user_id' => $userData['id'],
                            'photo_id' => $file_id,
                            'photo_approved' => false
                        ]);
                    }
                    
                    // ارسال عکس به کانال ادمین برای تأیید
                    $admin_channel_id = "-100123456789"; // آیدی کانال ادمین را قرار دهید
                    try {
                        $admin_message = "✅ درخواست تأیید عکس پروفایل:\n\nکاربر: {$userData['username']}\nآیدی: {$userData['telegram_id']}";
                        
                        $admin_keyboard = json_encode([
                            'inline_keyboard' => [
                                [
                                    ['text' => '✅ تأیید', 'callback_data' => "approve_photo:{$userData['id']}"],
                                    ['text' => '❌ رد', 'callback_data' => "reject_photo:{$userData['id']}"]
                                ]
                            ]
                        ]);
                        
                        // تابع ارسال عکس به کانال ادمین
                        // forwardPhoto($_ENV['TELEGRAM_TOKEN'], $chat_id, $admin_channel_id, $file_id, $admin_message, $admin_keyboard);
                    } catch (Exception $e) {
                        echo "خطا در ارسال عکس به کانال ادمین: " . $e->getMessage() . "\n";
                    }
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ عکس پروفایل شما با موفقیت ارسال شد و در انتظار تأیید ادمین است.");
                    
                    // بازگشت به منوی پروفایل
                    $userState = [
                        'state' => 'profile',
                        'step' => 'menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('id', $userData['id'])
                        ->update(['state' => json_encode($userState)]);
                    
                    // فراخوانی مجدد منوی پروفایل
                    $text = "📝 پروفایل";
                    $update['message']['text'] = $text;
                }
                
            } catch (Exception $e) {
                echo "خطا در پردازش عکس: " . $e->getMessage() . "\n";
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش عکس: " . $e->getMessage());
            }
        }
        
        // پردازش موقعیت مکانی
        if (isset($update['message']) && isset($update['message']['location'])) {
            $chat_id = $update['message']['chat']['id'];
            $user_id = $update['message']['from']['id'];
            
            // دریافت اطلاعات کاربر و وضعیت فعلی
            try {
                $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                
                if (!$userData || !isset($userData['state']) || empty($userData['state'])) {
                    // اگر وضعیتی برای کاربر تعریف نشده، موقعیت را نادیده می‌گیریم
                    continue;
                }
                
                $userState = json_decode($userData['state'], true);
                
                // اگر کاربر در حال ارسال موقعیت مکانی است
                if ($userState['state'] === 'profile' && $userState['step'] === 'location') {
                    $latitude = $update['message']['location']['latitude'];
                    $longitude = $update['message']['location']['longitude'];
                    $location_json = json_encode(['lat' => $latitude, 'lng' => $longitude]);
                    
                    // ذخیره موقعیت مکانی در پروفایل کاربر
                    $profileExists = \Application\Model\DB::table('user_profiles')
                        ->where('user_id', $userData['id'])
                        ->exists();
                    
                    if ($profileExists) {
                        \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->update(['location' => $location_json]);
                    } else {
                        \Application\Model\DB::table('user_profiles')->insert([
                            'user_id' => $userData['id'],
                            'location' => $location_json
                        ]);
                    }
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ موقعیت مکانی شما با موفقیت ثبت شد.");
                    
                    // بازگشت به منوی پروفایل
                    $userState = [
                        'state' => 'profile',
                        'step' => 'menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('id', $userData['id'])
                        ->update(['state' => json_encode($userState)]);
                    
                    // فراخوانی مجدد منوی پروفایل
                    $text = "📝 پروفایل";
                    $update['message']['text'] = $text;
                }
                
            } catch (Exception $e) {
                echo "خطا در پردازش موقعیت مکانی: " . $e->getMessage() . "\n";
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش موقعیت مکانی: " . $e->getMessage());
            }
        }
        
        // پردازش شماره تماس
        if (isset($update['message']) && isset($update['message']['contact'])) {
            $chat_id = $update['message']['chat']['id'];
            $user_id = $update['message']['from']['id'];
            
            // دریافت اطلاعات کاربر و وضعیت فعلی
            try {
                $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                
                if (!$userData || !isset($userData['state']) || empty($userData['state'])) {
                    // اگر وضعیتی برای کاربر تعریف نشده، شماره را نادیده می‌گیریم
                    continue;
                }
                
                $userState = json_decode($userData['state'], true);
                
                // اگر کاربر در حال ارسال شماره تماس است
                if ($userState['state'] === 'profile' && $userState['step'] === 'phone') {
                    $phone_number = $update['message']['contact']['phone_number'];
                    
                    // بررسی اینکه آیا شماره تلفن ایرانی است (شروع با +98)
                    $is_iranian = (strpos($phone_number, '+98') === 0);
                    
                    // ذخیره شماره تماس در پروفایل کاربر
                    $profileExists = \Application\Model\DB::table('user_profiles')
                        ->where('user_id', $userData['id'])
                        ->exists();
                    
                    if ($profileExists) {
                        \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->update(['phone' => $phone_number]);
                    } else {
                        \Application\Model\DB::table('user_profiles')->insert([
                            'user_id' => $userData['id'],
                            'phone' => $phone_number
                        ]);
                    }
                    
                    $message = "✅ شماره تلفن شما با موفقیت ثبت شد.";
                    if ($is_iranian) {
                        $message .= "\n\n✅ شماره شما ایرانی است و مشمول دریافت پورسانت می‌باشد.";
                    } else {
                        $message .= "\n\n❌ شماره شما ایرانی نیست و مشمول دریافت پورسانت نمی‌باشد.";
                    }
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                    // بازگشت به منوی پروفایل
                    $userState = [
                        'state' => 'profile',
                        'step' => 'menu'
                    ];
                    \Application\Model\DB::table('users')
                        ->where('id', $userData['id'])
                        ->update(['state' => json_encode($userState)]);
                    
                    // فراخوانی مجدد منوی پروفایل
                    $text = "📝 پروفایل";
                    $update['message']['text'] = $text;
                }
                
            } catch (Exception $e) {
                echo "خطا در پردازش شماره تماس: " . $e->getMessage() . "\n";
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش شماره تماس: " . $e->getMessage());
            }
        }
        
        // پردازش پیام‌های متنی
        if (isset($update['message']) && isset($update['message']['text'])) {
            $text = $update['message']['text'];
            $chat_id = $update['message']['chat']['id'];
            $user_id = $update['message']['from']['id'];
            $username = isset($update['message']['from']['username']) ? 
                        $update['message']['from']['username'] : 'بدون نام کاربری';
            
            echo "پیام از {$username}: {$text}\n";
            
            // بررسی وضعیت کاربر برای تغییر نام کاربری و سایر حالت‌های ویژه
            try {
                // بررسی آیا کاربر در حالت تغییر نام کاربری است
                $user_state_file = __DIR__ . "/user_states/{$user_id}.json";
                if (file_exists($user_state_file)) {
                    $userState = json_decode(file_get_contents($user_state_file), true);
                    
                    // پردازش حالت تغییر نام کاربری
                    if (isset($userState['state']) && $userState['state'] === 'change_username') {
                        // دریافت اطلاعات کاربر از دیتابیس
                        $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                        
                        if (!$userData) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                            echo "خطا: کاربر در دیتابیس یافت نشد\n";
                            unlink($user_state_file); // حذف فایل وضعیت
                            continue;
                        }
                        
                        // دریافت اطلاعات اضافی کاربر
                        $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->select('*')->first();
                        if (!$userExtra) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات اضافی کاربر");
                            echo "خطا: اطلاعات اضافی کاربر یافت نشد\n";
                            unlink($user_state_file); // حذف فایل وضعیت
                            continue;
                        }
                        
                        if ($userState['step'] === 'waiting_for_username') {
                            // بررسی نام کاربری جدید
                            $new_username = trim($text);
                            
                            // بررسی وجود کاربر دیگر با همین نام کاربری
                            $existingUser = \Application\Model\DB::table('users')
                                ->where('username', $new_username)
                                ->where('id', '!=', $userData['id'])
                                ->first();
                            
                            if ($existingUser) {
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ این نام کاربری قبلاً توسط کاربر دیگری انتخاب شده است. لطفاً نام کاربری دیگری انتخاب کنید.");
                                continue;
                            }
                            
                            // تایید نام کاربری
                            $confirm_message = "آیا مطمئنید میخواهید {$new_username} را برای نام کاربری خود استفاده کنید؟";
                            $confirm_keyboard = json_encode([
                                'inline_keyboard' => [
                                    [
                                        ['text' => 'بله', 'callback_data' => "confirm_username_change:{$new_username}:yes"],
                                        ['text' => 'خیر', 'callback_data' => "confirm_username_change:{$new_username}:no"]
                                    ]
                                ]
                            ]);
                            
                            sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $confirm_message, $confirm_keyboard);
                            
                            // آپدیت وضعیت کاربر به مرحله تایید
                            $userState['step'] = 'waiting_for_confirmation';
                            $userState['new_username'] = $new_username;
                            file_put_contents($user_state_file, json_encode($userState));
                            
                            continue;
                        }
                    }
                }
            } catch (Exception $e) {
                echo "خطا در پردازش وضعیت کاربر: " . $e->getMessage() . "\n";
            }
            
            // بررسی پیام چت بازی
            $active_match = getActiveMatchForUser($user_id);
            if ($active_match && $text[0] !== '/') {
                // تعیین گیرنده پیام (بازیکن دیگر)
                $recipient_id = ($active_match['player1'] == $user_id) ? $active_match['player2'] : $active_match['player1'];
                
                // بررسی امکان ارسال پیام
                $chat_enabled = true;
                try {
                    // بررسی وضعیت فعال بودن چت
                    $match_data = \Application\Model\DB::table('matches')
                        ->where('id', $active_match['id'])
                        ->select('chat_enabled')
                        ->first();
                    
                    if ($match_data && isset($match_data['chat_enabled']) && $match_data['chat_enabled'] === false) {
                        $chat_enabled = false;
                    }
                } catch (Exception $e) {
                    // اگر ستون وجود نداشت، فرض کنید چت فعال است
                    echo "خطا در بررسی وضعیت چت: " . $e->getMessage() . "\n";
                }
                
                if (!$chat_enabled) {
                    // چت غیرفعال است
                    $response = "قابلیت چت غیرفعال میباشد پیام شما ارسال نشد!";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $user_id, $response);
                    
                    // نمایش دکمه درخواست فعال کردن چت
                    $reactivate_keyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '🔄 فعال کردن مجدد چت', 'callback_data' => "request_chat:{$active_match['id']}"]
                            ]
                        ]
                    ]);
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $user_id, "برای درخواست فعال کردن چت از دکمه زیر استفاده کنید:", $reactivate_keyboard);
                    continue;
                }
                
                // بررسی نوع پیام ارسالی
                if (isset($update['message']['sticker']) || 
                    isset($update['message']['animation']) || 
                    isset($update['message']['photo']) || 
                    isset($update['message']['video']) || 
                    isset($update['message']['voice']) || 
                    isset($update['message']['audio']) || 
                    isset($update['message']['document'])) {
                    // پیام غیر متنی است
                    $response = "شما تنها مجاز به ارسال پیام بصورت متنی میباشید\nپیام شما ارسال نشد";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $user_id, $response);
                    continue;
                }
                
                // بررسی وجود لینک در پیام
                if (preg_match('/(https?:\/\/[^\s]+)/i', $text) || 
                    preg_match('/(www\.[^\s]+)/i', $text) || 
                    preg_match('/(@[^\s]+)/i', $text) || 
                    preg_match('/(t\.me\/[^\s]+)/i', $text)) {
                    // پیام حاوی لینک است
                    $response = "ارسال لینک ممنوع میباشد!\nپیام شما ارسال نشد";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $user_id, $response);
                    continue;
                }
                

                
                // ارسال پیام به بازیکن دیگر
                $sender_name = isset($update['message']['from']['first_name']) ? $update['message']['from']['first_name'] : 'بازیکن';
                $forward_text = "👤 {$sender_name}: {$text}";
                
                // دکمه‌های واکنش
                $reaction_keyboard = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '👍', 'callback_data' => "reaction:{$update['message']['message_id']}:like"],
                            ['text' => '👎', 'callback_data' => "reaction:{$update['message']['message_id']}:dislike"],
                            ['text' => '❤️', 'callback_data' => "reaction:{$update['message']['message_id']}:love"],
                            ['text' => '😂', 'callback_data' => "reaction:{$update['message']['message_id']}:laugh"],
                            ['text' => '😮', 'callback_data' => "reaction:{$update['message']['message_id']}:wow"]
                        ],
                        [
                            ['text' => '😢', 'callback_data' => "reaction:{$update['message']['message_id']}:sad"],
                            ['text' => '😡', 'callback_data' => "reaction:{$update['message']['message_id']}:angry"],
                            ['text' => '👏', 'callback_data' => "reaction:{$update['message']['message_id']}:clap"],
                            ['text' => '🔥', 'callback_data' => "reaction:{$update['message']['message_id']}:fire"],
                            ['text' => '🎉', 'callback_data' => "reaction:{$update['message']['message_id']}:party"]
                        ]
                    ]
                ]);
                
                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $recipient_id, $forward_text, $reaction_keyboard);
                echo "پیام از کاربر {$user_id} به کاربر {$recipient_id} ارسال شد\n";
                continue;
            }
            
            // پردازش دستور /cancel
            if ($text === '/cancel') {
                echo "دستور cancel دریافت شد - در حال حذف بازی‌های در انتظار...\n";
                
                // حذف بازی‌های در انتظار
                try {
                    $deleted = \Application\Model\DB::table('matches')
                        ->where(['player1' => $user_id, 'status' => 'pending'])
                        ->delete();
                    
                    $response_text = "✅ جستجوی بازیکن لغو شد.";
                    
                    // ارسال پاسخ
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                    echo "پاسخ ارسال شد: {$response_text}\n";
                } catch (Exception $e) {
                    echo "خطا: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در لغو جستجو: " . $e->getMessage());
                }
            }
            
            // پاسخ به دکمه بازی با ناشناس
            else if (strpos($text, 'بازی با ناشناس') !== false) {
                try {
                    // ارسال پیام در حال یافتن بازیکن - دقیقاً متن اصلی
                    $response_text = "در حال یافتن بازیکن 🕔\n\nبرای لغو جستجو، دستور /cancel را ارسال کنید.";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                    echo "پاسخ ارسال شد: {$response_text}\n";
                    
                    // ثبت در پایگاه داده بازی جدید در وضعیت pending
                    $helper = new application\controllers\HelperController();
                    \Application\Model\DB::table('matches')->insert([
                        'player1' => $user_id, 
                        'player1_hash' => $helper->Hash(), 
                        'type' => 'anonymous',
                        'created_at' => date('Y-m-d H:i:s')
                        // ستون last_action_time در دیتابیس وجود ندارد
                    ]);
                    
                    echo "بازی جدید در وضعیت pending ایجاد شد\n";
                } catch (Exception $e) {
                    echo "خطا در ایجاد بازی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در ایجاد بازی: " . $e->getMessage());
                }
            }
            
            // شرکت در مسابقه
            else if (strpos($text, 'شرکت در مسابقه') !== false) {
                $response_text = "cooming soon ..."; // عینا از متن اصلی
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                echo "پاسخ ارسال شد: {$response_text}\n";
            }
            
            // حساب کاربری
            else if (strpos($text, 'حساب کاربری') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->select('*')->first();
                    if (!$userExtra) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات اضافی کاربر");
                        echo "خطا: اطلاعات اضافی کاربر یافت نشد\n";
                        return;
                    }
                    
                    // محاسبه رتبه کاربر - ساده‌سازی شده
                    $match_rank = 1; // فرض
                    $winRate_rank = 1; // فرض
                    
                    // بررسی دوستان (با در نظر گرفتن مقادیر خالی)
                    $friends = isset($userExtra['friends']) ? json_decode($userExtra['friends'], true) : null;
                    $friends_count = is_array($friends) ? count($friends) : 0;
                    
                    // اطمینان از وجود سایر مقادیر
                    $matches = isset($userExtra['matches']) ? $userExtra['matches'] : 0;
                    $win_rate = isset($userExtra['win_rate']) ? strval(number_format($userExtra['win_rate'], 2)) . "%" : "0%";
                    $cups = isset($userExtra['cups']) ? $userExtra['cups'] : 0;
                    $doz_coin = isset($userExtra['doz_coin']) ? $userExtra['doz_coin'] : 0;
                    
                    // ساخت متن پاسخ
                    $message = "
🪪 حساب کاربری شما به شرح زیر میباشد :

 🆔 نام کاربری :      /{$userData['username']}
🔢 آیدی عددی :      {$userData['telegram_id']}

🎮 تعداد بازیهای انجام شده:      {$matches}
🔆 رتبه تعداد بازی بین کاربران:     {$match_rank}

➗ درصد برد در کل بازیها:     {$win_rate}
〽️ رتبه درصد برد بین کاربران:     {$winRate_rank}

🥇 تعداد قهرمانی در مسابقه: coming soon
🎊 رتبه قهرمانی در مسابقه: coming soon

🏆 موجودی جام:     {$cups}
 💎 موجودی دلتاکوین:     {$doz_coin}

👥 تعداد دوستان:     {$friends_count}
⏰ تاریخ و ساعت ورود:     {$userData['created_at']}
";
                    
                    // ایجاد کیبورد مخصوص حساب کاربری
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📝 پروفایل'], ['text' => '🏆 وضعیت زیرمجموعه ها']],
                            [['text' => '📝 تغییر نام کاربری']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    echo "اطلاعات حساب کاربری ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در دریافت اطلاعات کاربر: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات: " . $e->getMessage());
                }
            }
            
            // نفرات برتر
            else if (strpos($text, 'نفرات برتر') !== false) {
                $keyboard = json_encode([
                    'keyboard' => [
                        [['text' => 'نفرات برتر در درصد برد'], ['text' => 'نفرات برتر در تعداد جام']],
                        [['text' => 'نفرات برتر در تعداد بازی'], ['text' => 'نفرات برتر مسابقات هفتگی']],
                        [['text' => 'لغو ❌']]
                    ],
                    'resize_keyboard' => true
                ]);
                
                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "🏆 لیست نفرات برتر برحسب کدام دسته بندی ارسال شود ؟", $keyboard);
                echo "منوی نفرات برتر ارسال شد\n";
            }
            
            // دوستان
            else if (strpos($text, 'دوستان') !== false) {
                $keyboard = json_encode([
                    'keyboard' => [
                        [['text' => 'لیست دوستان'], ['text' => 'افزودن دوست']],
                        [['text' => 'درخواست های دوستی'], ['text' => 'لغو ❌']]
                    ],
                    'resize_keyboard' => true
                ]);
                
                sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "با استفاده از دکمه های زیر بخش مورد نظر را انتخاب کنید👇", $keyboard);
                echo "منوی دوستان ارسال شد\n";
            }
            
            // کسب درآمد
            else if (strpos($text, 'کسب درآمد') !== false) {
                $message = "شما میتوانید با ربات ما کسب درآمد کنید ، حالا چطوری ⁉️

💸 روش های کسب درآمد در ربات : 

1️⃣ ساده ترین روش کسب درآمد بازی کردن در ربات است . شما در قسمت بازی با ناشناس میتوانید به ازای هر بُرد 0.2 دلتا کوین دریافت کنید، توجه داشته باشید که به ازای هر باخت در این قسمت 0.1 دلتا کوین از دست میدهید. 
2️⃣ این روش از طریق زیرمجموعه گیری ممکن است. در این روش با کلیک بر روی دکمه زیرمجموعه گیری بنر و لینک اختصاصی خود را دریافت میکنید و به دوستانتان ارسال میکنید، به ازای هر دعوت از طریق لینک شما 2 دلتا کوین دریافت میکنید.
3️⃣ روش سوم هنوز در ربات اعمال نشده است. در این روش از طریق شرکت در مسابقات ربات که در قسمت تورنومنت ها، جوایز بُرد هر بازی مشخص شده است ، میتوانید به جوایز ارزنده ای دست یابید.

‼️ توجه : ارزش هر دلتا کوین ، هزار تومن میباشد
1 دلتا کوین = 1000 تومن
0.1 دلتا کوین = 100 تومن";
                
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                echo "اطلاعات کسب درآمد ارسال شد\n";
            }
            
            // پشتیبانی
            else if (strpos($text, 'پشتیبانی') !== false) {
                $message = "• به بخش پشتیبانی ربات خوشومدی(: 🤍

• سعی بخش پشتیبانی بر این است که تمامی پیام های دریافتی در کمتر از ۱۲ ساعت پاسخ داده شوند، بنابراین تا زمان دریافت پاسخ صبور باشید

• لطفا پیام، سوال، پیشنهاد و یا انتقاد خود را در قالب یک پیام واحد و بدون احوالپرسی و ... ارسال کنید 👇🏻

👨‍💻 @Doz_Sup";
                
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                echo "اطلاعات پشتیبانی ارسال شد\n";
            }
            
            // راهنما
            else if (strpos($text, 'راهنما') !== false) {
                $message = "🎮 نحوه بازی : 
1️⃣ با انتخاب هر دکمه ( 1 تا 7 ) یک مهره داخل ستون مربوطه می افتد و در پایین ترین محل خالی قرار میگیرد. 

2️⃣ دو نفر به نوبت بازی میکنند و به یک بازیکن رنگ 🔵 و بازیکن دیگر رنگ 🔴 اختصاص داده میشود.

3️⃣ بازیکنان باید تلاش کنند تا 4 مهره از رنگ خود را به صورت عمودی، افقی یا مایل مانند شکل زیر ردیف کنند.

به 3 مثال زیر توجه کنید :

1- برنده : آبی    روش: افقی
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️🔴⚪️⚪️⚪️
⚪️🔵🔵🔵🔵⚪️⚪️
⚪️🔴🔴🔴🔵⚪️⚪️
1️⃣2️⃣3️⃣4️⃣5️⃣6️⃣7️⃣

2- برنده : قرمز     روش: مایل
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️🔴
⚪️⚪️⚪️⚪️⚪️🔴🔵
⚪️⚪️⚪️⚪️🔴🔵🔴
🔴⚪️🔵🔴🔵🔵🔵
1️⃣2️⃣3️⃣4️⃣5️⃣6️⃣7️⃣

3- برنده : آبی      روش: عمودی
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️⚪️⚪️⚪️⚪️
⚪️⚪️⚪️🔵⚪️⚪️⚪️
⚪️⚪️⚪️🔵🔴⚪️⚪️
⚪️⚪️⚪️🔵🔴⚪️⚪️
⚪️⚪️⚪️🔵🔴⚪️⚪️
1️⃣2️⃣3️⃣4️⃣5️⃣6️⃣7️⃣

دو سه بار بازی کنی قلق کار دستت میاد ❤️‍🔥
بازی خوبی داشته باشی 🫂";
                
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                echo "اطلاعات راهنما ارسال شد\n";
            }

            // تغییر نام کاربری
            else if (strpos($text, 'تغییر نام کاربری') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // دریافت اطلاعات اضافی کاربر
                    $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->select('*')->first();
                    if (!$userExtra) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات اضافی کاربر");
                        echo "خطا: اطلاعات اضافی کاربر یافت نشد\n";
                        return;
                    }
                    
                    // بررسی موجودی دلتا کوین
                    $delta_coins = isset($userExtra['delta_coins']) ? $userExtra['delta_coins'] : 0;
                    
                    // ارسال پیام درخواست تغییر نام کاربری
                    $message = "شما میتوانید با 10 دلتاکوین نام کاربری خود را عوض کنید\nچنانچه قصد تغییر آن را دارید، نام کاربری جدیدتان را ارسال کنید\n";
                    $message .= "نام کاربری فعلی: /{$userData['username']}\n";
                    
                    if ($delta_coins < 10) {
                        $message .= "\nموجودی شما {$delta_coins} دلتاکوین میباشد. مقدار دلتاکوین موردنیاز جهت تغییر نام کاربری 10 عدد میباشد!";
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                        return;
                    }
                    
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                    // ذخیره وضعیت کاربر در حالت تغییر نام کاربری
                    try {
                        $userState = [
                            'state' => 'change_username',
                            'step' => 'waiting_for_username'
                        ];
                        
                        // ذخیره وضعیت در دیتابیس یا فایل
                        // فعلاً به صورت ساده پیاده‌سازی می‌کنیم
                        file_put_contents(__DIR__ . "/user_states/{$user_id}.json", json_encode($userState));
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                    
                    echo "درخواست تغییر نام کاربری برای کاربر {$user_id} ارسال شد\n";
                } catch (Exception $e) {
                    echo "خطا در پردازش درخواست تغییر نام کاربری: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در پردازش درخواست: " . $e->getMessage());
                }
            }
            
            // پروفایل کاربر
            else if (strpos($text, 'پروفایل') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // دریافت وضعیت تکمیل پروفایل با استفاده از کوئری خام
                    $profiles = \Application\Model\DB::rawQuery(
                        "SELECT * FROM user_profiles WHERE user_id = ?", 
                        [$userData['id']]
                    );
                    $userProfile = !empty($profiles) ? $profiles[0] : null;
                    
                    // پیام‌های راهنمای پروفایل
                    $message = "📝 برای تکمیل پروفایل خود، موارد زیر را تکمیل کنید:";
                    
                    // ساخت کیبورد مخصوص پروفایل
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📷 ارسال عکس پروفایل']],
                            [['text' => '👤 نام'], ['text' => '⚧ جنسیت']],
                            [['text' => '🔢 سن'], ['text' => '✍️ بیوگرافی']],
                            [['text' => '🏙 انتخاب استان'], ['text' => '🏠 انتخاب شهر']],
                            [['text' => '📍 ارسال موقعیت مکانی']],
                            [['text' => '📱 ارسال شماره تلگرام']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    // نمایش وضعیت فعلی پروفایل
                    $status_message = "";
                    if ($userProfile) {
                        $status_message .= "✅ وضعیت تکمیل پروفایل شما:\n\n";
                        $status_message .= isset($userProfile['photo_id']) && !empty($userProfile['photo_id']) ? "✅ عکس پروفایل: ارسال شده\n" : "❌ عکس پروفایل: ارسال نشده\n";
                        $status_message .= isset($userProfile['name']) && !empty($userProfile['name']) ? "✅ نام: {$userProfile['name']}\n" : "❌ نام: تنظیم نشده\n";
                        $status_message .= isset($userProfile['gender']) && !empty($userProfile['gender']) ? "✅ جنسیت: {$userProfile['gender']}\n" : "❌ جنسیت: تنظیم نشده\n";
                        $status_message .= isset($userProfile['age']) && !empty($userProfile['age']) ? "✅ سن: {$userProfile['age']}\n" : "❌ سن: تنظیم نشده\n";
                        $status_message .= isset($userProfile['bio']) && !empty($userProfile['bio']) ? "✅ بیوگرافی: تنظیم شده\n" : "❌ بیوگرافی: تنظیم نشده\n";
                        $status_message .= isset($userProfile['province']) && !empty($userProfile['province']) ? "✅ استان: {$userProfile['province']}\n" : "❌ استان: تنظیم نشده\n";
                        $status_message .= isset($userProfile['city']) && !empty($userProfile['city']) ? "✅ شهر: {$userProfile['city']}\n" : "❌ شهر: تنظیم نشده\n";
                        $status_message .= isset($userProfile['location']) && !empty($userProfile['location']) ? "✅ موقعیت مکانی: ارسال شده\n" : "❌ موقعیت مکانی: ارسال نشده\n";
                        $status_message .= isset($userProfile['phone']) && !empty($userProfile['phone']) ? "✅ شماره تلفن: {$userProfile['phone']}\n" : "❌ شماره تلفن: ارسال نشده\n";
                    } else {
                        $status_message = "❌ شما هنوز پروفایل خود را تکمیل نکرده‌اید.\n\nبا تکمیل پروفایل خود، به بازیکنان دیگر اجازه می‌دهید بیشتر با شما آشنا شوند و همچنین 3 دلتا کوین دریافت می‌کنید!";
                    }
                    
                    // ارسال وضعیت و منوی پروفایل
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $status_message);
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    echo "منوی پروفایل کاربر ارسال شد\n";
                    
                    // ذخیره وضعیت کاربر در حالت پردازش پروفایل
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'menu'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش پروفایل کاربر: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات پروفایل: " . $e->getMessage());
                }
            }
            
            // وضعیت زیرمجموعه ها
            else if (strpos($text, 'وضعیت زیرمجموعه ها') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // دریافت زیرمجموعه‌ها با استفاده از کوئری خام
                    $referrals = \Application\Model\DB::rawQuery(
                        "SELECT * FROM users WHERE refere_id = ?", 
                        [$userData['id']]
                    );
                    
                    if (count($referrals) === 0) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ شما هنوز هیچ زیرمجموعه‌ای ندارید.\n\nبرای دعوت از دوستان خود، از بخش کسب درآمد استفاده کنید.");
                        echo "اطلاعات زیرمجموعه‌ها ارسال شد (بدون زیرمجموعه)\n";
                        return;
                    }
                    
                    // ساخت لیست زیرمجموعه‌ها با دکمه‌ها
                    $referral_buttons = [];
                    foreach ($referrals as $referral) {
                        $referral_buttons[] = [['text' => $referral['username']]];
                    }
                    
                    // اضافه کردن دکمه بازگشت
                    $referral_buttons[] = [['text' => 'لغو ❌']];
                    
                    $keyboard = json_encode([
                        'keyboard' => $referral_buttons,
                        'resize_keyboard' => true
                    ]);
                    
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "📊 لیست زیرمجموعه‌های شما: (روی هر کدام کلیک کنید تا وضعیت پاداش‌ها را ببینید)", $keyboard);
                    echo "لیست زیرمجموعه‌ها ارسال شد\n";
                    
                    // ذخیره وضعیت کاربر در حالت مشاهده زیرمجموعه‌ها
                    try {
                        $userState = [
                            'state' => 'referrals',
                            'step' => 'list'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش زیرمجموعه‌ها: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات زیرمجموعه‌ها: " . $e->getMessage());
                }
            }
            
            // بخش‌های مختلف پروفایل کاربر
            else if (strpos($text, 'ارسال عکس پروفایل') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $message = "لطفاً عکس پروفایل خود را ارسال کنید. این عکس پس از تأیید توسط ادمین در پروفایل شما نمایش داده خواهد شد.";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'photo'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش عکس پروفایل: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // تنظیم نام
            else if (strpos($text, '👤 نام') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $message = "لطفاً نام خود را وارد کنید. نام می‌تواند شامل حروف فارسی یا انگلیسی باشد و حداکثر 30 کاراکتر می‌تواند داشته باشد.";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'name'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش نام: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // تنظیم جنسیت
            else if (strpos($text, '⚧ جنسیت') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // ایجاد کیبورد انتخاب جنسیت
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '👨 پسر'], ['text' => '👧 دختر']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ]);
                    
                    $message = "لطفاً جنسیت خود را انتخاب کنید:";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'gender'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش جنسیت: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // تنظیم سن
            else if (strpos($text, '🔢 سن') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // ایجاد کیبورد انتخاب سن (9 تا 70 سال)
                    $age_buttons = [];
                    $row = [];
                    for ($age = 9; $age <= 70; $age++) {
                        $row[] = ['text' => (string)$age];
                        if (count($row) === 5 || $age === 70) { // 5 تا در هر ردیف
                            $age_buttons[] = $row;
                            $row = [];
                        }
                    }
                    $age_buttons[] = [['text' => 'لغو ❌']];
                    
                    $keyboard = json_encode([
                        'keyboard' => $age_buttons,
                        'resize_keyboard' => true
                    ]);
                    
                    $message = "لطفاً سن خود را انتخاب کنید:";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'age'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش سن: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // تنظیم بیوگرافی
            else if (strpos($text, '✍️ بیوگرافی') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $message = "لطفاً متن بیوگرافی خود را وارد کنید. این متن می‌تواند به زبان فارسی یا انگلیسی باشد و حداکثر 200 کاراکتر می‌تواند داشته باشد. این متن نیاز به تأیید ادمین دارد.";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $message);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'bio'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش بیوگرافی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // انتخاب استان
            else if (strpos($text, '🏙 انتخاب استان') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // لیست استان‌های ایران
                    $provinces = [
                        'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز',
                        'ایلام', 'بوشهر', 'تهران', 'چهارمحال و بختیاری', 'خراسان جنوبی',
                        'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان',
                        'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان',
                        'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان', 'گیلان',
                        'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد'
                    ];
                    
                    // ایجاد کیبورد انتخاب استان
                    $province_buttons = [];
                    foreach ($provinces as $province) {
                        $province_buttons[] = [['text' => $province]];
                    }
                    $province_buttons[] = [['text' => 'ترجیح میدهم نگویم']];
                    $province_buttons[] = [['text' => 'لغو ❌']];
                    
                    $keyboard = json_encode([
                        'keyboard' => $province_buttons,
                        'resize_keyboard' => true
                    ]);
                    
                    $message = "لطفاً استان محل سکونت خود را انتخاب کنید:";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'province'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش استان: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // ارسال موقعیت مکانی
            else if (strpos($text, '📍 ارسال موقعیت مکانی') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // ایجاد کیبورد با دکمه ارسال موقعیت
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📍 ارسال موقعیت', 'request_location' => true]],
                            [['text' => 'ترجیح میدهم نگویم']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ]);
                    
                    $message = "لطفاً موقعیت مکانی خود را با کلیک بر روی دکمه زیر ارسال کنید یا اگر نمی‌خواهید این اطلاعات را ارائه دهید، گزینه «ترجیح میدهم نگویم» را انتخاب کنید:";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'location'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش موقعیت مکانی: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // ارسال شماره تلفن
            else if (strpos($text, '📱 ارسال شماره تلگرام') !== false) {
                try {
                    // دریافت اطلاعات کاربر از دیتابیس
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات کاربری");
                        echo "خطا: کاربر در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    // ایجاد کیبورد با دکمه ارسال شماره تلفن
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '📱 ارسال شماره', 'request_contact' => true]],
                            [['text' => 'ترجیح میدهم نگویم']],
                            [['text' => 'لغو ❌']]
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true
                    ]);
                    
                    $message = "لطفاً شماره تلفن خود را با کلیک بر روی دکمه زیر ارسال کنید یا اگر نمی‌خواهید این اطلاعات را ارائه دهید، گزینه «ترجیح میدهم نگویم» را انتخاب کنید. توجه: فقط برای شماره‌های ایرانی پورسانت تعلق می‌گیرد.";
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $keyboard);
                    
                    // ذخیره وضعیت کاربر
                    try {
                        $userState = [
                            'state' => 'profile',
                            'step' => 'phone'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                    } catch (Exception $e) {
                        echo "خطا در ذخیره وضعیت کاربر: " . $e->getMessage() . "\n";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش شماره تلفن: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا: " . $e->getMessage());
                }
            }
            
            // پردازش ورودی‌های کاربر در حالت‌های مختلف
            else if (isset($update['message']) && 
                   (!isset($update['message']['entities']) || $update['message']['entities'][0]['type'] !== 'bot_command')) {
                try {
                    // اول بررسی شود آیا دکمه لغو زده شده است
                    if ($text === 'لغو ❌') {
                        // برگشت به منوی اصلی
                        $keyboard = json_encode([
                            'keyboard' => [
                                [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                                [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                                [['text' => '👤 حساب کاربری'], ['text' => '🏆نفرات برتر•']],
                                [['text' => '• پشتیبانی👨‍💻'], ['text' => '⁉️راهنما •']]
                            ],
                            'resize_keyboard' => true
                        ]);
                        
                        // پاک کردن وضعیت کاربر
                        $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                        if ($userData) {
                            \Application\Model\DB::rawQuery(
                                "UPDATE users SET state = ? WHERE id = ?", 
                                [json_encode(['state' => '', 'step' => '']), $userData['id']]
                            );
                        }
                        
                        sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, "🎮 منوی اصلی:", $keyboard);
                        echo "برگشت به منوی اصلی\n";
                        continue;
                    }

                    // دریافت اطلاعات کاربر و وضعیت فعلی
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData || !isset($userData['state']) || empty($userData['state'])) {
                        // اگر وضعیتی برای کاربر تعریف نشده، به پیام پاسخ نمی‌دهیم
                        continue;
                    }
                    
                    $userState = json_decode($userData['state'], true);
                    
                    // پردازش ورودی بر اساس وضعیت کاربر
                    if ($userState['state'] === 'referrals' && $userState['step'] === 'list') {
                        // پردازش انتخاب زیرمجموعه از لیست
                        if ($text === 'لغو ❌') {
                            // بازگشت به منوی اصلی
                            $userState = [
                                'state' => '',
                                'step' => ''
                            ];
                            \Application\Model\DB::table('users')
                                ->where('id', $userData['id'])
                                ->update(['state' => json_encode($userState)]);
                            
                            // فراخوانی مجدد منوی اصلی
                            $text = "👤 حساب کاربری";
                            break;
                        }
                        
                        // جستجوی کاربر انتخاب شده در میان زیرمجموعه‌ها
                        $referral = \Application\Model\DB::rawQuery(
                            "SELECT * FROM users WHERE username = ? AND refere_id = ?", 
                            [$text, $userData['id']]
                        );
                        $referral = !empty($referral) ? $referral[0] : null;
                        
                        if (!$referral) {
                            sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ کاربر انتخاب شده در میان زیرمجموعه‌های شما یافت نشد.");
                            continue;
                        }
                        
                        // دریافت اطلاعات وضعیت این زیرمجموعه
                        $referralStatus = \Application\Model\DB::table('referral_status')
                            ->where('user_id', $referral['id'])
                            ->first();
                        
                        // اگر اطلاعات وضعیت زیرمجموعه وجود نداشت، مقادیر پیش‌فرض را در نظر می‌گیریم
                        $started_bot = false;
                        $won_one_game = false;
                        $completed_profile = false;
                        $won_thirty_games = false;
                        
                        if ($referralStatus) {
                            $started_bot = $referralStatus['started_bot'] ?? false;
                            $won_one_game = $referralStatus['won_one_game'] ?? false;
                            $completed_profile = $referralStatus['completed_profile'] ?? false;
                            $won_thirty_games = $referralStatus['won_thirty_games'] ?? false;
                        }
                        
                        // شمارش تعداد بازی‌های برنده شده توسط زیرمجموعه
                        $wins = \Application\Model\DB::table('matches')
                            ->where(function($q) use ($referral) {
                                $q->where('player1', $referral['id'])
                                  ->where('winner', 1);
                            })
                            ->orWhere(function($q) use ($referral) {
                                $q->where('player2', $referral['id'])
                                  ->where('winner', 2);
                            })
                            ->count();
                        
                        // بررسی تکمیل پروفایل زیرمجموعه
                        $profile = \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $referral['id'])
                            ->first();
                        
                        $profile_completed = false;
                        if ($profile) {
                            // بررسی تکمیل شدن فیلدهای اصلی پروفایل
                            $profile_completed = 
                                isset($profile['name']) && !empty($profile['name']) &&
                                isset($profile['gender']) && !empty($profile['gender']) &&
                                isset($profile['age']) && !empty($profile['age']) &&
                                isset($profile['bio']) && !empty($profile['bio']);
                        }
                        
                        // بروزرسانی وضعیت زیرمجموعه
                        if ($started_bot === false) {
                            $started_bot = true;
                            
                            // اگر رکورد وضعیت وجود نداشت، آن را ایجاد می‌کنیم
                            if (!$referralStatus) {
                                \Application\Model\DB::table('referral_status')->insert([
                                    'user_id' => $referral['id'],
                                    'refere_id' => $userData['id'],
                                    'started_bot' => true,
                                    'won_one_game' => $wins >= 1,
                                    'completed_profile' => $profile_completed,
                                    'won_thirty_games' => $wins >= 30
                                ]);
                            } else {
                                \Application\Model\DB::table('referral_status')
                                    ->where('user_id', $referral['id'])
                                    ->update([
                                        'started_bot' => true,
                                        'won_one_game' => $wins >= 1,
                                        'completed_profile' => $profile_completed,
                                        'won_thirty_games' => $wins >= 30
                                    ]);
                            }
                            
                            // اضافه کردن پاداش 0.5 دلتا کوین به کاربر
                            \Application\Model\DB::table('users_extra')
                                ->where('user_id', $userData['id'])
                                ->increment('doz_coin', 0.5);
                        }
                        
                        // بروزرسانی برد یک بازی
                        if ($won_one_game === false && $wins >= 1) {
                            \Application\Model\DB::table('referral_status')
                                ->where('user_id', $referral['id'])
                                ->update(['won_one_game' => true]);
                            
                            // اضافه کردن پاداش 1.5 دلتا کوین به کاربر
                            \Application\Model\DB::table('users_extra')
                                ->where('user_id', $userData['id'])
                                ->increment('doz_coin', 1.5);
                        }
                        
                        // بروزرسانی تکمیل پروفایل
                        if ($completed_profile === false && $profile_completed) {
                            \Application\Model\DB::table('referral_status')
                                ->where('user_id', $referral['id'])
                                ->update(['completed_profile' => true]);
                            
                            // اضافه کردن پاداش 3 دلتا کوین به کاربر
                            \Application\Model\DB::table('users_extra')
                                ->where('user_id', $userData['id'])
                                ->increment('doz_coin', 3);
                        }
                        
                        // بروزرسانی برد 30 بازی
                        if ($won_thirty_games === false && $wins >= 30) {
                            \Application\Model\DB::table('referral_status')
                                ->where('user_id', $referral['id'])
                                ->update(['won_thirty_games' => true]);
                            
                            // اضافه کردن پاداش 5 دلتا کوین به کاربر
                            \Application\Model\DB::table('users_extra')
                                ->where('user_id', $userData['id'])
                                ->increment('doz_coin', 5);
                        }
                        
                        // ساخت متن وضعیت زیرمجموعه
                        $referral_status_text = "📊 وضعیت زیرمجموعه: {$referral['username']}\n\n";
                        $referral_status_text .= "وضعیت استارت ربات (0.5 دلتا کوین): " . ($started_bot ? "✅ انجام شده" : "❌ انجام نشده") . "\n";
                        $referral_status_text .= "وضعیت کسب 1 برد (1.5 دلتا کوین): " . ($won_one_game ? "✅ انجام شده" : "❌ انجام نشده") . "\n";
                        $referral_status_text .= "وضعیت تکمیل پروفایل (3 دلتا کوین): " . ($completed_profile ? "✅ انجام شده" : "❌ انجام نشده") . "\n";
                        $referral_status_text .= "وضعیت کسب 30 برد (5 دلتا کوین): " . ($won_thirty_games ? "✅ انجام شده" : "❌ انجام نشده") . "\n\n";
                        $referral_status_text .= "تعداد کل بردهای کاربر: {$wins}";
                        
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $referral_status_text);
                        continue;
                    }
                    else if ($userState['state'] === 'profile') {
                        switch ($userState['step']) {
                            case 'name':
                                if (strlen($text) > 30) {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ نام شما نباید بیشتر از 30 کاراکتر باشد. لطفاً دوباره تلاش کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره نام در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['name' => $text]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'name' => $text
                                    ]);
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ نام شما با موفقیت به «{$text}» تغییر یافت.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                                
                            case 'gender':
                                // پردازش انتخاب جنسیت (پسر/دختر)
                                $gender = '';
                                if (strpos($text, 'پسر') !== false) {
                                    $gender = 'male';
                                } else if (strpos($text, 'دختر') !== false) {
                                    $gender = 'female';
                                } else {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ لطفاً یکی از گزینه‌های موجود را انتخاب کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره جنسیت در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['gender' => $gender]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'gender' => $gender
                                    ]);
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                $gender_text = ($gender === 'male') ? 'پسر' : 'دختر';
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ جنسیت شما به «{$gender_text}» تنظیم شد.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                                
                            case 'age':
                                // پردازش انتخاب سن
                                $age = intval($text);
                                if ($age < 9 || $age > 70) {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ لطفاً سن خود را بین 9 تا 70 سال انتخاب کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره سن در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['age' => $age]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'age' => $age
                                    ]);
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ سن شما به {$age} سال تنظیم شد.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                                
                            case 'province':
                                // لیست استان‌های ایران
                                $provinces = [
                                    'آذربایجان شرقی', 'آذربایجان غربی', 'اردبیل', 'اصفهان', 'البرز',
                                    'ایلام', 'بوشهر', 'تهران', 'چهارمحال و بختیاری', 'خراسان جنوبی',
                                    'خراسان رضوی', 'خراسان شمالی', 'خوزستان', 'زنجان', 'سمنان',
                                    'سیستان و بلوچستان', 'فارس', 'قزوین', 'قم', 'کردستان',
                                    'کرمان', 'کرمانشاه', 'کهگیلویه و بویراحمد', 'گلستان', 'گیلان',
                                    'لرستان', 'مازندران', 'مرکزی', 'هرمزگان', 'همدان', 'یزد'
                                ];
                                
                                // بررسی معتبر بودن استان انتخاب شده
                                if (!in_array($text, $provinces) && $text !== 'ترجیح میدهم نگویم') {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ لطفاً یکی از استان‌های موجود در لیست را انتخاب کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره استان در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['province' => $text]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'province' => $text
                                    ]);
                                }
                                
                                // اگر کاربر استان را انتخاب کرده، مرحله بعدی انتخاب شهر است
                                if ($text !== 'ترجیح میدهم نگویم') {
                                    // به کاربر نمایش میدهیم که استان ذخیره شده و حالا باید شهر را انتخاب کند
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ استان شما به «{$text}» تنظیم شد.");
                                    
                                    // ذخیره وضعیت کاربر برای انتخاب شهر
                                    $userState = [
                                        'state' => 'profile',
                                        'step' => 'city',
                                        'province' => $text
                                    ];
                                    \Application\Model\DB::table('users')
                                        ->where('id', $userData['id'])
                                        ->update(['state' => json_encode($userState)]);
                                    
                                    // بازگرداندن به منوی انتخاب شهر
                                    $text = "🏠 انتخاب شهر";
                                } else {
                                    // اگر کاربر نخواهد استان را مشخص کند، به منوی پروفایل برمی‌گردیم
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ انتخاب شما ثبت شد.");
                                    
                                    // بازگشت به منوی پروفایل
                                    $userState = [
                                        'state' => 'profile',
                                        'step' => 'menu'
                                    ];
                                    \Application\Model\DB::table('users')
                                        ->where('id', $userData['id'])
                                        ->update(['state' => json_encode($userState)]);
                                    
                                    // بازگرداندن به منوی پروفایل
                                    $text = "📝 پروفایل";
                                }
                                break;
                                
                            case 'city':
                                // ذخیره شهر در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['city' => $text]);
                                } else {
                                    // این حالت نباید رخ دهد، زیرا پیش از این، استان را ذخیره کرده‌ایم
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'city' => $text
                                    ]);
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ شهر شما به «{$text}» تنظیم شد.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                                
                            case 'bio':
                                if (strlen($text) > 200) {
                                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "❌ بیوگرافی شما نباید بیشتر از 200 کاراکتر باشد. لطفاً دوباره تلاش کنید.");
                                    continue 2;
                                }
                                
                                // ذخیره بیوگرافی در پروفایل کاربر
                                $profileExists = \Application\Model\DB::table('user_profiles')
                                    ->where('user_id', $userData['id'])
                                    ->exists();
                                
                                if ($profileExists) {
                                    \Application\Model\DB::table('user_profiles')
                                        ->where('user_id', $userData['id'])
                                        ->update(['bio' => $text, 'bio_approved' => false]);
                                } else {
                                    \Application\Model\DB::table('user_profiles')->insert([
                                        'user_id' => $userData['id'],
                                        'bio' => $text,
                                        'bio_approved' => false
                                    ]);
                                }
                                
                                // ارسال بیوگرافی به کانال ادمین
                                $admin_channel_id = "-100123456789"; // آیدی کانال ادمین را قرار دهید
                                try {
                                    $admin_message = "✅ درخواست تأیید بیوگرافی:\n\nکاربر: {$userData['username']}\nآیدی: {$userData['telegram_id']}\n\nبیوگرافی:\n$text";
                                    
                                    $admin_keyboard = json_encode([
                                        'inline_keyboard' => [
                                            [
                                                ['text' => '✅ تأیید', 'callback_data' => "approve_bio:{$userData['id']}"],
                                                ['text' => '❌ رد', 'callback_data' => "reject_bio:{$userData['id']}"]
                                            ]
                                        ]
                                    ]);
                                    
                                    // sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $admin_channel_id, $admin_message, $admin_keyboard);
                                } catch (Exception $e) {
                                    echo "خطا در ارسال بیوگرافی به کانال ادمین: " . $e->getMessage() . "\n";
                                }
                                
                                // بازگشت به منوی پروفایل
                                $userState = [
                                    'state' => 'profile',
                                    'step' => 'menu'
                                ];
                                \Application\Model\DB::table('users')
                                    ->where('id', $userData['id'])
                                    ->update(['state' => json_encode($userState)]);
                                
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ بیوگرافی شما با موفقیت ثبت شد و در انتظار تأیید ادمین است.");
                                // بازگرداندن به منوی پروفایل
                                $text = "📝 پروفایل";
                                break;
                        }
                    }
                    
                } catch (Exception $e) {
                    echo "خطا در پردازش ورودی کاربر: " . $e->getMessage() . "\n";
                }
            }
            
            // دکمه ترجیح میدهم نگویم
            else if ($text === 'ترجیح میدهم نگویم') {
                try {
                    // دریافت اطلاعات کاربر و وضعیت فعلی
                    $userData = \Application\Model\DB::table('users')->where('telegram_id', $user_id)->select('*')->first();
                    
                    if (!$userData || !isset($userData['state']) || empty($userData['state'])) {
                        continue;
                    }
                    
                    $userState = json_decode($userData['state'], true);
                    
                    // بررسی وضعیت کاربر
                    if ($userState['state'] === 'profile') {
                        $field = '';
                        $value = 'prefer_not_to_say';
                        
                        switch ($userState['step']) {
                            case 'province':
                                $field = 'province';
                                break;
                            case 'location':
                                $field = 'location';
                                break;
                            case 'phone':
                                $field = 'phone';
                                break;
                            default:
                                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "این گزینه در این مرحله قابل استفاده نیست.");
                                return;
                        }
                        
                        // ثبت ترجیح ندادن به ارائه اطلاعات
                        $profileExists = \Application\Model\DB::table('user_profiles')
                            ->where('user_id', $userData['id'])
                            ->exists();
                        
                        if ($profileExists) {
                            \Application\Model\DB::table('user_profiles')
                                ->where('user_id', $userData['id'])
                                ->update([$field => $value]);
                        } else {
                            \Application\Model\DB::table('user_profiles')->insert([
                                'user_id' => $userData['id'],
                                $field => $value
                            ]);
                        }
                        
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "✅ انتخاب شما ثبت شد.");
                        
                        // بازگشت به منوی پروفایل
                        $userState = [
                            'state' => 'profile',
                            'step' => 'menu'
                        ];
                        \Application\Model\DB::table('users')
                            ->where('id', $userData['id'])
                            ->update(['state' => json_encode($userState)]);
                        
                        // بازگرداندن به منوی پروفایل
                        $text = "📝 پروفایل";
                    }
                } catch (Exception $e) {
                    echo "خطا در پردازش ترجیح ندادن به ارائه اطلاعات: " . $e->getMessage() . "\n";
                }
            }
            
            // دکمه لغو (قبلاً به بخش دیگری منتقل شده است)
            else if ($text === 'لغو ❌') {
                // این قسمت دیگر اجرا نمی‌شود و در ابتدای پردازش پیام‌ها قرار گرفته است
                echo "این قسمت دیگر استفاده نمی‌شود.\n";
            }
            
            // پاسخ به دستور /username (نمایش مشخصات کاربر)
            else if (strpos($text, '/') === 0 && $text !== '/start' && $text !== '/cancel') {
                try {
                    // حذف اسلش از ابتدای نام کاربری
                    $username = ltrim($text, '/');
                    
                    // جستجوی کاربر بر اساس نام کاربری
                    $userData = \Application\Model\DB::table('users')->where('username', $username)->select('*')->first();
                    
                    if (!$userData) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ کاربری با این نام کاربری یافت نشد!");
                        echo "خطا: کاربر {$username} در دیتابیس یافت نشد\n";
                        return;
                    }
                    
                    $userExtra = \Application\Model\DB::table('users_extra')->where('user_id', $userData['id'])->select('*')->first();
                    if (!$userExtra) {
                        sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات اضافی کاربر");
                        echo "خطا: اطلاعات اضافی کاربر {$username} یافت نشد\n";
                        return;
                    }
                    
                    // آماده‌سازی اطلاعات کاربر برای نمایش
                    $win_rate = isset($userExtra['win_rate']) ? strval(number_format($userExtra['win_rate'], 2)) . "%" : "0%";
                    $cups = isset($userExtra['cups']) ? $userExtra['cups'] : 0;
                    $matches = isset($userExtra['matches']) ? $userExtra['matches'] : 0;
                    
                    // ساخت متن پاسخ
                    $message = "
🪪 اطلاعات کاربر {$userData['username']} :

🎮 تعداد بازی‌های انجام شده: {$matches}
➗ درصد برد: {$win_rate}
🏆 تعداد جام: {$cups}
                    ";
                    
                    // ایجاد دکمه درخواست دوستی
                    $inlineKeyboard = json_encode([
                        'inline_keyboard' => [
                            [
                                ['text' => '👥 درخواست دوستی', 'callback_data' => "friend_request:{$userData['id']}"]
                            ]
                        ]
                    ]);
                    
                    // ارسال پیام با دکمه درخواست دوستی
                    sendMessageWithKeyboard($_ENV['TELEGRAM_TOKEN'], $chat_id, $message, $inlineKeyboard);
                    echo "اطلاعات کاربر {$username} ارسال شد\n";
                    
                } catch (Exception $e) {
                    echo "خطا در دریافت اطلاعات کاربر {$username}: " . $e->getMessage() . "\n";
                    sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, "⚠️ خطا در دریافت اطلاعات: " . $e->getMessage());
                }
            }
            
            // پاسخ به دستور /start
            else if (strpos($text, '/start') === 0) {
                $first_name = isset($update['message']['from']['first_name']) ? $update['message']['from']['first_name'] : 'کاربر';
                
                // دقیقاً متن اصلی از فایل locale
                $response_text = "سلااام {$first_name} عزیززز به ربات بازی ما خوشومدییی❤️‍🔥

قراره اینجا کلی خوشبگذره بهت😼

با افراد ناشناس بازی کنی و دوست پیدا کنی 😁

تمرین کنی و قوی شی مسابقاتمون شرکت کنی و جایزه برنده شیی 😻

با رفیقات بازی کنی و ببینی کدومتون قوی و باهوش هستید 😹

همین حالا با استفاده از دکمه های زیر از ربات استفاده کن و لذت ببرر👇";
                
                // ارسال پاسخ
                sendMessage($_ENV['TELEGRAM_TOKEN'], $chat_id, $response_text);
                echo "پاسخ ارسال شد: {$response_text}\n";
                
                // ارسال مجدد منوی اصلی - اختیاری
                try {
                    $keyboard = json_encode([
                        'keyboard' => [
                            [['text' => '👀 بازی با ناشناس'], ['text' => '🏆شرکت در مسابقه 8 نفره + جایزه🎁']],
                            [['text' => '👥 دوستان'], ['text' => '💸 کسب درآمد 💸']],
                            [['text' => '👤 حساب کاربری'], ['text' => '🏆نفرات برتر•']],
                            [['text' => '• پشتیبانی👨‍💻'], ['text' => '⁉️راهنما •']]
                        ],
                        'resize_keyboard' => true
                    ]);
                    
                    $url = "https://api.telegram.org/bot{$_ENV['TELEGRAM_TOKEN']}/sendMessage";
                    $params = [
                        'chat_id' => $chat_id,
                        'text' => '🎮 منوی اصلی:',
                        'reply_markup' => $keyboard
                    ];
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $result = curl_exec($ch);
                    curl_close($ch);
                    
                    echo "کیبورد ارسال شد!\n";
                } catch (Exception $e) {
                    echo "خطا در ارسال کیبورد: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

/**
 * دریافت آپدیت‌ها از API تلگرام
 */
function getUpdatesViaFopen($token, $offset = 0) {
    $url = "https://api.telegram.org/bot{$token}/getUpdates";
    $params = [
        'offset' => $offset,
        'timeout' => 1,
        'limit' => 10,
        'allowed_updates' => json_encode(["message", "callback_query"])
    ];
    
    $url .= '?' . http_build_query($params);
    
    $response = @file_get_contents($url);
    if ($response === false) {
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * ارسال پیام به کاربر
 */
function sendMessage($token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $params = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    
    $url .= '?' . http_build_query($params);
    return file_get_contents($url);
}

/**
 * ارسال پیام با کیبورد به کاربر
 */
function sendMessageWithKeyboard($token, $chat_id, $text, $keyboard) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => $keyboard
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * پاسخ به callback_query از دکمه‌های inline
 */
function answerCallbackQuery($token, $callback_query_id, $text = null, $show_alert = false) {
    $url = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
    $params = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    
    if ($text !== null) {
        $params['text'] = $text;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * ویرایش متن پیام
 */
function editMessageText($token, $chat_id, $message_id, $text, $reply_markup = null) {
    $url = "https://api.telegram.org/bot{$token}/editMessageText";
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text
    ];
    
    if ($reply_markup !== null) {
        $params['reply_markup'] = $reply_markup;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * محاسبه و تولید متن تایمر برای بازیکن
 * این تایمر زیر نام کاربر نمایش داده می‌شود
 */
function generatePlayerTimer($last_action_time) {
    // اگر زمان آخرین کنش صفر یا خالی باشد
    if (empty($last_action_time)) {
        return "⏱️ زمان: 00:00";
    }
    
    // تبدیل به تایم‌استمپ
    $last_action_timestamp = strtotime($last_action_time);
    $current_timestamp = time();
    
    // محاسبه تفاوت زمانی (به ثانیه)
    $time_diff = $current_timestamp - $last_action_timestamp;
    
    // اگر تفاوت زمانی منفی باشد (که نباید باشد)
    if ($time_diff < 0) {
        $time_diff = 0;
    }
    
    // تبدیل به دقیقه و ثانیه
    $minutes = floor($time_diff / 60);
    $seconds = $time_diff % 60;
    
    // قالب‌بندی متن تایمر
    return sprintf("⏱️ زمان: %02d:%02d", $minutes, $seconds);
}

/**
 * یافتن بازی فعال برای کاربر
 * 
 * @param int $user_id شناسه کاربر
 * @return array|null اطلاعات بازی فعال یا null اگر بازی فعالی وجود نداشته باشد
 */
function getActiveMatchForUser($user_id) {
    try {
        // استفاده از متد rawQuery
        $results = \Application\Model\DB::rawQuery(
            "SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND status = 'active' LIMIT 1", 
            [$user_id, $user_id]
        );
        
        // بررسی وجود نتیجه
        if (count($results) > 0) {
            return $results[0];
        }
        
        return null;
    } catch (Exception $e) {
        echo "خطا در یافتن بازی فعال: " . $e->getMessage() . "\n";
        return null;
    }
}
?>