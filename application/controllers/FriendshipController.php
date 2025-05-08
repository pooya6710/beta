<?php
namespace application\controllers;

use Application\Model\DB;

class FriendshipController
{
    private $telegram_id;
    
    public function __construct($telegram_id)
    {
        $this->telegram_id = $telegram_id;
    }
    
    /**
     * دریافت لیست دوستان کاربر
     * @return array
     */
    public function getFriendsList()
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [];
            }
            
            // دریافت لیست دوستی‌های کاربر
            $friendships = DB::rawQuery(
                "SELECT * FROM friendships 
                WHERE (user_id_1 = ? OR user_id_2 = ?) AND status = 'accepted'",
                [$user['id'], $user['id']]
            );
            
            if (empty($friendships)) {
                return [];
            }
            
            $friendIds = [];
            foreach ($friendships as $friendship) {
                if ($friendship['user_id_1'] == $user['id']) {
                    $friendIds[] = $friendship['user_id_2'];
                } else {
                    $friendIds[] = $friendship['user_id_1'];
                }
            }
            
            if (empty($friendIds)) {
                return [];
            }
            
            // دریافت اطلاعات دوستان
            $friendsList = [];
            foreach ($friendIds as $friendId) {
                $friend = DB::table('users')
                    ->where('id', $friendId)
                    ->select('id', 'telegram_id', 'username', 'first_name', 'last_name', 'updated_at', 'last_activity')
                    ->first();
                    
                if ($friend) {
                    // دریافت اطلاعات پروفایل دوست
                    $friendProfile = DB::table('user_profiles')
                        ->where('user_id', $friendId)
                        ->first();
                        
                    // دریافت اطلاعات اضافی دوست
                    $friendExtra = DB::table('users_extra')
                        ->where('user_id', $friendId)
                        ->first();
                        
                    // بررسی آنلاین بودن
                    $isOnline = false;
                    if ($friend['last_activity']) {
                        $lastActivity = strtotime($friend['last_activity']);
                        $now = time();
                        $isOnline = ($now - $lastActivity) <= 600; // آنلاین در 10 دقیقه اخیر
                    }
                    
                    $friendData = [
                        'id' => $friend['id'],
                        'telegram_id' => $friend['telegram_id'],
                        'username' => $friend['username'],
                        'name' => $friend['first_name'] . ' ' . $friend['last_name'],
                        'is_online' => $isOnline,
                        'trophies' => $friendExtra ? $friendExtra['trophies'] : 0,
                        'profile' => $friendProfile
                    ];
                    
                    $friendsList[] = $friendData;
                }
            }
            
            // مرتب‌سازی بر اساس آنلاین بودن و سپس جام‌ها
            usort($friendsList, function($a, $b) {
                if ($a['is_online'] != $b['is_online']) {
                    return ($a['is_online']) ? -1 : 1;
                }
                return $b['trophies'] - $a['trophies'];
            });
            
            return $friendsList;
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت لیست دوستان: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت لیست درخواست‌های دوستی دریافتی
     * @return array
     */
    public function getIncomingFriendRequests()
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [];
            }
            
            // دریافت لیست درخواست‌های دوستی دریافتی
            $requests = DB::table('friend_requests')
                ->where('to_user_id', $user['id'])
                ->where('status', 'pending')
                ->get();
                
            if (empty($requests)) {
                return [];
            }
            
            $requestsList = [];
            foreach ($requests as $request) {
                $sender = DB::table('users')
                    ->where('id', $request['from_user_id'])
                    ->select('id', 'telegram_id', 'username', 'first_name', 'last_name')
                    ->first();
                    
                if ($sender) {
                    // دریافت اطلاعات پروفایل دوست
                    $senderProfile = DB::table('user_profiles')
                        ->where('user_id', $sender['id'])
                        ->first();
                        
                    // دریافت اطلاعات اضافی دوست
                    $senderExtra = DB::table('users_extra')
                        ->where('user_id', $sender['id'])
                        ->first();
                        
                    $requestData = [
                        'request_id' => $request['id'],
                        'sender_id' => $sender['id'],
                        'telegram_id' => $sender['telegram_id'],
                        'username' => $sender['username'],
                        'name' => $sender['first_name'] . ' ' . $sender['last_name'],
                        'trophies' => $senderExtra ? $senderExtra['trophies'] : 0,
                        'profile' => $senderProfile,
                        'created_at' => $request['created_at']
                    ];
                    
                    $requestsList[] = $requestData;
                }
            }
            
            return $requestsList;
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت لیست درخواست‌های دوستی دریافتی: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * دریافت لیست درخواست‌های دوستی ارسالی
     * @return array
     */
    public function getOutgoingFriendRequests()
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [];
            }
            
            // دریافت لیست درخواست‌های دوستی ارسالی
            $requests = DB::table('friend_requests')
                ->where('from_user_id', $user['id'])
                ->where('status', 'pending')
                ->get();
                
            if (empty($requests)) {
                return [];
            }
            
            $requestsList = [];
            foreach ($requests as $request) {
                $receiver = DB::table('users')
                    ->where('id', $request['to_user_id'])
                    ->select('id', 'telegram_id', 'username', 'first_name', 'last_name')
                    ->first();
                    
                if ($receiver) {
                    // دریافت اطلاعات اضافی گیرنده
                    $receiverExtra = DB::table('users_extra')
                        ->where('user_id', $receiver['id'])
                        ->first();
                        
                    $requestData = [
                        'request_id' => $request['id'],
                        'receiver_id' => $receiver['id'],
                        'telegram_id' => $receiver['telegram_id'],
                        'username' => $receiver['username'],
                        'name' => $receiver['first_name'] . ' ' . $receiver['last_name'],
                        'trophies' => $receiverExtra ? $receiverExtra['trophies'] : 0,
                        'created_at' => $request['created_at']
                    ];
                    
                    // محاسبه زمان باقی‌مانده برای ارسال درخواست مجدد
                    $requestTime = strtotime($request['created_at']);
                    $currentTime = time();
                    $elapsedTime = $currentTime - $requestTime;
                    $timeout = 12 * 3600; // 12 ساعت
                    
                    $requestData['elapsed_time'] = $elapsedTime;
                    $requestData['timeout'] = $timeout;
                    $requestData['can_request_again'] = ($elapsedTime >= $timeout);
                    
                    if (!$requestData['can_request_again']) {
                        $remainingTime = $timeout - $elapsedTime;
                        $remainingHours = floor($remainingTime / 3600);
                        $remainingMinutes = floor(($remainingTime % 3600) / 60);
                        
                        $requestData['remaining_hours'] = $remainingHours;
                        $requestData['remaining_minutes'] = $remainingMinutes;
                    }
                    
                    $requestsList[] = $requestData;
                }
            }
            
            return $requestsList;
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت لیست درخواست‌های دوستی ارسالی: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ارسال درخواست دوستی
     * @param string $username نام کاربری یا آیدی تلگرام
     * @return array نتیجه عملیات
     */
    public function sendFriendRequest($username)
    {
        try {
            // دریافت اطلاعات کاربر فرستنده
            $sender = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$sender) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // پاکسازی نام کاربری
            $username = trim($username);
            $username = ltrim($username, '@/');
            
            // جستجوی کاربر بر اساس نام کاربری یا آیدی تلگرام
            $receiver = null;
            if (is_numeric($username)) {
                // جستجو بر اساس آیدی تلگرام
                $receiver = DB::table('users')
                    ->where('telegram_id', $username)
                    ->first();
            } else {
                // جستجو بر اساس نام کاربری
                $receiver = DB::table('users')
                    ->where('username', $username)
                    ->first();
            }
            
            if (!$receiver) {
                return [
                    'success' => false,
                    'message' => 'کاربر مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی عدم ارسال درخواست به خود
            if ($sender['id'] == $receiver['id']) {
                return [
                    'success' => false,
                    'message' => 'شما نمی‌توانید به خودتان درخواست دوستی ارسال کنید.'
                ];
            }
            
            // بررسی دوستی قبلی
            $existingFriendship = DB::rawQuery(
                "SELECT * FROM friendships 
                WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?) AND status = 'accepted'",
                [$sender['id'], $receiver['id'], $receiver['id'], $sender['id']]
            );
            
            if (!empty($existingFriendship)) {
                return [
                    'success' => false,
                    'message' => 'شما و این کاربر در حال حاضر دوست هستید.'
                ];
            }
            
            // بررسی درخواست قبلی
            require_once __DIR__ . '/RequestTimeoutController.php';
            $requestStatus = RequestTimeoutController::checkFriendRequest($sender['id'], $receiver['id']);
            
            if (!$requestStatus['can_send']) {
                $message = "شما به تازگی برای {$receiver['username']} درخواست دوستی ارسال کرده‌اید. ";
                $message .= "لطفاً بعد از {$requestStatus['remaining_hours']} ساعت و {$requestStatus['remaining_minutes']} دقیقه مجدداً درخواست دهید.";
                return [
                    'success' => false,
                    'message' => $message
                ];
            }
            
            // بررسی درخواست معکوس
            $reverseRequest = DB::table('friend_requests')
                ->where('from_user_id', $receiver['id'])
                ->where('to_user_id', $sender['id'])
                ->where('status', 'pending')
                ->first();
                
            if ($reverseRequest) {
                // پذیرش درخواست دوستی معکوس
                $this->acceptFriendRequest($reverseRequest['id']);
                
                return [
                    'success' => true,
                    'message' => "درخواست دوستی {$receiver['username']} را قبلاً دریافت کرده بودید. اکنون شما و ایشان دوست هستید.",
                    'auto_accepted' => true
                ];
            }
            
            // ایجاد درخواست دوستی جدید
            $requestId = RequestTimeoutController::createFriendRequest($sender['id'], $receiver['id']);
            
            if (!$requestId) {
                return [
                    'success' => false,
                    'message' => 'خطا در ایجاد درخواست دوستی. لطفاً مجدد تلاش کنید.'
                ];
            }
            
            // ارسال پیام به کاربر گیرنده
            $message = "👋 *درخواست دوستی جدید*\n\n";
            $message .= "کاربر " . ($sender['username'] ? '@' . $sender['username'] : $sender['first_name'] . ' ' . $sender['last_name']) . " برای شما درخواست دوستی ارسال کرده است.\n\n";
            $message .= "برای پذیرش یا رد این درخواست، از دکمه‌های زیر استفاده کنید:";
            
            $reply_markup = json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ قبول درخواست', 'callback_data' => "accept_friend_{$requestId}"],
                        ['text' => '❌ رد درخواست', 'callback_data' => "reject_friend_{$requestId}"]
                    ],
                    [
                        ['text' => '👤 مشاهده پروفایل', 'callback_data' => "view_profile_{$sender['id']}"]
                    ]
                ]
            ]);
            
            $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
            
            $params = [
                'chat_id' => $receiver['telegram_id'],
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => $reply_markup
            ];
            
            $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage";
            $this->sendTelegramRequest($url, $params);
            
            return [
                'success' => true,
                'message' => "درخواست دوستی شما برای {$receiver['username']} با موفقیت ارسال شد.",
                'request_id' => $requestId
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ارسال درخواست دوستی: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ارسال درخواست دوستی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * پذیرش درخواست دوستی
     * @param int $requestId شناسه درخواست
     * @return array نتیجه عملیات
     */
    public function acceptFriendRequest($requestId)
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
            
            // دریافت اطلاعات درخواست
            $request = DB::table('friend_requests')
                ->where('id', $requestId)
                ->where('to_user_id', $user['id'])
                ->where('status', 'pending')
                ->first();
                
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'درخواست دوستی یافت نشد یا معتبر نیست.'
                ];
            }
            
            // تغییر وضعیت درخواست
            require_once __DIR__ . '/RequestTimeoutController.php';
            $success = RequestTimeoutController::acceptFriendRequest($requestId);
            
            if (!$success) {
                return [
                    'success' => false,
                    'message' => 'خطا در پذیرش درخواست دوستی. لطفاً مجدد تلاش کنید.'
                ];
            }
            
            // ایجاد رابطه دوستی
            DB::table('friendships')->insert([
                'user_id_1' => $request['from_user_id'],
                'user_id_2' => $user['id'],
                'status' => 'accepted',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // دریافت اطلاعات فرستنده درخواست
            $sender = DB::table('users')
                ->where('id', $request['from_user_id'])
                ->first();
                
            if ($sender) {
                // ارسال پیام به فرستنده درخواست
                $message = "🎉 *درخواست دوستی پذیرفته شد*\n\n";
                $message .= "کاربر " . ($user['username'] ? '@' . $user['username'] : $user['first_name'] . ' ' . $user['last_name']) . " درخواست دوستی شما را پذیرفت.\n\n";
                $message .= "اکنون می‌توانید با یکدیگر بازی کنید و پیام ارسال کنید.";
                
                $reply_markup = json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '🎮 شروع بازی', 'callback_data' => "start_game_{$user['id']}"]
                        ]
                    ]
                ]);
                
                $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
                
                $params = [
                    'chat_id' => $sender['telegram_id'],
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => $reply_markup
                ];
                
                $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage";
                $this->sendTelegramRequest($url, $params);
            }
            
            return [
                'success' => true,
                'message' => "درخواست دوستی " . ($sender ? ($sender['username'] ? '@' . $sender['username'] : $sender['first_name'] . ' ' . $sender['last_name']) : 'کاربر') . " با موفقیت پذیرفته شد.",
                'friend' => $sender
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در پذیرش درخواست دوستی: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در پذیرش درخواست دوستی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * رد درخواست دوستی
     * @param int $requestId شناسه درخواست
     * @return array نتیجه عملیات
     */
    public function rejectFriendRequest($requestId)
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
            
            // دریافت اطلاعات درخواست
            $request = DB::table('friend_requests')
                ->where('id', $requestId)
                ->where('to_user_id', $user['id'])
                ->where('status', 'pending')
                ->first();
                
            if (!$request) {
                return [
                    'success' => false,
                    'message' => 'درخواست دوستی یافت نشد یا معتبر نیست.'
                ];
            }
            
            // تغییر وضعیت درخواست
            require_once __DIR__ . '/RequestTimeoutController.php';
            $success = RequestTimeoutController::rejectFriendRequest($requestId);
            
            if (!$success) {
                return [
                    'success' => false,
                    'message' => 'خطا در رد درخواست دوستی. لطفاً مجدد تلاش کنید.'
                ];
            }
            
            // دریافت اطلاعات فرستنده درخواست
            $sender = DB::table('users')
                ->where('id', $request['from_user_id'])
                ->first();
                
            return [
                'success' => true,
                'message' => "درخواست دوستی " . ($sender ? ($sender['username'] ? '@' . $sender['username'] : $sender['first_name'] . ' ' . $sender['last_name']) : 'کاربر') . " با موفقیت رد شد."
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در رد درخواست دوستی: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در رد درخواست دوستی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * حذف دوست
     * @param string $username نام کاربری یا آیدی تلگرام
     * @return array نتیجه عملیات
     */
    public function removeFriend($username)
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
            
            // پاکسازی نام کاربری
            $username = trim($username);
            $username = ltrim($username, '@/');
            
            // جستجوی کاربر بر اساس نام کاربری یا آیدی تلگرام
            $friend = null;
            if (is_numeric($username)) {
                // جستجو بر اساس آیدی تلگرام
                $friend = DB::table('users')
                    ->where('telegram_id', $username)
                    ->first();
            } else {
                // جستجو بر اساس نام کاربری
                $friend = DB::table('users')
                    ->where('username', $username)
                    ->first();
            }
            
            if (!$friend) {
                return [
                    'success' => false,
                    'message' => 'کاربر مورد نظر یافت نشد.'
                ];
            }
            
            // بررسی دوستی
            $friendship = DB::rawQuery(
                "SELECT * FROM friendships 
                WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?) AND status = 'accepted'",
                [$user['id'], $friend['id'], $friend['id'], $user['id']]
            );
            
            if (empty($friendship)) {
                return [
                    'success' => false,
                    'message' => 'شما و این کاربر دوست نیستید.'
                ];
            }
            
            // حذف دوستی
            foreach ($friendship as $fs) {
                DB::table('friendships')
                    ->where('id', $fs['id'])
                    ->delete();
            }
            
            return [
                'success' => true,
                'message' => "دوستی شما با " . ($friend['username'] ? '@' . $friend['username'] : $friend['first_name'] . ' ' . $friend['last_name']) . " با موفقیت حذف شد."
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در حذف دوست: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در حذف دوست: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * نمایش پروفایل کاربر
     * @param string $username نام کاربری یا آیدی تلگرام
     * @return array اطلاعات پروفایل
     */
    public function viewUserProfile($username)
    {
        try {
            // پاکسازی نام کاربری
            $username = trim($username);
            $username = ltrim($username, '@/');
            
            // جستجوی کاربر بر اساس نام کاربری یا آیدی تلگرام
            $targetUser = null;
            if (is_numeric($username)) {
                // جستجو بر اساس آیدی تلگرام
                $targetUser = DB::table('users')
                    ->where('telegram_id', $username)
                    ->first();
            } else {
                // جستجو بر اساس نام کاربری
                $targetUser = DB::table('users')
                    ->where('username', $username)
                    ->first();
            }
            
            if (!$targetUser) {
                return [
                    'success' => false,
                    'message' => 'کاربر مورد نظر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات کاربر فعلی
            $currentUser = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$currentUser) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // دریافت اطلاعات پروفایل کاربر هدف
            $profile = DB::table('user_profiles')
                ->where('user_id', $targetUser['id'])
                ->first();
                
            // دریافت اطلاعات اضافی کاربر هدف
            $userExtra = DB::table('users_extra')
                ->where('user_id', $targetUser['id'])
                ->first();
                
            // بررسی آنلاین بودن
            $isOnline = false;
            if ($targetUser['last_activity']) {
                $lastActivity = strtotime($targetUser['last_activity']);
                $now = time();
                $isOnline = ($now - $lastActivity) <= 600; // آنلاین در 10 دقیقه اخیر
            }
            
            // بررسی وضعیت دوستی
            $isFriend = false;
            $friendship = DB::rawQuery(
                "SELECT * FROM friendships 
                WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?) AND status = 'accepted'",
                [$currentUser['id'], $targetUser['id'], $targetUser['id'], $currentUser['id']]
            );
            
            if (!empty($friendship)) {
                $isFriend = true;
            }
            
            // بررسی درخواست دوستی قبلی
            $hasPendingRequest = false;
            $pendingRequest = DB::table('friend_requests')
                ->where('from_user_id', $currentUser['id'])
                ->where('to_user_id', $targetUser['id'])
                ->where('status', 'pending')
                ->first();
                
            if ($pendingRequest) {
                $hasPendingRequest = true;
            }
            
            // آماده‌سازی اطلاعات پروفایل
            $userProfile = [
                'id' => $targetUser['id'],
                'telegram_id' => $targetUser['telegram_id'],
                'username' => $targetUser['username'],
                'name' => $targetUser['first_name'] . ' ' . $targetUser['last_name'],
                'is_online' => $isOnline,
                'is_friend' => $isFriend,
                'has_pending_request' => $hasPendingRequest,
                'trophies' => $userExtra ? $userExtra['trophies'] : 0,
                'total_games' => $userExtra ? $userExtra['total_games'] : 0,
                'wins' => $userExtra ? $userExtra['wins'] : 0,
                'win_ratio' => $userExtra && $userExtra['total_games'] > 0 ? round(($userExtra['wins'] / $userExtra['total_games']) * 100) : 0,
                'delta_coins' => $userExtra ? $userExtra['delta_coins'] : 0
            ];
            
            // اضافه کردن اطلاعات پروفایل اگر موجود باشد
            if ($profile) {
                $userProfile['profile'] = [
                    'photo' => $profile['photo'],
                    'name' => $profile['name'],
                    'gender' => $profile['gender'],
                    'age' => $profile['age'],
                    'bio' => $profile['bio'],
                    'province' => $profile['province'],
                    'city' => $profile['city']
                ];
            }
            
            return [
                'success' => true,
                'profile' => $userProfile
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در نمایش پروفایل کاربر: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در نمایش پروفایل کاربر: ' . $e->getMessage()
            ];
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