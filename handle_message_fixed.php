<?php

use Application\Model\DB;
use application\controllers\UserController as User;
use application\controllers\StepController as Step;

global $telegram, $locale, $user, $helper, $step, $keyboard, $option;

// اگر $user یا $step تعریف نشده یا null است، آنها را مجدداً ایجاد کن
if (!isset($user) || $user === null) {
    $user = new User($telegram->get());
}

if (!isset($step) || $step === null) {
    $step = new Step($telegram->get());
}

$text = $telegram->get()->message->text;

// حذف کدهای دیباگ برای افزایش سرعت
$button_text = $locale->trans('keyboard.home.play_with_unknown');
$from_id = $telegram->get()->message->from->id;
$first_name = $telegram->get()->message->from->first_name;

//$telegram->sendMessage($telegram->get())->send();


if ($text == '/start'){
    $telegram->sendMessage("%message.start[firstname:$first_name]%")->keyboard('main.home')->replay()->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.main.decline')){
    $telegram->sendMessage("%message.start[firstname:$first_name]%")->keyboard('main.home')->replay()->send();
    exit();
}

if (str_starts_with($text , '/start')){
    $exploded_text = explode(' ', $text);
    if (isset($exploded_text[1]) && str_starts_with($exploded_text[1] , 'sg')){
        $final_exploded = explode('-' , $exploded_text[1]);
        $find_game = DB::table('matches')->where('id',  $final_exploded[1])->select('*')->first();
        if (!$find_game){
            exit();
        }
        if ($telegram->from_id != $find_game['player1'] && $telegram->from_id != $find_game['player2']){
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
// حذف پیام‌های دیباگ
$translatedText = trim($locale->trans('keyboard.home.play_with_unknown'));
$receivedText = trim($text);

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

    $oldMatch = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND (status = 'pending' OR status = 'playing');" , [$telegram->from_id , $telegram->from_id])[0];
    if ($oldMatch) {
        $telegram->sendMessage("%message.u_have_old_match%")->send();
        exit();
    }else{
        $telegram->sendMessage("%message.finding_player%")->send();
        $newMatch = DB::rawQuery("SELECT * FROM matches WHERE player2 IS NULL AND status = 'pending' AND type = 'anonymous' ORDER BY id ASC LIMIT 1;");
        if (isset($newMatch[0])){
            $newMatch = $newMatch[0];
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
    if (!$userData) {
        $telegram->sendMessage("⚠️ خطا در دریافت اطلاعات کاربری")->send();
        exit();
    }

    $userExtra = $user->userExtra($userData['id']);
    if (!$userExtra) {
        $telegram->sendMessage("⚠️ خطا در دریافت اطلاعات اضافی کاربر")->send();
        exit();
    }

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
    $userData = $user->userData();
    if (!$userData || !isset($userData['username'])) {
        $telegram->sendMessage("⚠️ خطا: لطفاً اول نام کاربری خود را تنظیم کنید")->send();
        exit();
    }
    
    $telegram->sendMessage($locale->trans('message.send_link_to_friend') . PHP_EOL . PHP_EOL . "https://t.me/DozGame_Robot?start=re_".$userData['username'])->inline_keyboard([
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
    
    $userData = $user->userData();
    if (!$userData) {
        $telegram->sendMessage("⚠️ خطا در دریافت اطلاعات کاربری")->send();
        exit();
    }
    
    $user_id = $userData['id'];
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

        $match_result = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND (status = 'playing' or status = 'pending');" , [$friend_data['telegram_id'] , $friend_data['telegram_id']]);
        $match = isset($match_result[0]) ? $match_result[0] : null;
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

    $userData = $user->userData();
    if (!$userData) {
        $telegram->sendMessage("⚠️ خطا در دریافت اطلاعات کاربری")->send();
        exit();
    }
    
    $user_id = $userData['id'];

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
    $match = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND (status = 'playing');" , [$telegram->from_id , $telegram->from_id])[0];
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
    $telegram->sendMessage("cooming soon ...")->send();
    exit();
}
elseif ($text == $locale->trans('keyboard.friend.play')){

    $step->set('play_with_friend_username');

    $telegram->sendMessage("%message.send_friend_username%")->keyboard('main.decline')->send();

    exit();
}
elseif ($step && $step->get() == 'play_with_friend_username'){

    $friend_to = DB::table('users')->where('username' , $text)->select('id,telegram_id')->first();
    if (!$friend_to){
        $telegram->sendMessage("%message.username_not_found%")->send();
        exit();
    }

    $from_user = $user->userData();
    $from_user_id = $from_user['id'];

    $is_friend = DB::rawQuery("SELECT * FROM users_extra WHERE user_id = ? AND friends LIKE ?;", [$from_user_id, '%"'.$friend_to['id'].'"%'])[0];
    if (!$is_friend){
        $telegram->sendMessage("%message.username_not_friend%")->send();
        exit();
    }

    $oldMatch = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ? OR player1 = ? OR player2 = ?) AND (status = 'pending' OR status = 'playing');" , [$telegram->from_id ,$telegram->from_id , $friend_to['telegram_id'] , $friend_to['telegram_id']])[0];
    if ($oldMatch) {
        $telegram->sendMessage("%message.u_have_old_match%")->send();
        $step->clear();
        exit();
    }

    $telegram->sendMessage('%message.friend_req_send_to_play%')->keyboard('main.home')->send();
    $res = DB::table('matches')->insert(['player1' => $telegram->from_id, 'player1_hash' => $helper->Hash(), 'type' => 'friendly_request', 'player2' => $friend_to['telegram_id']]);
    $match_id = DB::table('matches')->where(['player1' => $telegram->from_id, 'player2' =>$friend_to['telegram_id'], 'status' => 'pending', 'type' => 'friendly_request'])->select('id,player1_hash')->orderBy('id' , 'DESC')->limit(1)->first();

    $username = $user->userData()['username'];
    $telegram->sendMessage($locale->trans('message.play_request' , ['username' => $username]))->inline_keyboard([
        [
            ['text' => 'بله', 'callback_data' => 'play_friend_req_yes_'.$match_id['id']] , 
            ['text' => 'خیر', 'callback_data' => 'play_friend_req_no_'.$match_id['id']]
        ]
    ])->send($friend_to['telegram_id']);

    $step->clear();
    exit();
}
elseif($text == $locale->trans('keyboard.friend.friend_request')){
    $step->set('add_friend');
    $telegram->sendMessage("%message.friend_request_before%")->send();
    exit();
}
elseif ($step && $step->get() == 'add_friend'){

    $friend_to = DB::table('users')->where('username' , $text)->select('id,telegram_id')->first();
    if (!$friend_to){
        $telegram->sendMessage("%message.username_not_found%")->send();
        exit();
    }
    
    $userData = $user->userData();
    if (!$userData) {
        $telegram->sendMessage("⚠️ خطا در دریافت اطلاعات کاربری")->send();
        exit();
    }

    $friend_from_id = $userData['id'];
    $friend_to_id = $friend_to['id'];
    if ($friend_from_id == $friend_to_id) {
        $telegram->sendMessage("%message.friend_to_your_self%")->send();
        exit();
    }

    $is_in_list = DB::rawQuery("SELECT * FROM users_extra WHERE user_id = ?;", [$friend_from_id])[0]['friends'];
    if ($is_in_list){
        $is_in_list = json_decode($is_in_list , 1);
        if (in_array($friend_to_id,$is_in_list)){
            $telegram->sendMessage("%message.friend_already_exist%")->send();
            exit();
        }
    }

    $is_requested = DB::table('friend_requests')->where(['from_id' => $friend_from_id, 'to_id' => $friend_to_id , 'status' => 'pending'])->select('*')->first();
    if ($is_requested){
        $telegram->sendMessage("%message.friend_already_requested%")->send();
        exit();
    }

    DB::table('friend_requests')->insert(['from_id' => $friend_from_id , 'to_id' => $friend_to_id]);

    $telegram->sendMessage("%message.friend_request_sent%")->send();

    $username = $user->userData()['username'];
    $telegram->sendMessage(str_replace('{username}' , $username , $locale->trans('message.friend_request_notification')))->inline_keyboard([
        [
            ['text' => 'بله', 'callback_data' => 'friend_req_yes_'.$friend_from_id] , 
            ['text' => 'خیر', 'callback_data' => 'friend_req_no_'.$friend_from_id]
        ]
    ])->send($friend_to['telegram_id']);

    $step->clear();
    exit();

}

elseif ($step && $step->get() == 'in_chat'){
    $match_result = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND status = 'playing';" , [$telegram->from_id , $telegram->from_id]);
    $match = isset($match_result[0]) ? $match_result[0] : null;
    if (!$match) {
        $telegram->sendMessage('%message.match_not_found%')->keyboard('main.home')->send();
        $step->clear();
        exit();
    }
    if (!$match){
        $telegram->sendMessage('%message.match_not_found%')->keyboard('main.home')->send();
        $step->clear();
        exit();
    }
    if ($match['player1'] == $telegram->from_id){
        $friend_telegram_id = $match['player2'];
        $username = $user->userData()['username'];
        $telegram->sendMessage(str_replace('{username}' , $username , $locale->trans('message.from_user_to_other')))->send($friend_telegram_id);
        $telegram->sendMessage(str_replace('{text}' , $text ,$locale->trans('message.text_chat')))->send($friend_telegram_id);

        $telegram->sendMessage($locale->trans('message.message_sent'))->send();
    }
    elseif ($match['player2'] == $telegram->from_id){
        $friend_telegram_id = $match['player1'];

        $username = $user->userData()['username'];
        $telegram->sendMessage(str_replace('{username}' , $username , $locale->trans('message.from_user_to_other')))->send($friend_telegram_id);
        $telegram->sendMessage(str_replace('{text}' , $text ,$locale->trans('message.text_chat')))->send($friend_telegram_id);

        $telegram->sendMessage($locale->trans('message.message_sent'))->send();
    }else{
        $telegram->sendMessage('%message.match_not_found%')->keyboard('main.home')->send();
        $step->clear();
        exit();
    }
    exit();
}

// اگر هیچ پیامی مطابقت نکرد، پیام پیش‌فرض را می‌فرستیم
$telegram->sendMessage("%message.error.default%")->keyboard('main.home')->send();