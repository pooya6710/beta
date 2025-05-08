<?php
namespace application\controllers;

use Application\Model\DB;

class ProfileController
{
    private $telegram_id;
    
    public function __construct($telegram_id)
    {
        $this->telegram_id = $telegram_id;
    }
    
    /**
     * دریافت اطلاعات پروفایل کاربر
     * @return array|null
     */
    public function getProfile()
    {
        try {
            // دریافت اطلاعات کاربر از جدول users
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return null;
            }
            
            // دریافت اطلاعات پروفایل کاربر
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // دریافت اطلاعات اضافی کاربر
            $userExtra = DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->first();
                
            return [
                'user' => $user,
                'profile' => $profile,
                'extra' => $userExtra
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت پروفایل کاربر: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * آیا پروفایل کاربر کامل است
     * @return bool
     */
    public function isProfileComplete()
    {
        $profile = $this->getProfile();
        
        if (!$profile || !$profile['profile']) {
            return false;
        }
        
        $requiredFields = ['photo', 'name', 'gender', 'age', 'bio'];
        
        foreach ($requiredFields as $field) {
            if (empty($profile['profile'][$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * آپلود عکس پروفایل
     * @param string $photo_path مسیر فایل عکس
     * @return array نتیجه عملیات
     */
    public function uploadProfilePhoto($photo_path)
    {
        try {
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // آیا پروفایل کاربر وجود دارد
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            $photo_id = uniqid($user['id'] . '_') . '.jpg';
            
            // ثبت یا به‌روزرسانی عکس پروفایل
            if ($profile) {
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'photo' => $photo_id,
                        'photo_approved' => false,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'photo' => $photo_id,
                    'photo_approved' => false,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // ارسال به کانال برای تایید
            $this->sendToAdminChannel('photo', $photo_id, $user);
            
            return [
                'success' => true,
                'message' => 'عکس پروفایل با موفقیت آپلود شد و در انتظار تایید ادمین است.',
                'photo_id' => $photo_id
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در آپلود عکس پروفایل: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در آپلود عکس پروفایل: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت نام کاربر
     * @param string $name نام کاربر
     * @return array نتیجه عملیات
     */
    public function setName($name)
    {
        try {
            // بررسی طول نام
            if (mb_strlen($name, 'UTF-8') > 50) {
                return [
                    'success' => false,
                    'message' => 'نام وارد شده بیش از حد مجاز است. حداکثر ۵۰ کاراکتر مجاز است.'
                ];
            }
            
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // آیا پروفایل کاربر وجود دارد
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // ثبت یا به‌روزرسانی نام کاربر
            if ($profile) {
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'name' => $name,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'name' => $name,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'نام شما با موفقیت ثبت شد.',
                'name' => $name
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت نام کاربر: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت نام: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت جنسیت کاربر
     * @param string $gender جنسیت کاربر
     * @return array نتیجه عملیات
     */
    public function setGender($gender)
    {
        try {
            // بررسی معتبر بودن جنسیت
            if (!in_array($gender, ['male', 'female'])) {
                return [
                    'success' => false,
                    'message' => 'جنسیت وارد شده معتبر نیست. لطفاً از دکمه‌های ارائه شده استفاده کنید.'
                ];
            }
            
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // آیا پروفایل کاربر وجود دارد
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // ثبت یا به‌روزرسانی جنسیت کاربر
            if ($profile) {
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'gender' => $gender,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'gender' => $gender,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $gender_text = $gender === 'male' ? 'مرد' : 'زن';
            
            return [
                'success' => true,
                'message' => 'جنسیت شما با موفقیت ثبت شد.',
                'gender' => $gender,
                'gender_text' => $gender_text
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت جنسیت کاربر: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت جنسیت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت سن کاربر
     * @param int $age سن کاربر
     * @return array نتیجه عملیات
     */
    public function setAge($age)
    {
        try {
            // بررسی معتبر بودن سن
            $age = intval($age);
            if ($age < 9 || $age > 70) {
                return [
                    'success' => false,
                    'message' => 'سن وارد شده معتبر نیست. سن باید بین ۹ تا ۷۰ سال باشد.'
                ];
            }
            
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // آیا پروفایل کاربر وجود دارد
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // ثبت یا به‌روزرسانی سن کاربر
            if ($profile) {
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'age' => $age,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'age' => $age,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'سن شما با موفقیت ثبت شد.',
                'age' => $age
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت سن کاربر: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت سن: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت بیوگرافی کاربر
     * @param string $bio متن بیوگرافی
     * @return array نتیجه عملیات
     */
    public function setBio($bio)
    {
        try {
            // بررسی طول بیوگرافی
            if (mb_strlen($bio, 'UTF-8') > 500) {
                return [
                    'success' => false,
                    'message' => 'بیوگرافی وارد شده بیش از حد مجاز است. حداکثر ۵۰۰ کاراکتر مجاز است.'
                ];
            }
            
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // آیا پروفایل کاربر وجود دارد
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // ثبت یا به‌روزرسانی بیوگرافی کاربر
            if ($profile) {
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'bio' => $bio,
                        'bio_approved' => false,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'bio' => $bio,
                    'bio_approved' => false,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // ارسال به کانال برای تایید
            $this->sendToAdminChannel('bio', $bio, $user);
            
            return [
                'success' => true,
                'message' => 'بیوگرافی شما با موفقیت ثبت شد و در انتظار تایید ادمین است.',
                'bio' => $bio
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت بیوگرافی کاربر: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت بیوگرافی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت استان کاربر
     * @param string $province نام استان
     * @return array نتیجه عملیات
     */
    public function setProvince($province)
    {
        try {
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // آیا پروفایل کاربر وجود دارد
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // ثبت یا به‌روزرسانی استان کاربر
            if ($profile) {
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'province' => $province,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'province' => $province,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'استان شما با موفقیت ثبت شد.',
                'province' => $province
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت استان کاربر: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت استان: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت شهر کاربر
     * @param string $city نام شهر
     * @return array نتیجه عملیات
     */
    public function setCity($city)
    {
        try {
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // آیا پروفایل کاربر وجود دارد
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // ثبت یا به‌روزرسانی شهر کاربر
            if ($profile) {
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'city' => $city,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'city' => $city,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'شهر شما با موفقیت ثبت شد.',
                'city' => $city
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت شهر کاربر: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت شهر: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت موقعیت مکانی کاربر
     * @param float $latitude عرض جغرافیایی
     * @param float $longitude طول جغرافیایی
     * @return array نتیجه عملیات
     */
    public function setLocation($latitude, $longitude)
    {
        try {
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // آیا پروفایل کاربر وجود دارد
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // ثبت یا به‌روزرسانی موقعیت مکانی کاربر
            if ($profile) {
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'موقعیت مکانی شما با موفقیت ثبت شد.',
                'latitude' => $latitude,
                'longitude' => $longitude
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت موقعیت مکانی کاربر: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت موقعیت مکانی: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت شماره تلفن کاربر
     * @param string $phone شماره تلفن
     * @return array نتیجه عملیات
     */
    public function setPhone($phone)
    {
        try {
            // پاکسازی شماره تلفن
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            // بررسی معتبر بودن شماره تلفن ایرانی
            if (!preg_match('/^(?:98|\+98|0098|0)?9[0-9]{9}$/', $phone)) {
                return [
                    'success' => false,
                    'message' => 'شماره تلفن وارد شده معتبر نیست. لطفاً یک شماره تلفن ایرانی وارد کنید.'
                ];
            }
            
            // تبدیل به فرمت استاندارد
            if (substr($phone, 0, 2) === '98') {
                $phone = '+' . $phone;
            } else if (substr($phone, 0, 1) === '9') {
                $phone = '+98' . $phone;
            } else if (substr($phone, 0, 1) === '0' && substr($phone, 1, 1) === '9') {
                $phone = '+98' . substr($phone, 1);
            }
            
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'کاربر یافت نشد.'
                ];
            }
            
            // آیا پروفایل کاربر وجود دارد
            $profile = DB::table('user_profiles')
                ->where('user_id', $user['id'])
                ->first();
                
            // ثبت یا به‌روزرسانی شماره تلفن کاربر
            if ($profile) {
                DB::table('user_profiles')
                    ->where('user_id', $user['id'])
                    ->update([
                        'phone' => $phone,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else {
                DB::table('user_profiles')->insert([
                    'user_id' => $user['id'],
                    'phone' => $phone,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // به‌روزرسانی پورسانت
            $this->updateReferralCommission('profile_completion');
            
            return [
                'success' => true,
                'message' => 'شماره تلفن شما با موفقیت ثبت شد.',
                'phone' => $phone
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت شماره تلفن کاربر: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در ثبت شماره تلفن: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تغییر نام کاربری
     * @param string $username نام کاربری جدید
     * @return array نتیجه عملیات
     */
    public function changeUsername($username)
    {
        try {
            // پاکسازی نام کاربری
            $username = trim($username);
            $username = ltrim($username, '@/');
            
            // بررسی فرمت نام کاربری
            if (!preg_match('/^[a-zA-Z0-9_]{5,32}$/', $username)) {
                return [
                    'success' => false,
                    'message' => 'نام کاربری باید بین ۵ تا ۳۲ کاراکتر و شامل حروف انگلیسی، اعداد و زیرخط باشد.'
                ];
            }
            
            // بررسی دسترسی به آیدی
            if (\application\controllers\AdminController::isUsernameLocked($username)) {
                return [
                    'success' => false,
                    'message' => 'این نام کاربری توسط ادمین قفل شده و قابل استفاده نیست.'
                ];
            }
            
            // بررسی استفاده قبلی
            $existingUser = DB::table('users')
                ->where('username', $username)
                ->first();
                
            if ($existingUser) {
                return [
                    'success' => false,
                    'message' => 'این نام کاربری قبلاً توسط کاربر دیگری استفاده شده است.'
                ];
            }
            
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
            
            // دریافت اطلاعات اضافی کاربر
            $userExtra = DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->first();
                
            if (!$userExtra) {
                return [
                    'success' => false,
                    'message' => 'اطلاعات کاربر یافت نشد.'
                ];
            }
            
            // بررسی کافی بودن دلتا کوین
            if ($userExtra['delta_coins'] < 10) {
                return [
                    'success' => false,
                    'message' => "موجودی شما {$userExtra['delta_coins']} دلتا کوین است. برای تغییر نام کاربری نیاز به حداقل ۱۰ دلتا کوین دارید.",
                    'delta_coins' => $userExtra['delta_coins']
                ];
            }
            
            // تغییر نام کاربری و کسر دلتا کوین
            DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->update([
                    'username' => $username,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            // کسر دلتا کوین
            $newDeltaCoins = $userExtra['delta_coins'] - 10;
            
            DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->update([
                    'delta_coins' => $newDeltaCoins
                ]);
                
            // ثبت تراکنش دلتا کوین
            DB::table('delta_coin_transactions')->insert([
                'user_id' => $user['id'],
                'amount' => -10,
                'reason' => 'تغییر نام کاربری',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'message' => 'نام کاربری شما با موفقیت به ' . $username . ' تغییر یافت و ۱۰ دلتا کوین از حساب شما کسر شد.',
                'username' => $username,
                'delta_coins' => $newDeltaCoins
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در تغییر نام کاربری: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در تغییر نام کاربری: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ارسال پروفایل و بیوگرافی به کانال ادمین برای تایید
     * @param string $type نوع محتوا (photo یا bio)
     * @param string $content محتوای ارسالی
     * @param array $user اطلاعات کاربر
     * @return bool
     */
    private function sendToAdminChannel($type, $content, $user)
    {
        try {
            // دریافت آیدی کانال ادمین از تنظیمات
            $admin_channel = DB::table('bot_settings')
                ->where('name', 'admin_channel_id')
                ->first();
                
            if (!$admin_channel) {
                error_log("خطا: آیدی کانال ادمین در تنظیمات یافت نشد.");
                return false;
            }
            
            $channel_id = $admin_channel['value'];
            
            // ساخت پیام
            $message = "درخواست تایید ";
            $message .= $type === 'photo' ? "عکس پروفایل" : "بیوگرافی";
            $message .= " کاربر:\n\n";
            $message .= "نام کاربری: " . ($user['username'] ? '@' . $user['username'] : 'بدون نام کاربری') . "\n";
            $message .= "تلگرام آیدی: " . $user['telegram_id'] . "\n";
            $message .= "نام: " . $user['first_name'] . ' ' . $user['last_name'] . "\n\n";
            
            if ($type === 'bio') {
                $message .= "بیوگرافی ارسالی:\n" . $content;
            }
            
            // ساخت دکمه‌های اینلاین
            $inline_keyboard = [
                [
                    [
                        'text' => 'تایید ✅',
                        'callback_data' => "approve_{$type}_{$user['id']}"
                    ],
                    [
                        'text' => 'رد ❌',
                        'callback_data' => "reject_{$type}_{$user['id']}"
                    ]
                ],
                [
                    [
                        'text' => 'اطلاعات کاربر 👤',
                        'callback_data' => "user_info_{$user['id']}"
                    ]
                ]
            ];
            
            $reply_markup = json_encode([
                'inline_keyboard' => $inline_keyboard
            ]);
            
            // ارسال پیام به کانال
            $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
            
            if ($type === 'photo') {
                // در اینجا باید کد ارسال عکس به کانال نوشته شود
                // می‌توان از متدهای API تلگرام مانند sendPhoto استفاده کرد
            } else {
                // ارسال متن
                $params = [
                    'chat_id' => $channel_id,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $reply_markup
                ];
                
                $url = "https://api.telegram.org/bot{$telegram_token}/sendMessage";
                $this->sendTelegramRequest($url, $params);
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log("خطا در ارسال به کانال ادمین: " . $e->getMessage());
            return false;
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
    
    /**
     * به‌روزرسانی پورسانت زیرمجموعه گیری
     * @param string $type نوع پورسانت
     * @return bool
     */
    private function updateReferralCommission($type)
    {
        try {
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            if (!$user) {
                return false;
            }
            
            // دریافت زیرمجموعه گیرنده‌ها
            $referral = DB::table('referrals')
                ->where('referred_id', $user['id'])
                ->first();
                
            if (!$referral) {
                return false;
            }
            
            // تنظیم وضعیت مرحله در جدول زیرمجموعه‌ها
            if ($type === 'profile_completion') {
                DB::table('referrals')
                    ->where('id', $referral['id'])
                    ->update([
                        'profile_completed' => true,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else if ($type === 'first_win') {
                DB::table('referrals')
                    ->where('id', $referral['id'])
                    ->update([
                        'first_win' => true,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            } else if ($type === 'thirty_wins') {
                DB::table('referrals')
                    ->where('id', $referral['id'])
                    ->update([
                        'thirty_wins' => true,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            }
            
            // دریافت مقدار پورسانت از تنظیمات
            $commission_setting = DB::table('bot_settings')
                ->where('name', "referral_commission_{$type}")
                ->first();
                
            if (!$commission_setting) {
                // تنظیم مقادیر پیش‌فرض
                if ($type === 'profile_completion') {
                    $commission_amount = 3;
                } else if ($type === 'first_win') {
                    $commission_amount = 1.5;
                } else if ($type === 'thirty_wins') {
                    $commission_amount = 5;
                } else if ($type === 'initial') {
                    $commission_amount = 0.5;
                } else {
                    $commission_amount = 0;
                }
            } else {
                $commission_amount = floatval($commission_setting['value']);
            }
            
            if ($commission_amount <= 0) {
                return false;
            }
            
            // دریافت اطلاعات معرف
            $referrer = DB::table('users')
                ->where('id', $referral['referrer_id'])
                ->first();
                
            if (!$referrer) {
                return false;
            }
            
            // دریافت اطلاعات اضافی معرف
            $referrer_extra = DB::table('users_extra')
                ->where('user_id', $referrer['id'])
                ->first();
                
            if (!$referrer_extra) {
                return false;
            }
            
            // اضافه کردن دلتا کوین به معرف
            $new_delta_coins = $referrer_extra['delta_coins'] + $commission_amount;
            
            DB::table('users_extra')
                ->where('user_id', $referrer['id'])
                ->update([
                    'delta_coins' => $new_delta_coins
                ]);
                
            // ثبت تراکنش دلتا کوین
            $reason = "پورسانت ";
            if ($type === 'profile_completion') {
                $reason .= "تکمیل پروفایل";
            } else if ($type === 'first_win') {
                $reason .= "اولین برد";
            } else if ($type === 'thirty_wins') {
                $reason .= "30 برد";
            } else if ($type === 'initial') {
                $reason .= "عضویت اولیه";
            }
            $reason .= " کاربر " . ($user['username'] ? '@' . $user['username'] : $user['telegram_id']);
            
            DB::table('delta_coin_transactions')->insert([
                'user_id' => $referrer['id'],
                'amount' => $commission_amount,
                'reason' => $reason,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // ارسال پیام به معرف
            $message = "🎁 *دریافت پورسانت زیرمجموعه گیری*\n\n";
            $message .= "تبریک! شما مقدار {$commission_amount} دلتا کوین بابت ";
            
            if ($type === 'profile_completion') {
                $message .= "تکمیل پروفایل";
            } else if ($type === 'first_win') {
                $message .= "اولین برد";
            } else if ($type === 'thirty_wins') {
                $message .= "30 برد";
            } else if ($type === 'initial') {
                $message .= "عضویت اولیه";
            }
            
            $message .= " زیرمجموعه خود دریافت کردید.\n\n";
            $message .= "موجودی فعلی: {$new_delta_coins} دلتا کوین";
            
            $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
            sendMessage($telegram_token, $referrer['telegram_id'], $message);
            
            return true;
            
        } catch (\Exception $e) {
            error_log("خطا در به‌روزرسانی پورسانت زیرمجموعه گیری: " . $e->getMessage());
            return false;
        }
    }
}