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
}