<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/vendor/autoload.php');
$dotenv = \Dotenv\Dotenv::createImmutable((__DIR__));
$dotenv->safeLoad();

// توکن ربات تلگرام
$token = $_ENV['TELEGRAM_TOKEN'];

// آی‌دی چت (باید با آی‌دی تلگرام خودتان جایگزین شود)
$chatId = "";
if (isset($_POST['chat_id']) && !empty($_POST['chat_id'])) {
    $chatId = $_POST['chat_id'];
}

// متن پیام
$message = "سلام! این یک پیام تست از ربات است.";
if (isset($_POST['message']) && !empty($_POST['message'])) {
    $message = $_POST['message'];
}

$result = "";

// فرستادن پیام اگر آی‌دی چت وارد شده باشد
if (!empty($chatId) && isset($_POST['send'])) {
    // لینک ارسال پیام به API تلگرام
    $apiUrl = "https://api.telegram.org/bot{$token}/sendMessage";
    
    // پارامترهای ارسال پیام
    $params = [
        'chat_id' => $chatId,
        'text' => $message
    ];
    
    // تنظیمات cURL
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    
    // دریافت پاسخ
    $response = curl_exec($ch);
    curl_close($ch);
    
    // نمایش نتیجه
    $result = json_decode($response, true);
}

// نمایش فرم و نتیجه ارسال پیام
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تست ارسال پیام</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            background-color: #f5f5f5;
        }
        h1 {
            color: #4a76a8;
            text-align: center;
        }
        form {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: Tahoma, Arial, sans-serif;
        }
        textarea {
            height: 100px;
        }
        button {
            background-color: #4a76a8;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #3a5b8c;
        }
        .result {
            margin-top: 20px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        pre {
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>تست ارسال پیام ربات تلگرام</h1>
    
    <form method="post">
        <div class="form-group">
            <label for="chat_id">آی‌دی چت (شناسه عددی کاربر):</label>
            <input type="text" id="chat_id" name="chat_id" value="<?php echo htmlspecialchars($chatId); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="message">متن پیام:</label>
            <textarea id="message" name="message"><?php echo htmlspecialchars($message); ?></textarea>
        </div>
        
        <button type="submit" name="send">ارسال پیام</button>
    </form>
    
    <?php if (!empty($result)): ?>
    <div class="result">
        <h2>نتیجه ارسال:</h2>
        <?php if ($result['ok']): ?>
            <p class="success">پیام با موفقیت ارسال شد!</p>
        <?php else: ?>
            <p class="error">خطا در ارسال پیام:</p>
            <pre><?php print_r($result); ?></pre>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="result">
        <h2>راهنما:</h2>
        <p>برای استفاده از این ابزار، شما باید شناسه عددی کاربر در تلگرام را وارد کنید. برای پیدا کردن شناسه خود، می‌توانید:</p>
        <ol>
            <li>به ربات <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a> پیام دهید.</li>
            <li>یا به ربات <a href="https://t.me/myidbot" target="_blank">@myidbot</a> پیام /getid را ارسال کنید.</li>
        </ol>
        <p>همچنین بعد از اینکه کاربر به ربات شما پیامی ارسال کند، می‌توانید شناسه او را از لاگ‌های سرور پیدا کنید.</p>
    </div>
</body>
</html>