<?php
namespace application\controllers;

use Application\Model\DB;

class AdminController
{
    private $telegram_id;
    
    public function __construct($telegram_id)
    {
        $this->telegram_id = $telegram_id;
    }
    
    /**
     * بررسی می‌کند که آیا کاربر ادمین است یا خیر
     * @return bool
     */
    public function isAdmin()
    {
        $user = DB::table('users')
            ->where('telegram_id', $this->telegram_id)
            ->select('*')
            ->first();
            
        if (!$user) {
            return false;
        }
        
        return $user['type'] === 'admin' || $user['type'] === 'owner';
    }
    
    /**
     * بررسی می‌کند که آیا کاربر دسترسی‌های مورد نظر را دارد یا خیر
     * @param string $permission نام دسترسی مورد نظر
     * @return bool
     */
    public function hasPermission($permission)
    {
        if (!$this->isAdmin()) {
            return false;
        }
        
        $user = DB::table('users')
            ->where('telegram_id', $this->telegram_id)
            ->select('*')
            ->first();
            
        if (!$user) {
            return false;
        }
        
        // اگر کاربر مدیر ارشد (owner) باشد، همه دسترسی‌ها را دارد
        if ($user['type'] === 'owner') {
            return true;
        }
        
        // دریافت دسترسی‌های ادمین از دیتابیس
        $permissions = DB::table('admin_permissions')
            ->where('user_id', $user['id'])
            ->select('*')
            ->first();
            
        if (!$permissions) {
            return false;
        }
        
        // بررسی دسترسی مورد نظر
        return isset($permissions[$permission]) && $permissions[$permission] === true;
    }
    
    /**
     * دریافت آمار سیستم
     * @return array
     */
    public function getStatistics()
    {
        require_once __DIR__ . '/StatisticsController.php';
        return \application\controllers\StatisticsController::getAdminReport();
    }
    
    /**
     * ارسال پیام همگانی به تمام کاربران
     * @param string $message متن پیام
     * @param string $type نوع پیام (text, forward)
     * @param int $message_id شناسه پیام (برای فوروارد)
     * @return array تعداد پیام‌های ارسال شده و ناموفق
     */
    public function broadcastMessage($message, $type = 'text', $message_id = null)
    {
        $stats = [
            'sent' => 0,
            'failed' => 0
        ];
        
        // دریافت تمام کاربران
        $users = DB::table('users')->select('*')->get();
        
        // ثبت اطلاعات پیام در دیتابیس
        $admin = DB::table('users')
            ->where('telegram_id', $this->telegram_id)
            ->select('id')
            ->first();
            
        $broadcast_id = DB::table('broadcast_messages')->insert([
            'admin_id' => $admin['id'],
            'message_type' => $type,
            'message_text' => $message,
            'media_id' => $message_id
        ]);
        
        // ارسال پیام به تمام کاربران
        // در اینجا کد ارسال پیام به صورت مجزا به هر کاربر نوشته می‌شود
        // و آمار ارسال به روز می‌شود
        
        return $stats;
    }
    
    /**
     * اضافه کردن یک ادمین جدید
     * @param int $user_id شناسه کاربر
     * @param array $permissions دسترسی‌های ادمین
     * @return bool
     */
    public function addAdmin($user_id, $permissions = [])
    {
        // بررسی دسترسی کاربر فعلی برای اضافه کردن ادمین
        if (!$this->hasPermission('can_manage_admins')) {
            return false;
        }
        
        // تغییر نوع کاربر به ادمین
        DB::table('users')
            ->where('id', $user_id)
            ->update(['type' => 'admin']);
            
        // ایجاد دسترسی‌های ادمین
        $permissionsData = [
            'user_id' => $user_id,
            'role' => 'admin'
        ];
        
        // اضافه کردن دسترسی‌ها
        foreach ($permissions as $permission => $value) {
            $permissionsData[$permission] = $value ? true : false;
        }
        
        // حذف دسترسی‌های قبلی
        DB::table('admin_permissions')
            ->where('user_id', $user_id)
            ->delete();
            
        // اضافه کردن دسترسی‌های جدید
        DB::table('admin_permissions')->insert($permissionsData);
        
        return true;
    }
    
    /**
     * اضافه کردن توکن دسترسی به گروه یا کانال
     * @param string $token توکن دسترسی
     * @param string $name نام گروه یا کانال
     * @return bool
     */
    public function addLinkKey($token, $name)
    {
        // کد مربوط به اضافه کردن توکن دسترسی
        return true;
    }
    
    /**
     * قفل کردن یک آیدی خاص
     * @param string $username نام کاربری که باید قفل شود
     * @param string $reason دلیل قفل
     * @return bool
     */
    public function lockUsername($username, $reason = '')
    {
        // بررسی دسترسی ادمین
        if (!$this->hasPermission('can_manage_users')) {
            return false;
        }
        
        // اگر نام کاربری با "/" شروع می‌شود، آن را حذف کنیم
        $username = ltrim($username, '/');
        
        // بررسی آیا این نام کاربری قبلاً در دیتابیس موجود است
        $existingLock = DB::table('locked_usernames')
            ->where('username', $username)
            ->first();
            
        if ($existingLock) {
            // به‌روزرسانی قفل موجود
            DB::table('locked_usernames')
                ->where('username', $username)
                ->update([
                    'reason' => $reason,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            // ایجاد قفل جدید
            DB::table('locked_usernames')->insert([
                'username' => $username,
                'reason' => $reason,
                'admin_id' => $this->getUserId(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return true;
    }
    
    /**
     * بررسی آیا نام کاربری قفل شده است
     * @param string $username نام کاربری مورد نظر
     * @return bool
     */
    public static function isUsernameLocked($username)
    {
        // اگر نام کاربری با "/" شروع می‌شود، آن را حذف کنیم
        $username = ltrim($username, '/');
        
        $lockedUsername = DB::table('locked_usernames')
            ->where('username', $username)
            ->first();
            
        return $lockedUsername !== null;
    }
    
    /**
     * باز کردن قفل یک نام کاربری
     * @param string $username نام کاربری مورد نظر
     * @return bool
     */
    public function unlockUsername($username)
    {
        // بررسی دسترسی ادمین
        if (!$this->hasPermission('can_manage_users')) {
            return false;
        }
        
        // اگر نام کاربری با "/" شروع می‌شود، آن را حذف کنیم
        $username = ltrim($username, '/');
        
        DB::table('locked_usernames')
            ->where('username', $username)
            ->delete();
            
        return true;
    }
    
    /**
     * فعال یا غیرفعال کردن ربات
     * @param bool $status وضعیت ربات (true: فعال، false: غیرفعال)
     * @return bool
     */
    public function setBotStatus($status)
    {
        // بررسی دسترسی ادمین
        if (!$this->hasPermission('can_manage_settings')) {
            return false;
        }
        
        // ذخیره وضعیت ربات در دیتابیس
        $settings = DB::table('bot_settings')
            ->where('name', 'bot_active')
            ->first();
            
        if ($settings) {
            // به‌روزرسانی تنظیمات موجود
            DB::table('bot_settings')
                ->where('name', 'bot_active')
                ->update([
                    'value' => $status ? '1' : '0',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            // ایجاد تنظیمات جدید
            DB::table('bot_settings')->insert([
                'name' => 'bot_active',
                'value' => $status ? '1' : '0',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return true;
    }
    
    /**
     * بررسی آیا ربات فعال است
     * @return bool
     */
    public static function isBotActive()
    {
        $settings = DB::table('bot_settings')
            ->where('name', 'bot_active')
            ->first();
            
        if (!$settings) {
            // اگر تنظیمات موجود نباشد، به طور پیش‌فرض فعال است
            return true;
        }
        
        return $settings['value'] === '1';
    }
    
    /**
     * دریافت اطلاعات کامل کاربر
     * @param string|int $identifier شناسه کاربر (میتواند telegram_id، username، یا id باشد)
     * @return array|null
     */
    public function getUserInfo($identifier)
    {
        // بررسی دسترسی ادمین
        if (!$this->hasPermission('can_manage_users') && !$this->hasPermission('can_view_users')) {
            return null;
        }
        
        $user = null;
        
        // بررسی نوع شناسه
        if (is_numeric($identifier)) {
            // اگر عدد باشد، ممکن است telegram_id یا id باشد
            $user = DB::table('users')
                ->where('telegram_id', $identifier)
                ->first();
                
            if (!$user) {
                $user = DB::table('users')
                    ->where('id', $identifier)
                    ->first();
            }
        } else {
            // اگر رشته باشد، نام کاربری است
            $username = ltrim($identifier, '/');
            $user = DB::table('users')
                ->where('username', $username)
                ->first();
        }
        
        if (!$user) {
            return null;
        }
        
        // دریافت اطلاعات اضافی کاربر
        $userExtra = DB::table('users_extra')
            ->where('user_id', $user['id'])
            ->first();
            
        // دریافت پروفایل کاربر
        $userProfile = DB::table('user_profiles')
            ->where('user_id', $user['id'])
            ->first();
            
        // دریافت تعداد بازی‌های کاربر
        $gamesCount = DB::rawQuery(
            "SELECT COUNT(*) as count FROM matches WHERE player1 = ? OR player2 = ?",
            [$user['telegram_id'], $user['telegram_id']]
        )[0]['count'] ?? 0;
        
        // دریافت تعداد بردهای کاربر
        $winsCount = $userExtra ? ($userExtra['wins'] ?? 0) : 0;
        
        // دریافت تعداد زیرمجموعه‌های کاربر
        $referralsCount = DB::rawQuery(
            "SELECT COUNT(*) as count FROM referrals WHERE referrer_id = ?",
            [$user['id']]
        )[0]['count'] ?? 0;
        
        // ترکیب اطلاعات
        $result = [
            'user' => $user,
            'extra' => $userExtra,
            'profile' => $userProfile,
            'games_count' => $gamesCount,
            'wins_count' => $winsCount,
            'referrals_count' => $referralsCount
        ];
        
        return $result;
    }
    
    /**
     * تغییر تعداد دلتا کوین کاربر
     * @param int $userId شناسه کاربر
     * @param int $amount مقدار تغییر (می‌تواند مثبت یا منفی باشد)
     * @param string $reason دلیل تغییر
     * @return bool
     */
    public function modifyUserDeltaCoins($userId, $amount, $reason = '')
    {
        // بررسی دسترسی ادمین
        if (!$this->hasPermission('can_manage_users')) {
            return false;
        }
        
        // دریافت اطلاعات اضافی کاربر
        $userExtra = DB::table('users_extra')
            ->where('user_id', $userId)
            ->first();
            
        if (!$userExtra) {
            return false;
        }
        
        // محاسبه مقدار جدید دلتا کوین
        $newAmount = $userExtra['delta_coins'] + $amount;
        if ($newAmount < 0) {
            $newAmount = 0; // از منفی شدن دلتا کوین جلوگیری می‌کنیم
        }
        
        // به‌روزرسانی دلتا کوین کاربر
        DB::table('users_extra')
            ->where('user_id', $userId)
            ->update(['delta_coins' => $newAmount]);
            
        // ثبت تراکنش
        DB::table('delta_coin_transactions')->insert([
            'user_id' => $userId,
            'amount' => $amount,
            'reason' => $reason,
            'admin_id' => $this->getUserId(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    }
    
    /**
     * تغییر تعداد جام کاربر
     * @param int $userId شناسه کاربر
     * @param int $amount مقدار تغییر (می‌تواند مثبت یا منفی باشد)
     * @param string $reason دلیل تغییر
     * @return bool
     */
    public function modifyUserTrophies($userId, $amount, $reason = '')
    {
        // بررسی دسترسی ادمین
        if (!$this->hasPermission('can_manage_users')) {
            return false;
        }
        
        // دریافت اطلاعات اضافی کاربر
        $userExtra = DB::table('users_extra')
            ->where('user_id', $userId)
            ->first();
            
        if (!$userExtra) {
            return false;
        }
        
        // محاسبه مقدار جدید جام
        $newAmount = $userExtra['trophies'] + $amount;
        if ($newAmount < 0) {
            $newAmount = 0; // از منفی شدن جام جلوگیری می‌کنیم
        }
        
        // به‌روزرسانی جام کاربر
        DB::table('users_extra')
            ->where('user_id', $userId)
            ->update(['trophies' => $newAmount]);
            
        // ثبت تراکنش
        DB::table('trophy_transactions')->insert([
            'user_id' => $userId,
            'amount' => $amount,
            'reason' => $reason,
            'admin_id' => $this->getUserId(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    }
    
    /**
     * تنظیم مقدار پورسانت زیرمجموعه‌گیری
     * @param string $level سطح پورسانت (initial, first_win, profile_completion, thirty_wins)
     * @param float $amount مقدار پورسانت
     * @return bool
     */
    public function setReferralCommission($level, $amount)
    {
        // بررسی دسترسی ادمین
        if (!$this->hasPermission('can_manage_settings')) {
            return false;
        }
        
        // تبدیل به مقدار عددی
        $amount = floatval($amount);
        
        // بررسی معتبر بودن سطح
        $validLevels = ['initial', 'first_win', 'profile_completion', 'thirty_wins'];
        if (!in_array($level, $validLevels)) {
            return false;
        }
        
        // ذخیره تنظیمات در دیتابیس
        $settings = DB::table('bot_settings')
            ->where('name', "referral_commission_{$level}")
            ->first();
            
        if ($settings) {
            // به‌روزرسانی تنظیمات موجود
            DB::table('bot_settings')
                ->where('name', "referral_commission_{$level}")
                ->update([
                    'value' => (string)$amount,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            // ایجاد تنظیمات جدید
            DB::table('bot_settings')->insert([
                'name' => "referral_commission_{$level}",
                'value' => (string)$amount,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return true;
    }
    
    /**
     * تنظیم قیمت دلتا کوین
     * @param float $price قیمت دلتا کوین (به تومان)
     * @return bool
     */
    public function setDeltaCoinPrice($price)
    {
        // بررسی دسترسی ادمین
        if (!$this->hasPermission('can_manage_settings')) {
            return false;
        }
        
        // تبدیل به مقدار عددی
        $price = floatval($price);
        
        // ذخیره تنظیمات در دیتابیس
        $settings = DB::table('bot_settings')
            ->where('name', 'delta_coin_price')
            ->first();
            
        if ($settings) {
            // به‌روزرسانی تنظیمات موجود
            DB::table('bot_settings')
                ->where('name', 'delta_coin_price')
                ->update([
                    'value' => (string)$price,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            // ایجاد تنظیمات جدید
            DB::table('bot_settings')->insert([
                'name' => 'delta_coin_price',
                'value' => (string)$price,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return true;
    }
    
    /**
     * دریافت آیدی کاربر
     * @return int
     */
    private function getUserId()
    {
        $user = DB::table('users')
            ->where('telegram_id', $this->telegram_id)
            ->select('id')
            ->first();
            
        return $user ? $user['id'] : 0;
    }
    
    /**
     * دریافت لیست ادمین‌ها با سطح دسترسی خاص
     * @param string $permission نام سطح دسترسی
     * @return array
     */
    public static function getAdminsWithPermission($permission)
    {
        try {
            $admins = [];
            
            // اگر دسترسی 'is_owner' باشد، فقط مدیران ارشد را برمی‌گرداند
            if ($permission === 'is_owner') {
                $admins = DB::table('users')
                    ->where('type', 'owner')
                    ->get();
                    
                return $admins;
            }
            
            // دریافت ادمین‌ها با سطح دسترسی مشخص
            $permissions = DB::table('admin_permissions')
                ->where($permission, true)
                ->get();
                
            if (empty($permissions)) {
                return [];
            }
            
            // استخراج شناسه کاربران
            $userIds = array_map(function($item) {
                return $item['user_id'];
            }, $permissions);
            
            // دریافت اطلاعات کاربران
            $admins = DB::table('users')
                ->whereIn('id', $userIds)
                ->get();
                
            return $admins;
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت لیست ادمین‌ها: " . $e->getMessage());
            return [];
        }
    }
}