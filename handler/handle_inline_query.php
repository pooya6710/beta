<?php

use Application\Model\DB;
$data = $telegram->get()->inline_query->query;





if ($data == 'referral') {
    $telegram->answerInlineQuery()->article('referral' , 'زیر مجموعه گیری' , ['message_text' => "سلام دوست من از طریق این ربات میتونی با افراد ناشناس بازی کنی و باهاشون چت کنی و در انواع مسابقات گروهی شرکت کنی🏆👀

فرق این ربات با رباتای دیگه اینه که میتونی کنار بازی کردن کسب درآمد هم کنی💸

همین حالا رو لینک زیر کلیک کن و باهام بازی کن 👇❤️"] , [
        "inline_keyboard" => [
            [["text" => "ورود به ربات 🎉" , 'url' => "https://t.me/DozGame_Robot?start=re_".$user->userData()['username']]]
        ]
    ]);
}
else {
//    $telegram->answerInlineQuery()->article('start_xo' , 'شروع بازی دوز' , ['message_text' => "🎮 برای بازی با دوست خود روی دکمه من پایه ام کلیک کنید . "] , [
//        "inline_keyboard" => [
//            [["text" => "من پایه ام ✋" , 'callback_data' => 'p1-'.$telegram->from_id]]
//        ]
//    ]);
}
