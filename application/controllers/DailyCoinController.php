<?php
namespace application\controllers;

use Application\Model\DB;

class DailyCoinController
{
    private $telegram_id;
    
    public function __construct($telegram_id)
    {
        $this->telegram_id = $telegram_id;
    }
    
    /**
     * بررسی دریافت دلتا کوین روزانه
     * @return array وضعیت دریافت روزانه
     */
    public function checkDailyCoin()
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
            
            // بررسی عضویت در کانال‌های اسپانسر
            $channelStatus = $this->checkChannelMembership();
            
            if (!$channelStatus['is_member']) {
                return [
                    'success' => false,
                    'message' => 'شما هنوز در کانال‌های ذکر شده عضو نشده‌اید.',
                    'channels' => $channelStatus['channels']
                ];
            }
            
            // بررسی دریافت قبلی در امروز
            $today = date('Y-m-d');
            $dailyCoin = DB::table('daily_coins')
                ->where('user_id', $user['id'])
                ->where('claim_date', $today)
                ->first();
                
            if ($dailyCoin) {
                // کاربر قبلاً امروز دریافت کرده است
                // محاسبه زمان باقی‌مانده تا فردا
                $now = time();
                $tomorrow = strtotime($today . ' 23:59:59');
                $remainingSeconds = $tomorrow - $now;
                $remainingHours = floor($remainingSeconds / 3600);
                $remainingMinutes = floor(($remainingSeconds % 3600) / 60);
                
                return [
                    'success' => false,
                    'message' => "شما دلتا کوین امروز را دریافت کرده‌اید! لطفاً {$remainingHours} ساعت و {$remainingMinutes} دقیقه دیگر امتحان کنید.",
                    'already_claimed' => true,
                    'amount' => $dailyCoin['amount'],
                    'remaining_hours' => $remainingHours,
                    'remaining_minutes' => $remainingMinutes
                ];
            }
            
            return [
                'success' => true,
                'message' => 'شما می‌توانید دلتا کوین روزانه خود را دریافت کنید.',
                'can_claim' => true
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در بررسی دلتا کوین روزانه: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در بررسی دلتا کوین روزانه: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * دریافت دلتا کوین روزانه
     * @return array نتیجه عملیات
     */
    public function claimDailyCoin()
    {
        try {
            // بررسی وضعیت دریافت
            $checkResult = $this->checkDailyCoin();
            
            if (!$checkResult['success']) {
                return $checkResult;
            }
            
            // دریافت اطلاعات کاربر
            $user = DB::table('users')
                ->where('telegram_id', $this->telegram_id)
                ->first();
                
            // محاسبه مقدار دلتا کوین تصادفی
            $amount = $this->getRandomCoinAmount();
            
            // ثبت دریافت در جدول daily_coins
            $claimId = DB::table('daily_coins')->insert([
                'user_id' => $user['id'],
                'amount' => $amount,
                'claim_date' => date('Y-m-d'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // اضافه کردن دلتا کوین به کاربر
            $userExtra = DB::table('users_extra')
                ->where('user_id', $user['id'])
                ->first();
                
            if ($userExtra) {
                $newBalance = $userExtra['delta_coins'] + $amount;
                
                DB::table('users_extra')
                    ->where('user_id', $user['id'])
                    ->update([
                        'delta_coins' => $newBalance
                    ]);
            } else {
                // ایجاد رکورد جدید در جدول users_extra
                DB::table('users_extra')->insert([
                    'user_id' => $user['id'],
                    'delta_coins' => $amount,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $newBalance = $amount;
            }
            
            // ثبت تراکنش دلتا کوین
            DB::table('delta_coin_transactions')->insert([
                'user_id' => $user['id'],
                'amount' => $amount,
                'reason' => 'دلتا کوین روزانه',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'success' => true,
                'message' => "تبریک! مقدار {$amount} دلتا کوین دریافت کردید.",
                'amount' => $amount,
                'new_balance' => $newBalance
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در دریافت دلتا کوین روزانه: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'خطا در دریافت دلتا کوین روزانه: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * بررسی عضویت در کانال‌های اسپانسر
     * @return array وضعیت عضویت
     */
    private function checkChannelMembership()
    {
        // دریافت لیست کانال‌ها از تنظیمات
        $channelsSetting = DB::table('bot_settings')
            ->where('name', 'daily_coin_channels')
            ->first();
            
        if (!$channelsSetting) {
            // اگر تنظیمات وجود نداشت، عضویت را تایید می‌کنیم
            return [
                'is_member' => true,
                'channels' => []
            ];
        }
        
        $channelsData = json_decode($channelsSetting['value'], true);
        
        if (!isset($channelsData['channels']) || empty($channelsData['channels'])) {
            // اگر لیست کانال‌ها خالی بود، عضویت را تایید می‌کنیم
            return [
                'is_member' => true,
                'channels' => []
            ];
        }
        
        $channels = $channelsData['channels'];
        $telegram_token = $_ENV['TELEGRAM_BOT_TOKEN'];
        
        // بررسی عضویت در هر کانال
        $notMember = [];
        
        foreach ($channels as $channel) {
            $isMember = $this->checkUserMembershipInChannel($channel, $telegram_token);
            
            if (!$isMember) {
                $chatInfo = $this->getChatInfo($channel, $telegram_token);
                $notMember[] = [
                    'chat_id' => $channel,
                    'title' => $chatInfo ? $chatInfo['title'] : $channel,
                    'link' => $chatInfo && isset($chatInfo['username']) ? 'https://t.me/' . $chatInfo['username'] : null
                ];
            }
        }
        
        return [
            'is_member' => empty($notMember),
            'channels' => $notMember
        ];
    }
    
    /**
     * بررسی عضویت کاربر در یک کانال
     * @param string $chat_id شناسه چت
     * @param string $telegram_token توکن ربات تلگرام
     * @return bool
     */
    private function checkUserMembershipInChannel($chat_id, $telegram_token)
    {
        $url = "https://api.telegram.org/bot{$telegram_token}/getChatMember";
        $params = [
            'chat_id' => $chat_id,
            'user_id' => $this->telegram_id
        ];
        
        $response = $this->sendTelegramRequest($url, $params);
        
        if (!$response) {
            return false;
        }
        
        // بررسی وضعیت عضویت
        $status = $response['status'];
        
        return in_array($status, ['creator', 'administrator', 'member', 'restricted']);
    }
    
    /**
     * دریافت اطلاعات چت
     * @param string $chat_id شناسه چت
     * @param string $telegram_token توکن ربات تلگرام
     * @return array|null
     */
    private function getChatInfo($chat_id, $telegram_token)
    {
        $url = "https://api.telegram.org/bot{$telegram_token}/getChat";
        $params = [
            'chat_id' => $chat_id
        ];
        
        return $this->sendTelegramRequest($url, $params);
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
     * دریافت مقدار تصادفی دلتا کوین
     * @return float مقدار تصادفی
     */
    private function getRandomCoinAmount()
    {
        // دریافت محدوده از تنظیمات
        $minSetting = DB::table('bot_settings')
            ->where('name', 'daily_coin_min')
            ->first();
            
        $maxSetting = DB::table('bot_settings')
            ->where('name', 'daily_coin_max')
            ->first();
            
        $min = $minSetting ? floatval($minSetting['value']) : 0.1;
        $max = $maxSetting ? floatval($maxSetting['value']) : 1.0;
        
        // محاسبه مقدار تصادفی با یک رقم اعشار
        $amount = mt_rand($min * 10, $max * 10) / 10;
        
        // روز اول یک دلتا کوین ثابت
        $today = date('Y-m-d');
        $dailyCoinCount = DB::table('daily_coins')
            ->where('user_id', DB::table('users')->where('telegram_id', $this->telegram_id)->first()['id'])
            ->count();
            
        if ($dailyCoinCount === 0) {
            return 1.0;
        }
        
        return $amount;
    }
}