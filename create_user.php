<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// اطلاعات پایگاه داده
$host = $_ENV['PGHOST'] ?? 'localhost';
$port = $_ENV['PGPORT'] ?? '5432';
$dbname = $_ENV['PGDATABASE'] ?? 'postgres';
$user = $_ENV['PGUSER'] ?? 'postgres';
$password = $_ENV['PGPASSWORD'] ?? '';

try {
    // اتصال به پایگاه داده
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "اتصال به پایگاه داده با موفقیت انجام شد\n";
    
    // بررسی وجود کاربر
    $telegram_id = 198623746; // از لاگ‌های قبلی
    $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = :telegram_id");
    $stmt->execute(['telegram_id' => $telegram_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "کاربر با تلگرام آیدی {$telegram_id} قبلاً در دیتابیس موجود است (ID: {$user['id']})\n";
    } else {
        // ایجاد کاربر جدید
        $stmt = $pdo->prepare("INSERT INTO users (telegram_id, username, first_name, last_name, created_at) VALUES (:telegram_id, :username, :first_name, :last_name, :created_at) RETURNING id");
        $stmt->execute([
            'telegram_id' => $telegram_id,
            'username' => 'pooya12345678910',
            'first_name' => 'Pooya',
            'last_name' => '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $user_id = $stmt->fetchColumn();
        echo "کاربر جدید با تلگرام آیدی {$telegram_id} در دیتابیس ثبت شد (ID: {$user_id})\n";
        
        // ایجاد رکورد در users_extra
        $stmt = $pdo->prepare("INSERT INTO users_extra (user_id, friends, matches, wins, losses, delta_coins, profile_status, created_at) VALUES (:user_id, :friends, :matches, :wins, :losses, :delta_coins, :profile_status, :created_at)");
        $stmt->execute([
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
    }
    
    // نمایش همه کاربران
    echo "\nلیست تمام کاربران:\n";
    $stmt = $pdo->query("SELECT * FROM users");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Telegram ID: {$user['telegram_id']}, Username: {$user['username']}\n";
    }
    
} catch (PDOException $e) {
    echo "خطا در پایگاه داده: " . $e->getMessage() . "\n";
}