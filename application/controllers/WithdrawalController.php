<?php
namespace application\controllers;

use Application\Model\DB;

class WithdrawalController
{
    private $telegram_id;
    
    public function __construct($telegram_id)
    {
        $this->telegram_id = $telegram_id;
    }
    
    /**
     * ثبت درخواست برداشت دلتا کوین
     * @param int $amount مقدار دلتا کوین
     * @param string $type نوع برداشت (bank یا trx)
     * @param string $wallet آدرس کیف پول یا شماره کارت
     * @return array نتیجه عملیات
     */
    public function createWithdrawalRequest($amount, $type, $wallet)
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
            
            // بررسی موجودی دلتا کوین
            if ($userExtra['delta_coins'] < $amount) {
                return [
                    'success' => false,
                    'message' => "موجودی شما {$userExtra['delta_coins']} دلتا کوین است. مقدار وارد شده بیشتر از موجودی است!"
                ];
            }
            
            // دریافت حداقل مقدار برداشت از تنظیمات
            $minWithdrawalAmount = $this->getMinWithdrawalAmount();
            
            // بررسی حداقل مقدار برداشت
            if ($amount < $minWithdrawalAmount) {
                return [
                    'success' => false,
                    'message' => "حداقل برداشت دلتا کوین {$minWithdrawalAmount} عدد می‌باشد!"
                ];
            }
            
            // بررسی مضرب 10 بودن
            $step = $this->getWithdrawalStep();
            if ($amount % $step !== 0) {
                // گرد کردن به نزدیکترین مضرب
                $amount = floor($amount / $step) * $step;
                return [
                    'success' => false,
                    'message' => "مقدار برداشت باید مضربی از {$step} باشد. مقدار درخواستی شما به {$amount} تغییر یافت.",
                    'corrected_amount' => $amount
                ];
            }
            
            // ثبت درخواست برداشت
            $requestData = [
                'user_id' => $user['id'],
                'amount' => $amount,
                'type' => $type
            ];
            
            // ثبت اطلاعات کارت بانکی یا کیف پول
            if ($type === 'bank') {
                // برای برداشت بانکی
                $requestData['bank_card_number'] = $wallet;
            } else {
                // برای برداشت ترونی
                $requestData['wallet_address'] = $wallet;
            }
            
            // ثبت درخواست در دیتابیس
            $request_id = DB::table('withdrawal_requests')->insert($requestData);
            
            // کم کردن مقدار دلتا کوین از کاربر
            DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->update(['delta_coins' => $userExtra['delta_coins'] - $amount]);
                
            // محاسبه مقدار به تومان
            $amount_toman = $amount * $this->getDeltaCoinPrice();
            
            return [
                'success' => true,
                'message' => 'درخواست برداشت با موفقیت ثبت شد.',
                'request_id' => $request_id,
                'amount' => $amount,
                'amount_toman' => $amount_toman,
                'wallet' => $wallet,
                'type' => $type
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در ثبت درخواست برداشت: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * تأیید درخواست برداشت
     * @param int $request_id شناسه درخواست
     * @param int $admin_id شناسه ادمین
     * @return bool
     */
    public function approveWithdrawalRequest($request_id, $admin_id)
    {
        try {
            // دریافت اطلاعات درخواست
            $request = DB::table('withdrawal_requests')
                ->where('id', $request_id)
                ->first();
                
            if (!$request) {
                return false;
            }
            
            // تغییر وضعیت درخواست به تکمیل شده
            DB::table('withdrawal_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'completed',
                    'processed_by' => $admin_id,
                    'processed_at' => date('Y-m-d H:i:s')
                ]);
                
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * رد درخواست برداشت
     * @param int $request_id شناسه درخواست
     * @param int $admin_id شناسه ادمین
     * @return bool
     */
    public function rejectWithdrawalRequest($request_id, $admin_id)
    {
        try {
            // دریافت اطلاعات درخواست
            $request = DB::table('withdrawal_requests')
                ->where('id', $request_id)
                ->first();
                
            if (!$request) {
                return false;
            }
            
            // تغییر وضعیت درخواست به رد شده
            DB::table('withdrawal_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'rejected',
                    'processed_by' => $admin_id,
                    'processed_at' => date('Y-m-d H:i:s')
                ]);
                
            // برگرداندن دلتا کوین به کاربر
            $user_extra = DB::table('users_extra')
                ->where('user_id', $request['user_id'])
                ->first();
                
            if ($user_extra) {
                DB::table('users_extra')
                    ->where('user_id', $request['user_id'])
                    ->update(['delta_coins' => $user_extra['delta_coins'] + $request['amount']]);
            }
                
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * دریافت لیست درخواست‌های برداشت
     * @param string $status وضعیت درخواست‌ها
     * @param int $limit تعداد درخواست‌ها برای دریافت
     * @return array
     */
    public function getWithdrawalRequests($status = 'pending', $limit = 10)
    {
        try {
            $requests = DB::table('withdrawal_requests')
                ->where('status', $status)
                ->orderBy('created_at', 'DESC')
                ->limit($limit)
                ->get();
                
            return $requests;
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * دریافت حداقل مقدار برداشت از تنظیمات
     * @return int
     */
    public function getMinWithdrawalAmount()
    {
        $minAmount = $this->getSetting('min_withdrawal_amount', 50);
        return intval($minAmount);
    }
    
    /**
     * دریافت مضرب برداشت از تنظیمات
     * @return int
     */
    public function getWithdrawalStep()
    {
        $step = $this->getSetting('withdrawal_step', 10);
        return intval($step);
    }
    
    /**
     * دریافت قیمت دلتا کوین از تنظیمات
     * @return int
     */
    public function getDeltaCoinPrice()
    {
        $price = $this->getSetting('delta_coin_price', 1000);
        return intval($price);
    }
    
    /**
     * دریافت یک تنظیم از دیتابیس
     * @param string $name نام تنظیم
     * @param mixed $default مقدار پیش‌فرض
     * @return mixed
     */
    private function getSetting($name, $default = null)
    {
        $setting = DB::table('bot_settings')
            ->where('name', $name)
            ->first();
            
        if (!$setting) {
            return $default;
        }
        
        return $setting['value'];
    }
}