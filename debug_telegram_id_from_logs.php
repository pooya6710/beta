<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
include(__DIR__ . "/system/Loader.php");

// ایجاد کاربر در دیتابیس با telegram_id
function createUserInDatabase($telegram_id, $username, $first_name, $last_name) {
    try {
        // بررسی وجود کاربر
        $existing_user = \Application\Model\DB::table('users')
            ->where('telegram_id', $telegram_id)
            ->first();
        
        if ($existing_user) {
            echo "کاربر با تلگرام آیدی {$telegram_id} قبلاً در دیتابیس موجود است\n";
            return $existing_user['id'];
        }
        
        // ایجاد کاربر جدید
        $user_id = \Application\Model\DB::table('users')->insertGetId([
            'telegram_id' => $telegram_id,
            'username' => $username,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "کاربر جدید با تلگرام آیدی {$telegram_id} در دیتابیس ثبت شد (ID: {$user_id})\n";
        
        // ایجاد رکورد در users_extra
        \Application\Model\DB::table('users_extra')->insert([
            'user_id' => $user_id,
            'friends' => '[]',
            'matches' => 0,
            'wins' => 0,
            'losses' => 0,
            'delta_coins' => 0,
            'profile_status' => 'incomplete',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "رکورد اطلاعات تکمیلی برای کاربر با ID {$user_id} ایجاد شد\n";
        
        return $user_id;
    } catch (Exception $e) {
        echo "خطا در ثبت کاربر: " . $e->getMessage() . "\n";
        return false;
    }
}

// خواندن آخرین آپدیت‌ها از لاگ
echo "در حال بررسی لاگ‌ها برای یافتن اطلاعات کاربران...\n";

// کاربران آزمایشی (از لاگ‌های قبلی)
$test_users = [
    [
        'telegram_id' => 198623746,
        'username' => 'pooya12345678910',
        'first_name' => 'Pooya',
        'last_name' => ''
    ],
    [
        'telegram_id' => 3063173100,
        'username' => 'pooya12345678910',
        'first_name' => 'Pooya',
        'last_name' => ''
    ]
];

// ایجاد کاربران آزمایشی در دیتابیس
foreach ($test_users as $user) {
    createUserInDatabase(
        $user['telegram_id'],
        $user['username'],
        $user['first_name'],
        $user['last_name']
    );
}

echo "\nبررسی کاربران موجود در دیتابیس:\n";
$users = \Application\Model\DB::table('users')->get();
foreach ($users as $user) {
    echo "ID: {$user['id']}, Telegram ID: {$user['telegram_id']}, Username: {$user['username']}\n";
}