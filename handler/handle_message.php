<?php

use Application\Model\DB;
global $telegram;
global $locale;
global $user;
global $helper;
$text = $telegram->get()->message->text;

// دیباگ: نمایش متن دریافتی از تلگرام
echo "متن دریافتی از تلگرام: [$text]\n";
// دیباگ: نمایش کد هگز متن دریافتی
echo "کد هگز متن دریافتی: " . bin2hex($text) . "\n";

// دیباگ: نمایش متن دکمه اول
$button_text = $locale->trans('keyboard.home.play_with_unknown');
echo "متن دکمه 'بازی با ناشناس': [$button_text]\n";
echo "کد هگز دکمه: " . bin2hex($button_text) . "\n";

// بررسی مقایسه
echo "مقایسه عادی: " . ($text == $button_text ? "یکسان" : "متفاوت") . "\n";
echo "مقایسه تریم شده: " . (trim($text) == trim($button_text) ? "یکسان" : "متفاوت") . "\n";
echo "strpos: " . (strpos($text, "بازی با ناشناس") !== false ? "پیدا شد" : "پیدا نشد") . "\n";
$from_id = $telegram->get()->message->from->id;
$first_name = $telegram->get()->message->from->first_name;

//$telegram->sendMessage($telegram->get())->send();


if ($text == '/cancel'){
    // پاک کردن همه بازی‌های در انتظار کاربر
    DB::table('matches')->where(['player1' => $telegram->from_id, 'status' => 'pending'])->delete();
    $telegram->sendMessage("%message.cancel_search%")->keyboard('main.home')->send();
    exit();
}
elseif ($text == '/start'){
    $telegram->sendMessage("%message.start[firstname:$first_name]%")->keyboard('main.home')->replay()->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.main.decline')){
    $telegram->sendMessage("%message.start[firstname:$first_name]%")->keyboard('main.home')->replay()->send();
    exit();
}

if (str_starts_with($text , '/start')){
    $exploded_text = explode(' ', $text);
    if (str_starts_with($exploded_text[1] , 'sg')){
        $final_exploded = explode('-' , $exploded_text[1]);
        $find_game = DB::table('matches')->where('id',  $final_exploded[1])->select('*')->first();
        if (!$find_game){
            exit();
        }
        if ($telegram->from_id != $find_game['player1'] and $telegram->from_id != $find_game['player2']){
            exit();
        }

        if($telegram->from_id == $find_game['player1']){
            $telegram->sendMessage('%message.game_link%')->inline_keyboard([
                [['text' => 'ورود به بازی', 'web_app' => ['url' => 'https://robot.bemola.site/XO/pages/match.php?id='.$find_game['id']."&hash=".$find_game['player1_hash']."&player=1"]]]
            ])->send();
            exit();
        }
        if ($telegram->from_id == $find_game['player2']){
            $telegram->sendMessage('%message.game_link%')->inline_keyboard([
                [['text' => 'ورود به بازی', 'web_app' => ['url' => 'https://robot.bemola.site/XO/pages/match.php?id='.$find_game['id']."&hash=".$find_game['player2_hash']."&player=2"]]]
            ])->send();
            exit();
        }

        exit();
    }

}

//if ($text == $locale->trans('keyboard.home.play_in_gp_or_pv')){
//    $telegram->sendMessage('%message.send_game_to_pv%')->inline_keyboard($keyboard->get('main.send_to_pv'))->send();
//    exit();
//}
// چاپ اطلاعات دیباگ
echo "متن دقیق دریافتی: [$text]\n";
echo "متن دقیق ترجمه شده برای مقایسه: [" . $locale->trans('keyboard.home.play_with_unknown') . "]\n";

// مقایسه دقیق‌تر با trim برای حذف فضاهای خالی اضافی
$translatedText = trim($locale->trans('keyboard.home.play_with_unknown'));
$receivedText = trim($text);
echo "مقایسه بعد از trim: " . ($receivedText == $translatedText ? "مساوی است" : "مساوی نیست") . "\n";

// بررسی اگر متن دکمه 'بازی با ناشناس' است
if ($text == '/start') {
    $telegram->sendMessage("%message.start[firstname:$first_name]%")->keyboard('main.home')->replay()->send();
    exit();
} elseif (str_contains($text, 'بازی با ناشناس') || trim($text) == trim($locale->trans('keyboard.home.play_with_unknown'))) {
    // بررسی وجود و معتبر بودن کاربر و داده‌های آن
$userData = $user->userData();
if ($userData && isset($userData['is_firstMatch']) && $userData['is_firstMatch']){
        $telegram->sendMessage("%message.firstMatch_unknown%")->send();
        DB::table('users')->where('telegram_id',  $telegram->from_id)->update(['is_firstMatch'=>0]);
        exit();
    }

    $oldMatchResult = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND (status = 'pending' OR status = 'playing');" , [$telegram->from_id , $telegram->from_id]);
    $oldMatch = isset($oldMatchResult[0]) ? $oldMatchResult[0] : null;
    if ($oldMatch) {
        $telegram->sendMessage("%message.u_have_old_match%")->send();
        exit();
    }else{
        $telegram->sendMessage("%message.finding_player%")->send();
        $newMatchResult = DB::rawQuery("SELECT * FROM matches WHERE player2 IS NULL AND status = 'pending' AND type = 'anonymous' ORDER BY id ASC LIMIT 1;");
        $newMatch = isset($newMatchResult[0]) ? $newMatchResult[0] : null;
        if ($newMatch){
            $player2_hash = $helper->Hash();
            DB::table('matches')->where(['id' => $newMatch['id']])->update(['player2' => $telegram->from_id , 'player2_hash' => $player2_hash , 'status' => 'playing']);

            $player2_username = DB::table('users')->where('telegram_id' , $telegram->from_id)->select('username')->get()[0]['username'];
            $player1_username = DB::table('users')->where('telegram_id' , $newMatch['player1'])->select('username')->get()[0]['username'];

            $telegram->sendMessage("%message.player_found[username:$player2_username]%")->inline_keyboard([
                [['text' => 'ورود به بازی', 'web_app' => ['url' => 'https://robot.bemola.site/XO/pages/match.php?id='.$newMatch['id']."&hash=".$newMatch['player1_hash']."&player=1"]]]
            ])->send($newMatch['player1']);
            $telegram->sendMessage('%message.u_can_chat_now%')->keyboard('main.in_chat')->send($newMatch['player1']);
            DB::table('users')->where('telegram_id' , $newMatch['player1'])->update(['step' => 'in_chat']);

            $telegram->sendMessage("%message.player_found[username:$player1_username]%")->inline_keyboard([
                [['text' => 'ورود به بازی', 'web_app' => ['url' => 'https://robot.bemola.site/XO/pages/match.php?id='.$newMatch['id']."&hash=".$player2_hash."&player=2"]]]
            ])->send($telegram->from_id);
            $telegram->sendMessage('%message.u_can_chat_now%')->keyboard('main.in_chat')->send($telegram->from_id);
            DB::table('users')->where('telegram_id' , $telegram->from_id)->update(['step' => 'in_chat']);

            exit();
        }else{
            DB::table('matches')->insert(['player1' => $telegram->from_id , 'player1_hash' => $helper->Hash() , 'type' => 'anonymous']);
            exit();
        }
    }
    exit();
}
elseif (mb_stripos($text, 'مسابقه', 0, 'UTF-8') !== false || mb_stripos($text, 'تورنومنت', 0, 'UTF-8') !== false || $telegram->matchesKeyword($text, $locale->trans('keyboard.home.tournament'))) {
    $telegram->sendMessage('cooming soon ...')->send();
    exit();
}
elseif (mb_stripos($text, 'دوستان', 0, 'UTF-8') !== false || $telegram->matchesKeyword($text, $locale->trans('keyboard.home.friend'))) {
    $telegram->sendMessage('%message.choose%')->keyboard(array_merge($keyboard->get('main.friend') , $keyboard->get('main.decline')))->send();
    exit();
}
elseif ($telegram->matchesKeyword($text, $locale->trans('keyboard.home.account')) || 
       mb_stripos($text, 'حساب کاربری', 0, 'UTF-8') !== false){

    $userData = $user->userData();

    $userExtra = $user->userExtra($userData['id']);

    $userRank = $user->userRank($userData['id']);

    $friends = json_decode($userExtra['friends'], true);
    if (is_array($friends)) {
        $friends_count = count($friends);
    } else {
        $friends_count = 0;
    }


    $win_rate = strval(number_format($userExtra['win_rate'],2)) . "%";
    $telegram->sendMessage($locale->trans('message.account',[
        'username'=>"/".$userData['username'],
        'telegram_id'=>$userData['telegram_id'],
        'matches_count'=>$userExtra['matches'],
        'win_rate'=>$win_rate,
        'win_rate_rank'=>($userRank['winRate_rank']),
        'matches_rank'=>$userRank['match_rank'],
        'cups'=>$userExtra['cups'],
        'friends_count'=> $friends_count,
        'joined_at'=>$userData['created_at'],
        'doz_coin' => $userExtra['doz_coin']
    ]))->send();
    exit();
}
elseif ($telegram->matchesKeyword($text, $locale->trans('keyboard.home.leaderboard')) || 
       mb_stripos($text, 'نفرات برتر', 0, 'UTF-8') !== false){
    $telegram->sendMessage('%message.select_category%')->keyboard('main.leaderboard')->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.support')){
    $telegram->sendMessage('%message.support_clicked%')->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.info')){
    $telegram->sendMessage('%message.info%')->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.add_money')){
    $telegram->sendMessage('%message.select_way_to_pay%')->keyboard('main.add_money')->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.referral')) {
    $telegram->sendMessage($locale->trans('message.send_link_to_friend') . PHP_EOL . PHP_EOL . "https://t.me/DozGame_Robot?start=re_".$user->userData()['username'])->inline_keyboard([
        [[
            'text' => 'زیر مجموعه گیری ',
            'switch_inline_query_chosen_chat' => [
                'query' => 'referral' ,
                'allow_user_chats' => true,
                'allow_bot_chats' => false,
                'allow_group_chats' => true,
                'allow_channel_chats' => false
            ]
        ]
        ]
    ])->send();
    sleep(0.8);
    $telegram->sendMessage("%message.send_message_to_friend%")->replay("+1")->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.friend_list')){
    $step->clear();
    $user_id = $user->userData()['id'];
    $friends = json_decode(DB::table('users_extra')->where('user_id', $user_id)->select('friends')->first()['friends'] , 1);
    if ($friends == NULL ){
        $telegram->sendMessage('%message.no_friend_exist%')->send();
        exit();
    }
    $friend_list_keyboard = [];
    $friend_list_keyboard[] = [['text' => 'حذف' , 'callback_data' => 'none'] , ['text' => 'بازی' , 'callback_data' => 'none'],['text' => 'وضعیت' , 'callback_data' => 'none'], ['text' => 'نام کاربری' , 'callback_data' => 'none']];
    $now = new DateTime();
    foreach ($friends as $friend){
        $friend_data = DB::table('users')->where('id', $friend)->select('username,telegram_id,updated_at')->first();
        $friend_username = $friend_data['username'];

        $matchResult = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND (status = 'playing' or status = 'pending');" , [$friend_data['telegram_id'] , $friend_data['telegram_id']]);
        $match = isset($matchResult[0]) ? $matchResult[0] : null;
        if ($match) {
            $status = '🕹';
            $status_callback = 'playing';
        }else{
            $updatedAt = new DateTime($friend_data['updated_at']);
            $diffInSeconds = $now->getTimestamp() - $updatedAt->getTimestamp();
            if ($diffInSeconds > 120) {
                $status = '🔴';
                $status_callback = 'offline_'.$friend;
            } else {
                $status = '🟢';
                $status_callback = 'none';
            }
        }



        $friend_list_keyboard[] = [['text' => '❌' , 'callback_data' => 'remove_'.$friend] , ['text' => '🎮' , 'callback_data' => 'play_'.$friend],['text' => $status , 'callback_data' => $status_callback], ['text' => $friend_username , 'callback_data' => 'none']];

    }
    $telegram->sendMessage('%message.friend_list_text%')->inline_keyboard($friend_list_keyboard)->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.friend_add')){
    $step->set('add_friend');
    $telegram->sendMessage("%message.friend_request_before%")->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.friend_requests')){

    $user_id = $user->userData()['id'];

    $friends_req = DB::table('friend_requests')->where(['to_id' => $user_id , 'status' => 'pending'])->select('from_id')->get();
    if (!$friends_req)
    {
        $telegram->sendMessage('%message.no_request_exist%')->send();
        exit();
    }

    $friend_req_list_keyboard = [];
    foreach ($friends_req as $friend_req){
        $friend_req_username = DB::table('users')->where('id', $friend_req['from_id'])->select('username')->first()['username'];
        $friend_req_list_keyboard[] = [['text' => '❌' , 'callback_data' => 'friend_r_'.$friend_req['from_id']] , ['text' => '✅ ' , 'callback_data' => 'friend_a_'.$friend_req['from_id']], ['text' => $friend_req_username , 'callback_data' => 'none']];
    }
    $telegram->sendMessage('%message.friend_request_list%')->inline_keyboard($friend_req_list_keyboard)->send();


    $step->clear();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.leaderboard_winrate')){

    $dates = DB::rawQuery('SELECT ue.user_id,ue.win_rate, u.username 
        FROM users_extra ue
        JOIN users u ON ue.user_id = u.id
        ORDER BY ue.win_rate DESC
        LIMIT 10;
        ');

    $send_text = $locale->trans('message.winRate_leaderboard') . PHP_EOL . PHP_EOL;
    foreach ($dates as $key => $date){
        $send_text .= ($key+1) . " ) " . $date['username'] . " -> " . $date['win_rate']."%" . PHP_EOL;
    }
    $telegram->sendMessage($send_text)->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.leaderboard_score')){

    $dates = DB::rawQuery('SELECT ue.user_id,ue.cups, u.username 
        FROM users_extra ue
        JOIN users u ON ue.user_id = u.id
        ORDER BY ue.cups DESC
        LIMIT 10;
        ');

    $send_text = $locale->trans('message.jam_leaderboard') . PHP_EOL . PHP_EOL;
    foreach ($dates as $key => $date){
        $send_text .= ($key+1) . " ) " . $date['username'] . " -> " . $date['cups'] . PHP_EOL;
    }
    $telegram->sendMessage($send_text)->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.leaderboard_experience')){

    $dates = DB::rawQuery('SELECT ue.user_id,ue.matches, u.username 
        FROM users_extra ue
        JOIN users u ON ue.user_id = u.id
        ORDER BY ue.matches DESC
        LIMIT 10;
        ');

    $send_text = $locale->trans('message.gameCount_leaderboard') . PHP_EOL . PHP_EOL;
    foreach ($dates as $key => $date){
        $send_text .= ($key+1) . " ) " . $date['username'] . " -> " . $date['matches'] . PHP_EOL;
    }
    $telegram->sendMessage($send_text)->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.leaderboard_winer')){
    $telegram->sendMessage("cooming soon ...")->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.reject')){
    $matchResult = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND (status = 'playing');" , [$telegram->from_id , $telegram->from_id]);
    $match = isset($matchResult[0]) ? $matchResult[0] : null;
    if ($match['player1'] == $telegram->from_id)
        $friend_telegram_id = $match['player2'];
    elseif ($match['player2'] == $telegram->from_id)
        $friend_telegram_id = $match['player1'];

    $telegram->sendMessage('%message.u_reject_chat%')->keyboard('main.home')->send();
    $telegram->sendMessage('%message.he_reject_chat%')->keyboard('main.home')->send($friend_telegram_id);
    $step->clear();
    $step->clear($friend_telegram_id);
    exit();
}
elseif ($text == $locale->trans('keyboard.home.inventory')){
    $userData = $user->userData();
    $doz_coin = $user->userExtra($userData['id'])['doz_coin'];
    $balance = $doz_coin * 1000;
    $telegram->sendMessage("%message.inventory_text[doz_coin:$doz_coin,balance:$balance]%")->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.home.withdrawal')){
    $userData = $user->userData();
    $doz_coin = $user->userExtra($userData['id'])['doz_coin'];

    if ($doz_coin >= 50){
        $telegram->sendMessage("%message.withdrawal_ok%")->send();

    }else{
        $telegram->sendMessage("%message.error_min_withdrawal[doz_coin:$doz_coin]%")->send();

    }
    exit();
}


if ($step->get() == 'add_friend' or str_starts_with($text , 'httpreqfriend_')){

    $text = str_replace('httpreqfriend_' , '' , $text);
    $friend = DB::table('users')->where('username', $text)->select('id,username,telegram_id')->first();
    if (!$friend){
        $telegram->sendMessage('%message.user_not_found%')->send();
        exit();
    }

    $friend_from_id = $user->userData()['id'];
    $friend_to_id = $friend['id'];
    $myUsername = $user->userData()['username'];
    $friendUsername = $friend['username'];

    if ($friend_from_id == $friend_to_id){
        $telegram->sendMessage("%message.friend_request_self_error%")->send();
        exit();
    }

    $reverseFriendRequest = DB::table('friend_requests')->where(['from_id' => $friend_to_id , 'to_id' => $friend_from_id , 'status' => 'pending'])->select('*')->first();

    if ($reverseFriendRequest){
        DB::table('friend_requests')->where(['id' => $reverseFriendRequest['id']])->update(['status' => 'accepted']);

        DB::rawQuery("UPDATE users_extra 
        SET friends = JSON_MERGE(COALESCE(friends, '[]'), JSON_ARRAY($friend_from_id)) 
        WHERE user_id = " . $friend_to_id);

        DB::rawQuery("UPDATE users_extra 
        SET friends = JSON_MERGE(COALESCE(friends, '[]'), JSON_ARRAY($friend_to_id)) 
        WHERE user_id = " . $friend_from_id);

        $telegram->sendMessage("%message.friend_request_accepted[username:$myUsername]%")->send($friend['telegram_id']);
        $telegram->sendMessage("%message.friend_request_accepted_fast[username:$friendUsername]%")->send();
        $step->clear();
        exit();
    }


    $already_friends = json_decode(DB::table('users_extra')->where('user_id', $friend_from_id)->select('friends')->first()['friends'] , 1);
    if ($already_friends){
        if(in_array($friend_to_id, $already_friends)){
            $telegram->sendMessage("%message.friend_exist[username:$friendUsername]%")->send();
            exit();
        }
    }

    $is_friend_request_exist = DB::table('friend_requests')->where(['from_id' => $friend_from_id , 'to_id' => $friend_to_id])->select('*')->first();
    if ($is_friend_request_exist){
        $telegram->sendMessage('%message.friend_request_exist%')->send();
        exit();
    }

    DB::table('friend_requests')->insert(['from_id' => $friend_from_id , 'to_id' => $friend_to_id]);
    $is_friend_request_exist = DB::table('friend_requests')->where(['from_id' => $friend_from_id , 'to_id' => $friend_to_id])->select('*')->first();

    $telegram->sendMessage("%message.friend_request_sent[username:$friendUsername]%")->keyboard('main.home')->send();
    $telegram->sendMessage("%message.friend_request[username:$myUsername]%")->inline_keyboard([
        [['text' => '❌' , 'callback_data' => 'friend_r_'.$friend_from_id] , ['text' => '✅ ' , 'callback_data' => 'friend_a_'.$friend_from_id]]
    ])->send($friend['telegram_id']);
    $step->clear();
    exit();
}
elseif ($step->get() == 'in_chat') {
    $matchResult = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND (status = 'playing' or status = 'pending');" , [$telegram->from_id , $telegram->from_id]);
    $match = isset($matchResult[0]) ? $matchResult[0] : null;
    if ($match['player1'] == $telegram->from_id)
        $friend_telegram_id = $match['player2'];
    elseif ($match['player2'] == $telegram->from_id)
        $friend_telegram_id = $match['player1'];

    if ($step->get($friend_telegram_id) != 'in_chat'){
        $telegram->sendMessage('⚠️ کاربر مقابل چت کردن با شما را بسته است  .')->send();
        exit();
    }
    $telegram->sendMessage($text)->send($friend_telegram_id);
    exit();
}



