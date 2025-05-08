<?php
namespace application\controllers;

use Application\Model\DB;

class ChatController
{
    private $telegram_id;
    
    public function __construct($telegram_id)
    {
        $this->telegram_id = $telegram_id;
    }
    
    /**
     * تنظیم چت بعد از بازی
     * @param int $match_id شناسه بازی
     * @return array نتیجه عملیات
     */
    public function setupPostGameChat($match_id)
    {
        try {
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $match_id)
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی وضعیت بازی
            if ($match['status'] !== 'completed') {
                return [
                    'success' => false,
                    'message' => 'بازی هنوز به پایان نرسیده است.'
                ];
            }
            
            // بررسی وجود چت قبلی
            $existingChat = DB::table('post_game_chats')
                ->where('match_id', $match_id)
                ->first();
                
            if ($existingChat) {
                // بازگرداندن اطلاعات چت موجود
                return [
                    'success' => true,
                    'chat_id' => $existingChat['id'],
                    'match_id' => $match_id,
                    'chat_end_time' => $existingChat['chat_end_time'],
                    'extended' => $existingChat['extended'],
                    'remaining_seconds' => max(0, strtotime($existingChat['chat_end_time']) - time())
                ];
            }
            
            // دریافت زمان چت از تنظیمات
            $chatTime = $this->getChatTime();
            
            // محاسبه زمان پایان چت
            $endTime = date('Y-m-d H:i:s', time() + $chatTime);
            
            // ایجاد چت جدید
            $chatId = DB::table('post_game_chats')->insert([
                'match_id' => $match_id,
                'chat_end_time' => $endTime,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // تنظیم وضعیت چت برای هر دو کاربر
            DB::table('chat_status')->insert([
                'match_id' => $match_id,
                'user_id' => $match['player1'],
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            DB::table('chat_status')->insert([
                'match_id' => $match_id,
                'user_id' => $match['player2'],
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'chat_id' => $chatId,
                'match_id' => $match_id,
                'chat_end_time' => $endTime,
                'extended' => false,
                'remaining_seconds' => $chatTime
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در تنظیم چت بعد از بازی: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در تنظیم چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * بررسی وضعیت چت
     * @param int $match_id شناسه بازی
     * @return array وضعیت چت
     */
    public function getChatStatus($match_id)
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $match_id)
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی مشارکت کاربر در بازی
            if ($match['player1'] != $user['id'] && $match['player2'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما در این بازی شرکت نکرده‌اید.'
                ];
            }
            
            // دریافت اطلاعات چت
            $chat = DB::table('post_game_chats')
                ->where('match_id', $match_id)
                ->first();
                
            if (!$chat) {
                return [
                    'success' => false,
                    'message' => 'چت برای این بازی تنظیم نشده است.',
                    'chat_exists' => false
                ];
            }
            
            // بررسی زمان پایان چت
            $now = time();
            $endTime = strtotime($chat['chat_end_time']);
            $isExpired = ($now >= $endTime);
            
            // دریافت وضعیت چت کاربر
            $userChatStatus = DB::table('chat_status')
                ->where('match_id', $match_id)
                ->where('user_id', $user['id'])
                ->first();
                
            $isActive = $userChatStatus ? $userChatStatus['is_active'] : false;
            
            // دریافت وضعیت چت حریف
            $opponentId = ($match['player1'] == $user['id']) ? $match['player2'] : $match['player1'];
            $opponentChatStatus = DB::table('chat_status')
                ->where('match_id', $match_id)
                ->where('user_id', $opponentId)
                ->first();
                
            $opponentActive = $opponentChatStatus ? $opponentChatStatus['is_active'] : false;
            
            // دریافت اطلاعات حریف
            $opponent = DB::table('users')
                ->where('id', $opponentId)
                ->first();
            
            return [
                'success' => true,
                'chat_exists' => true,
                'chat_id' => $chat['id'],
                'match_id' => $match_id,
                'is_expired' => $isExpired,
                'is_active' => $isActive,
                'opponent_active' => $opponentActive,
                'chat_end_time' => $chat['chat_end_time'],
                'extended' => $chat['extended'],
                'remaining_seconds' => max(0, $endTime - $now),
                'opponent' => $opponent ? [
                    'id' => $opponent['id'],
                    'telegram_id' => $opponent['telegram_id'],
                    'username' => $opponent['username'],
                    'name' => $opponent['first_name'] . ' ' . $opponent['last_name']
                ] : null
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در بررسی وضعیت چت: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در بررسی وضعیت چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * غیرفعال کردن چت
     * @param int $match_id شناسه بازی
     * @return array نتیجه عملیات
     */
    public function deactivateChat($match_id)
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // بررسی وضعیت چت کاربر
            $userChatStatus = DB::table('chat_status')
                ->where('match_id', $match_id)
                ->where('user_id', $user['id'])
                ->first();
                
            if (!$userChatStatus) {
                return [
                    'success' => false,
                    'message' => 'وضعیت چت برای این کاربر یافت نشد.'
                ];
            }
            
            // غیرفعال کردن چت
            DB::table('chat_status')
                ->where('id', $userChatStatus['id'])
                ->update([
                    'is_active' => false,
                    'last_deactivation' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            return [
                'success' => true,
                'message' => 'چت با موفقیت غیرفعال شد.'
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در غیرفعال کردن چت: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در غیرفعال کردن چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * درخواست فعال‌سازی مجدد چت
     * @param int $match_id شناسه بازی
     * @return array نتیجه عملیات
     */
    public function requestReactivateChat($match_id)
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $match_id)
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی وضعیت چت کاربر
            $userChatStatus = DB::table('chat_status')
                ->where('match_id', $match_id)
                ->where('user_id', $user['id'])
                ->first();
                
            if (!$userChatStatus) {
                return [
                    'success' => false,
                    'message' => 'وضعیت چت برای این کاربر یافت نشد.'
                ];
            }
            
            // بررسی وضعیت چت حریف
            $opponentId = ($match['player1'] == $user['id']) ? $match['player2'] : $match['player1'];
            $opponentChatStatus = DB::table('chat_status')
                ->where('match_id', $match_id)
                ->where('user_id', $opponentId)
                ->first();
                
            if (!$opponentChatStatus) {
                return [
                    'success' => false,
                    'message' => 'وضعیت چت برای حریف یافت نشد.'
                ];
            }
            
            // بررسی فعال بودن چت حریف
            if (!$opponentChatStatus['is_active']) {
                return [
                    'success' => false,
                    'message' => 'حریف شما نیز چت را غیرفعال کرده است. امکان فعال‌سازی مجدد وجود ندارد.'
                ];
            }
            
            // دریافت اطلاعات حریف
            $opponent = DB::table('users')
                ->where('id', $opponentId)
                ->first();
                
            if (!$opponent) {
                return [
                    'success' => false,
                    'message' => 'اطلاعات حریف یافت نشد.'
                ];
            }
            
            // ارسال پیام درخواست به حریف
            $message = "💬 *درخواست فعال‌سازی مجدد چت*\n\n";
            $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " تمایل دارد چت را مجدداً فعال کند.\n\n";
            $message .= "آیا موافقت می‌کنید؟";
            
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ موافقم', 'callback_data' => "reactivate_chat_{$match_id}"],
                        ['text' => '❌ موافق نیستم', 'callback_data' => "reject_reactivate_chat_{$match_id}"]
                    ]
                ]
            ]);
            
            $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
            
            $params = [
                'chat_id' => $opponent['telegram_id'],
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => $reply_markup
            ];
            
            $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage";
            $this->sendTelegramRequest($url, $params);
            
            return [
                'success' => true,
                'message' => 'درخواست فعال‌سازی مجدد چت برای حریف ارسال شد. منتظر پاسخ باشید.'
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در درخواست فعال‌سازی مجدد چت: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در درخواست فعال‌سازی مجدد چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * فعال‌سازی مجدد چت
     * @param int $match_id شناسه بازی
     * @return array نتیجه عملیات
     */
    public function reactivateChat($match_id)
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $match_id)
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مورد نظر یافت نشد.'
                ];
            }
            
            // فعال‌سازی چت برای درخواست‌کننده
            $requesterChatStatus = DB::table('chat_status')
                ->where('match_id', $match_id)
                ->where('user_id', ($match['player1'] == $user['id']) ? $match['player2'] : $match['player1'])
                ->first();
                
            if ($requesterChatStatus) {
                DB::table('chat_status')
                    ->where('id', $requesterChatStatus['id'])
                    ->update([
                        'is_active' => true,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            }
            
            // فعال‌سازی چت برای پاسخ‌دهنده
            $responderChatStatus = DB::table('chat_status')
                ->where('match_id', $match_id)
                ->where('user_id', $user['id'])
                ->first();
                
            if ($responderChatStatus) {
                DB::table('chat_status')
                    ->where('id', $responderChatStatus['id'])
                    ->update([
                        'is_active' => true,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            }
            
            // ارسال پیام به درخواست‌کننده
            $requester = DB::table('users')
                ->where('id', ($match['player1'] == $user['id']) ? $match['player2'] : $match['player1'])
                ->first();
                
            if ($requester) {
                $message = "✅ *چت مجدداً فعال شد*\n\n";
                $message .= "درخواست شما برای فعال‌سازی مجدد چت پذیرفته شد. اکنون می‌توانید با حریف خود چت کنید.";
                
                $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
                
                $params = [
                    'chat_id' => $requester['telegram_id'],
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ];
                
                $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage";
                $this->sendTelegramRequest($url, $params);
            }
            
            return [
                'success' => true,
                'message' => 'چت با موفقیت فعال شد.'
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در فعال‌سازی مجدد چت: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در فعال‌سازی مجدد چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * رد درخواست فعال‌سازی مجدد چت
     * @param int $match_id شناسه بازی
     * @return array نتیجه عملیات
     */
    public function rejectReactivateChat($match_id)
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $match_id)
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مورد نظر یافت نشد.'
                ];
            }
            
            // ارسال پیام به درخواست‌کننده
            $requester = DB::table('users')
                ->where('id', ($match['player1'] == $user['id']) ? $match['player2'] : $match['player1'])
                ->first();
                
            if ($requester) {
                $message = "❌ *درخواست رد شد*\n\n";
                $message .= "درخواست شما برای فعال‌سازی مجدد چت توسط حریف رد شد.";
                
                $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
                
                $params = [
                    'chat_id' => $requester['telegram_id'],
                    'text' => $message,
                    'parse_mode' => 'Markdown'
                ];
                
                $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage";
                $this->sendTelegramRequest($url, $params);
            }
            
            return [
                'success' => true,
                'message' => 'درخواست فعال‌سازی مجدد چت با موفقیت رد شد.'
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در رد درخواست فعال‌سازی مجدد چت: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در رد درخواست فعال‌سازی مجدد چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تمدید زمان چت
     * @param int $match_id شناسه بازی
     * @return array نتیجه عملیات
     */
    public function extendChatTime($match_id)
    {
        try {
            // دریافت اطلاعات چت
            $chat = DB::table('post_game_chats')
                ->where('match_id', $match_id)
                ->first();
                
            if (!$chat) {
                return [
                    'success' => false,
                    'message' => 'چت برای این بازی یافت نشد.'
                ];
            }
            
            // بررسی تمدید قبلی
            if ($chat['extended']) {
                return [
                    'success' => false,
                    'message' => 'چت قبلاً تمدید شده است.'
                ];
            }
            
            // بررسی منقضی نشدن چت
            $now = time();
            $endTime = strtotime($chat['chat_end_time']);
            
            if ($now >= $endTime) {
                return [
                    'success' => false,
                    'message' => 'زمان چت به پایان رسیده است.'
                ];
            }
            
            // دریافت زمان تمدید از تنظیمات
            $extendedTime = $this->getExtendedChatTime();
            
            // محاسبه زمان پایان جدید
            $newEndTime = date('Y-m-d H:i:s', time() + $extendedTime);
            
            // به‌روزرسانی چت
            DB::table('post_game_chats')
                ->where('id', $chat['id'])
                ->update([
                    'chat_end_time' => $newEndTime,
                    'extended' => true
                ]);
                
            return [
                'success' => true,
                'message' => 'زمان چت با موفقیت به ۵ دقیقه افزایش یافت.',
                'chat_end_time' => $newEndTime,
                'remaining_seconds' => $extendedTime
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در تمدید زمان چت: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در تمدید زمان چت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * صدا زدن کاربر در مینی گیم
     * @param int $match_id شناسه بازی
     * @return array نتیجه عملیات
     */
    public function callOpponent($match_id)
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات بازی
            $match = DB::table('matches')
                ->where('id', $match_id)
                ->first();
                
            if (!$match) {
                return [
                    'success' => false,
                    'message' => 'بازی مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی مشارکت کاربر در بازی
            if ($match['player1'] != $user['id'] && $match['player2'] != $user['id']) {
                return [
                    'success' => false,
                    'message' => 'شما در این بازی شرکت نکرده‌اید.'
                ];
            }
            
            // بررسی نوبت بازی
            $isUserTurn = ($match['current_turn'] != $user['id']);
            
            if (!$isUserTurn) {
                return [
                    'success' => false,
                    'message' => 'نوبت شماست. نمی‌توانید حریف را صدا بزنید.'
                ];
            }
            
            // دریافت اطلاعات حریف
            $opponentId = ($match['player1'] == $user['id']) ? $match['player2'] : $match['player1'];
            $opponent = DB::table('users')
                ->where('id', $opponentId)
                ->first();
                
            if (!$opponent) {
                return [
                    'success' => false,
                    'message' => 'اطلاعات حریف یافت نشد.'
                ];
            }
            
            // ارسال پیام به حریف
            $message = "🔔 *نوبت شماست!*\n\n";
            $message .= "دوست شما " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " منتظر است تا شما بازی کنید.\n\n";
            $message .= "لطفاً هر چه سریع‌تر نوبت خود را انجام دهید.";
            
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🎮 ادامه بازی', 'callback_data' => "continue_game_{$match_id}"]
                    ]
                ]
            ]);
            
            $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
            
            $params = [
                'chat_id' => $opponent['telegram_id'],
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => $reply_markup
            ];
            
            $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage";
            $this->sendTelegramRequest($url, $params);
            
            return [
                'success' => true,
                'message' => 'اعلان به حریف شما ارسال شد.'
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در صدا زدن حریف: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در صدا زدن حریف: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت ری‌اکشن
     * @param int $message_id شناسه پیام
     * @param string $emoji اموجی ری‌اکشن
     * @return array نتیجه عملیات
     */
    public function addReaction($message_id, $emoji)
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات ری‌اکشن
            $reaction = DB::table('reactions')
                ->where('emoji', $emoji)
                ->where('is_active', true)
                ->first();
                
            if (!$reaction) {
                return [
                    'success' => false,
                    'message' => 'ری‌اکشن مورد نظر یافت نشد یا غیرفعال است.'
                ];
            }
            
            // بررسی ری‌اکشن قبلی
            $existingReaction = DB::table('user_reactions')
                ->where('user_id', $user['id'])
                ->where('message_id', $message_id)
                ->first();
                
            if ($existingReaction) {
                // به‌روزرسانی ری‌اکشن
                DB::table('user_reactions')
                    ->where('id', $existingReaction['id'])
                    ->update([
                        'reaction_id' => $reaction['id']
                    ]);
            } else {
                // ایجاد ری‌اکشن جدید
                DB::table('user_reactions')->insert([
                    'user_id' => $user['id'],
                    'message_id' => $message_id,
                    'reaction_id' => $reaction['id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'ری‌اکشن با موفقیت ثبت شد.',
                'emoji' => $emoji,
                'reaction_id' => $reaction['id']
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت ری‌اکشن: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت ری‌اکشن: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * دریافت تمام ری‌اکشن‌های فعال
     * @return array لیست ری‌اکشن‌ها
     */
    public function getAllReactions()
    {
        try {
            // دریافت تمام ری‌اکشن‌های فعال
            $reactions = DB::table('reactions')
                ->where('is_active', true)
                ->get();
                
            return [
                'success' => true,
                'reactions' => $reactions
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت ری‌اکشن‌ها: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت ری‌اکشن‌ها: ' . $e->getMessage(),
                'reactions' => []
            ];
        }
    }
    
    /**
     * دریافت زمان چت از تنظیمات
     * @return int زمان چت به ثانیه
     */
    private function getChatTime()
    {
        try {
            $setting = DB::table('bot_settings')
                ->where('name', 'post_game_chat_time')
                ->first();
                
            if ($setting) {
                return intval($setting['value']);
            }
            
            return 30; // مقدار پیش‌فرض: 30 ثانیه
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت زمان چت: " . $e->getMessage());
            return 30;
        }
    }
    
    /**
     * دریافت زمان تمدید چت از تنظیمات
     * @return int زمان تمدید چت به ثانیه
     */
    private function getExtendedChatTime()
    {
        try {
            $setting = DB::table('bot_settings')
                ->where('name', 'extended_chat_time')
                ->first();
                
            if ($setting) {
                return intval($setting['value']);
            }
            
            return 300; // مقدار پیش‌فرض: 5 دقیقه (300 ثانیه)
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت زمان تمدید چت: " . $e->getMessage());
            return 300;
        }
    }
    
    /**
     * ارسال درخواست به API تلگرام
     * @param string $url آدرس API
     * @param array $params پارامترهای درخواست
     * @return array|null
     */
    private function sendTelegramRequest($url, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        
        if (curl_errno($ch)) {
            error_log("خطا در ارسال درخواست به تلگرام: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        $response = json_decode($response, true);
        
        if (!$response['ok']) {
            error_log("خطا در پاسخ تلگرام: " . json_encode($response));
            return null;
        }
        
        return $response['result'];
    }
}