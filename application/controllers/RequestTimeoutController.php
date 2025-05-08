<?php
namespace application\controllers;

use Application\Model\DB;

class RequestTimeoutController
{
    // مدت زمان انقضای درخواست‌های بازی (به ثانیه)
    const GAME_REQUEST_TIMEOUT = 3600; // 1 ساعت
    
    // مدت زمان انقضای درخواست‌های دوستی (به ثانیه)
    const FRIEND_REQUEST_TIMEOUT = 43200; // 12 ساعت
    
    /**
     * بررسی و به‌روزرسانی وضعیت درخواست بازی
     * @param int $sender_id شناسه کاربر فرستنده
     * @param int $receiver_id شناسه کاربر گیرنده
     * @return array وضعیت درخواست
     */
    public static function checkGameRequest($sender_id, $receiver_id)
    {
        try {
            // جستجوی درخواست موجود
            $request = DB::table('game_requests')
                ->where('sender_id', $sender_id)
                ->where('receiver_id', $receiver_id)
                ->where('status', 'pending')
                ->first();
                
            if (!$request) {
                return [
                    'exists' => false,
                    'expired' => false,
                    'can_send' => true,
                    'remaining_time' => 0
                ];
            }
            
            // محاسبه زمان سپری شده از درخواست
            $request_time = strtotime($request['created_at']);
            $current_time = time();
            $elapsed_time = $current_time - $request_time;
            
            // بررسی انقضای درخواست
            if ($elapsed_time >= self::GAME_REQUEST_TIMEOUT) {
                // حذف درخواست منقضی شده
                DB::table('game_requests')
                    ->where('id', $request['id'])
                    ->delete();
                    
                return [
                    'exists' => false,
                    'expired' => true,
                    'can_send' => true,
                    'remaining_time' => 0
                ];
            }
            
            // محاسبه زمان باقی‌مانده تا امکان ارسال درخواست مجدد
            $remaining_time = self::GAME_REQUEST_TIMEOUT - $elapsed_time;
            $remaining_minutes = ceil($remaining_time / 60);
            
            return [
                'exists' => true,
                'expired' => false,
                'can_send' => false,
                'remaining_time' => $remaining_time,
                'remaining_minutes' => $remaining_minutes,
                'request_id' => $request['id']
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در بررسی درخواست بازی: " . $e->getMessage());
            return [
                'exists' => false,
                'expired' => false,
                'can_send' => true,
                'remaining_time' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * بررسی و به‌روزرسانی وضعیت درخواست دوستی
     * @param int $sender_id شناسه کاربر فرستنده
     * @param int $receiver_id شناسه کاربر گیرنده
     * @return array وضعیت درخواست
     */
    public static function checkFriendRequest($sender_id, $receiver_id)
    {
        try {
            // جستجوی درخواست موجود
            $request = DB::table('friend_requests')
                ->where('from_user_id', $sender_id)
                ->where('to_user_id', $receiver_id)
                ->where('status', 'pending')
                ->first();
                
            if (!$request) {
                return [
                    'exists' => false,
                    'expired' => false,
                    'can_send' => true,
                    'remaining_time' => 0
                ];
            }
            
            // محاسبه زمان سپری شده از درخواست
            $request_time = strtotime($request['created_at']);
            $current_time = time();
            $elapsed_time = $current_time - $request_time;
            
            // بررسی انقضای درخواست
            if ($elapsed_time >= self::FRIEND_REQUEST_TIMEOUT) {
                // حذف درخواست منقضی شده
                DB::table('friend_requests')
                    ->where('id', $request['id'])
                    ->delete();
                    
                return [
                    'exists' => false,
                    'expired' => true,
                    'can_send' => true,
                    'remaining_time' => 0
                ];
            }
            
            // محاسبه زمان باقی‌مانده تا امکان ارسال درخواست مجدد
            $remaining_time = self::FRIEND_REQUEST_TIMEOUT - $elapsed_time;
            $remaining_hours = floor($remaining_time / 3600);
            $remaining_minutes = floor(($remaining_time % 3600) / 60);
            
            return [
                'exists' => true,
                'expired' => false,
                'can_send' => false,
                'remaining_time' => $remaining_time,
                'remaining_hours' => $remaining_hours,
                'remaining_minutes' => $remaining_minutes,
                'request_id' => $request['id']
            ];
            
        } catch (\Exception $e) {
            error_log("خطا در بررسی درخواست دوستی: " . $e->getMessage());
            return [
                'exists' => false,
                'expired' => false,
                'can_send' => true,
                'remaining_time' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * ثبت درخواست بازی جدید
     * @param int $sender_id شناسه کاربر فرستنده
     * @param int $receiver_id شناسه کاربر گیرنده
     * @return bool|int شناسه درخواست یا false در صورت خطا
     */
    public static function createGameRequest($sender_id, $receiver_id)
    {
        try {
            // بررسی وضعیت درخواست قبلی
            $request_status = self::checkGameRequest($sender_id, $receiver_id);
            
            if (!$request_status['can_send']) {
                return false;
            }
            
            // ثبت درخواست جدید
            $request_id = DB::table('game_requests')->insert([
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $request_id;
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت درخواست بازی: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ثبت درخواست دوستی جدید
     * @param int $sender_id شناسه کاربر فرستنده
     * @param int $receiver_id شناسه کاربر گیرنده
     * @return bool|int شناسه درخواست یا false در صورت خطا
     */
    public static function createFriendRequest($sender_id, $receiver_id)
    {
        try {
            // بررسی وضعیت درخواست قبلی
            $request_status = self::checkFriendRequest($sender_id, $receiver_id);
            
            if (!$request_status['can_send']) {
                return false;
            }
            
            // ثبت درخواست جدید
            $request_id = DB::table('friend_requests')->insert([
                'from_user_id' => $sender_id,
                'to_user_id' => $receiver_id,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $request_id;
            
        } catch (\Exception $e) {
            error_log("خطا در ثبت درخواست دوستی: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * پذیرش درخواست بازی
     * @param int $request_id شناسه درخواست
     * @return bool
     */
    public static function acceptGameRequest($request_id)
    {
        try {
            DB::table('game_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'accepted',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            return true;
            
        } catch (\Exception $e) {
            error_log("خطا در پذیرش درخواست بازی: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * رد درخواست بازی
     * @param int $request_id شناسه درخواست
     * @return bool
     */
    public static function rejectGameRequest($request_id)
    {
        try {
            DB::table('game_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            return true;
            
        } catch (\Exception $e) {
            error_log("خطا در رد درخواست بازی: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * پذیرش درخواست دوستی
     * @param int $request_id شناسه درخواست
     * @return bool
     */
    public static function acceptFriendRequest($request_id)
    {
        try {
            DB::table('friend_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'accepted',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            return true;
            
        } catch (\Exception $e) {
            error_log("خطا در پذیرش درخواست دوستی: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * رد درخواست دوستی
     * @param int $request_id شناسه درخواست
     * @return bool
     */
    public static function rejectFriendRequest($request_id)
    {
        try {
            DB::table('friend_requests')
                ->where('id', $request_id)
                ->update([
                    'status' => 'rejected',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            return true;
            
        } catch (\Exception $e) {
            error_log("خطا در رد درخواست دوستی: " . $e->getMessage());
            return false;
        }
    }
}