<?php
namespace application\controllers;

use Application\Model\DB;

class StatisticsController
{
    /**
     * ذخیره آمار روزانه در دیتابیس
     * @return bool
     */
    public static function saveDaily()
    {
        try {
            // بررسی آیا آمار امروز قبلاً ذخیره شده است
            $today = date('Y-m-d');
            $stats_exists = DB::table('admin_statistics')
                ->where('date', $today)
                ->exists();
                
            if ($stats_exists) {
                // به روز رسانی آمار موجود
                return self::updateDailyStatistics();
            }
            
            // محاسبه آمار
            $total_users = self::countTotalUsers();
            $active_users = self::countActiveUsersToday();
            $new_users = self::countNewUsersToday();
            $total_games = self::countTotalGames();
            $active_games = self::countActiveGames();
            $completed_games = self::countCompletedGamesToday();
            $total_delta_coins = self::getTotalDeltaCoins();
            
            // ذخیره آمار در دیتابیس
            $stats = [
                'date' => $today,
                'total_users' => $total_users,
                'active_users' => $active_users,
                'new_users' => $new_users,
                'total_games' => $total_games,
                'active_games' => $active_games,
                'completed_games' => $completed_games,
                'total_delta_coins' => $total_delta_coins
            ];
            
            DB::table('admin_statistics')->insert($stats);
            
            return true;
        } catch (\Exception $e) {
            error_log("خطا در ذخیره آمار روزانه: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * به روز رسانی آمار روزانه
     * @return bool
     */
    private static function updateDailyStatistics()
    {
        try {
            $today = date('Y-m-d');
            
            // محاسبه آمار
            $total_users = self::countTotalUsers();
            $active_users = self::countActiveUsersToday();
            $new_users = self::countNewUsersToday();
            $total_games = self::countTotalGames();
            $active_games = self::countActiveGames();
            $completed_games = self::countCompletedGamesToday();
            $total_delta_coins = self::getTotalDeltaCoins();
            
            // به روز رسانی آمار در دیتابیس
            $stats = [
                'total_users' => $total_users,
                'active_users' => $active_users,
                'new_users' => $new_users,
                'total_games' => $total_games,
                'active_games' => $active_games,
                'completed_games' => $completed_games,
                'total_delta_coins' => $total_delta_coins
            ];
            
            DB::table('admin_statistics')
                ->where('date', $today)
                ->update($stats);
            
            return true;
        } catch (\Exception $e) {
            error_log("خطا در به روز رسانی آمار روزانه: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت تعداد کل کاربران
     * @return int
     */
    public static function countTotalUsers()
    {
        $result = DB::rawQuery("SELECT COUNT(*) as count FROM users");
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * دریافت تعداد کاربران فعال امروز
     * @return int
     */
    public static function countActiveUsersToday()
    {
        $result = DB::rawQuery("SELECT COUNT(*) as count FROM users WHERE DATE(last_activity) = CURRENT_DATE");
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * دریافت تعداد کاربران جدید امروز
     * @return int
     */
    public static function countNewUsersToday()
    {
        $result = DB::rawQuery("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURRENT_DATE");
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * دریافت تعداد بازی‌های در جریان
     * @return int
     */
    public static function countActiveGames()
    {
        $result = DB::rawQuery("SELECT COUNT(*) as count FROM matches WHERE status = 'active'");
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * دریافت تعداد کل بازی‌ها
     * @return int
     */
    public static function countTotalGames()
    {
        $result = DB::rawQuery("SELECT COUNT(*) as count FROM matches");
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * دریافت تعداد بازی‌های تکمیل شده امروز
     * @return int
     */
    public static function countCompletedGamesToday()
    {
        $result = DB::rawQuery("SELECT COUNT(*) as count FROM matches WHERE status = 'completed' AND DATE(updated_at) = CURRENT_DATE");
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * دریافت میانگین دلتا کوین کاربران
     * @return float
     */
    public static function getAverageCoins()
    {
        $result = DB::rawQuery("SELECT AVG(delta_coins) as avg FROM users_extra WHERE delta_coins > 0");
        return round($result[0]['avg'] ?? 0, 2);
    }
    
    /**
     * دریافت تعداد بازی‌های انجام شده امروز
     * @return int
     */
    public static function countGamesToday()
    {
        $result = DB::rawQuery("SELECT COUNT(*) as count FROM matches WHERE DATE(created_at) = CURRENT_DATE");
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * دریافت مجموع دلتا کوین‌های موجود در سیستم
     * @return int
     */
    public static function getTotalDeltaCoins()
    {
        $result = DB::rawQuery("SELECT SUM(delta_coins) as total FROM users_extra");
        return $result[0]['total'] ?? 0;
    }
    
    /**
     * دریافت گزارش آماری برای ادمین
     * @return array
     */
    public static function getAdminReport()
    {
        $stats = [];
        
        // تعداد کل کاربران
        $stats['total_users'] = self::countTotalUsers();
        
        // تعداد بازیکنان فعال امروز
        $stats['active_users_today'] = self::countActiveUsersToday();
        
        // تعداد کاربران جدید امروز
        $stats['new_users_today'] = self::countNewUsersToday();
        
        // تعداد بازی‌های در جریان
        $stats['active_games'] = self::countActiveGames();
        
        // تعداد کل بازی‌ها
        $stats['total_games'] = self::countTotalGames();
        
        // میانگین دلتا کوین کاربران
        $stats['avg_delta_coins'] = self::getAverageCoins();
        
        // تعداد بازی‌های انجام شده امروز
        $stats['games_today'] = self::countGamesToday();
        
        // تعداد بازی‌های تکمیل شده امروز
        $stats['completed_games_today'] = self::countCompletedGamesToday();
        
        // مجموع دلتا کوین‌های موجود در سیستم
        $stats['total_delta_coins'] = self::getTotalDeltaCoins();
        
        return $stats;
    }
}