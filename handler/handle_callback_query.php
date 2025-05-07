<?php

use Application\Model\DB;
$data = $telegram->get()->callback_query->data;
$id = $telegram->get()->callback_query->id;
$inline_id = $telegram->get()->callback_query->inline_message_id;





if ($data == 'i_join'){
    $telegram->deleteMessage();
    $telegram->sendMessage('%message.joined_successfully%')->keyboard('main.home')->send();
    $telegram->answerCallbackQuery('');
    exit();
}
elseif (str_starts_with($data,"friend")) {
    $exploded_data = explode("_",$data);
    $friend_request = DB::table('friend_requests')->where(['id' => $exploded_data[2]])->select('*')->first();
    if (isset($friend_request) and $friend_request['status']!= 'pending'){
        $telegram->sendMessage('%message.processed%')->send();
        $telegram->deleteMessage();
        exit();
    }
    if ($exploded_data[1] == "r") {
        $friend_from_id = intval($user->userData()['id']);
        $friend_to_id = intval($exploded_data[2]);
        $myUsername = $user->userData()['username'];
        $telegram->deleteMessage();
        DB::table('friend_requests')->where(['from_id' => $friend_to_id , 'to_id' => $friend_from_id])->update(['status' => 'rejected']);
        $friend = DB::table('users')->where('id',$exploded_data[2] )->select('id,username,telegram_id')->first();
        $telegram->sendMessage("%message.friend_request_rejected[username:$myUsername]%")->send($friend['telegram_id']);
        $telegram->sendMessage("%message.friend_request_rejected_fast[username:$myUsername]%")->send();
        $telegram->answerCallbackQuery('');

        exit();

    }
    elseif ($exploded_data[1] == "a") {
        $friend_from_id = intval($user->userData()['id']);
        $friend_to_id = intval($exploded_data[2]);
        $myUsername = $user->userData()['username'];
        $friend = DB::table('users')->where('id',$exploded_data[2] )->select('id,username,telegram_id')->first();

        $already_friends = json_decode(DB::table('users_extra')->where('user_id', $friend_from_id)->select('friends')->first()['friends'] , 1);
        if ($already_friends){
            if(in_array($friend_to_id, $already_friends)){
                $telegram->sendMessage("%message.friend_exist[username:" . $friend['username'] . "]%" )->send();
                $telegram->deleteMessage();
                DB::table('friend_requests')->where(['from_id' => $friend_to_id , 'to_id' => $friend_from_id])->update(['status' => 'accepted']);
                exit();
            }
        }

        $telegram->deleteMessage();

        $telegram->sendMessage("%message.friend_request_accepted[username:$myUsername]%")->send($friend['telegram_id']);
        $telegram->sendMessage("%message.friend_request_accepted_fast[username:".$friend['username']."]%")->send();

        DB::table('friend_requests')->where(['from_id' => $friend_to_id , 'to_id' => $friend_from_id])->update(['status' => 'accepted']);

        DB::rawQuery("UPDATE users_extra
        SET friends = JSON_MERGE(COALESCE(friends, '[]'), JSON_ARRAY($friend_to_id))
        WHERE user_id = " . $friend_from_id);


        DB::rawQuery("UPDATE users_extra
        SET friends = JSON_MERGE(COALESCE(friends, '[]'), JSON_ARRAY($friend_from_id))
        WHERE user_id = " . $friend_to_id);
        $telegram->answerCallbackQuery('');
        exit();
    }
    exit();
}
elseif (str_starts_with($data,"remove")) {
    $exploded_data = explode("_",$data);
    $my_id = $user->userData()['id'];

    $friends = json_decode($user->userExtra($user->userData()['id'])['friends'] , 1);

    if (($key = array_search($exploded_data[1], $friends)) !== false) {
        unset($friends[$key]);
    }
    $reversFriend = json_decode($user->userExtra($exploded_data[1])['friends'] , 1);
    if (($key = array_search($my_id, $reversFriend)) !== false) {
        unset($reversFriend[$key]);
    }

    $telegram->answerCallbackQuery('%message.friend_removed%');

    $telegram->deleteMessage();

    DB::table('users_extra')->where(['user_id' => $my_id])->update(['friends' => json_encode($friends)]);
    DB::table('users_extra')->where(['user_id' => $exploded_data[1]])->update(['friends' => json_encode($reversFriend)]);
    
    if ($friends == NULL ){
        $telegram->sendMessage('%message.no_friend_exist%')->send();
        exit();
    }
    $friend_list_keyboard = [];
    foreach ($friends as $friend){
        $friend_username = DB::table('users')->where('id', $friend)->select('username')->first()['username'];
        $friend_list_keyboard[] = [['text' => 'حذف ❌' , 'callback_data' => 'remove_'.$friend] , ['text' => 'بازی 🎮' , 'callback_data' => 'play_'.$friend], ['text' => $friend_username , 'callback_data' => 'none']];
    }
    $telegram->sendMessage('%message.friend_list_text%')->inline_keyboard($friend_list_keyboard)->send();

    exit();
}
elseif (str_starts_with($data , "p1")){
    $exploded_data = explode('-' , $data);
    if ($telegram->from_id == $exploded_data[1]){
        $telegram->answerCallbackQuery($locale->trans("message.wait_for_friend"));
        exit();
    }
    $telegram->editMessage($inline_id , $locale->trans('message.u_can_play_now'))->inline_keyboard([
        [["text" => "🎮  شروع بازی" , 'callback_data' => 'start-'.$exploded_data[1].'-'.$telegram->from_id]]
    ])->send();

    exit();
}
elseif (str_starts_with($data , "start")){
    $exploded_data = explode('-' , $data);
    if ($telegram->from_id != $exploded_data[1]){
        $telegram->answerCallbackQuery($locale->trans('message.who_play'));
        exit();
    }
    $game_id = DB::table('matches')->insert(['player1' => $telegram->from_id , 'player1_hash' => $helper->Hash() ,'player2' => $exploded_data[2] , 'player2_hash' => $helper->Hash() , 'type' => 'private']);
    $telegram->answerCallbackQuery(' ');
    $telegram->editMessage($inline_id , $locale->trans('message.u_can_start_now'))->inline_keyboard([
        [['text' => 'ورود به بازی', 'url' => 't.me/Game100_robot?start=sg-'.$game_id]]
    ])->send();
    exit();

}
elseif (str_starts_with($data , "play_")){
    $exploded_data = explode('_' , $data);
    $friend = DB::table('users')->where('id', $exploded_data[1])->select('*')->first();
    $telegram->answerCallbackQuery(' ');

    $oldMatch = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND (status = 'pending' OR status = 'playing') AND (type = 'in_bot' or type = 'anonymous' or type = 'in_bot');" , [$telegram->from_id , $telegram->from_id])[0];
    if ($oldMatch){
        $telegram->sendMessage("%message.u_have_old_match%")->send();
        exit();
    }

    $friendOldMatch = DB::rawQuery("SELECT * FROM matches WHERE (player1 = ? OR player2 = ?) AND (status = 'pending' OR status = 'playing') AND (type = 'in_bot' or type = 'anonymous' or type = 'in_bot');" , [$friend['telegram_id'] ,$friend['telegram_id'] ])[0];
    if ($friendOldMatch){
        $telegram->sendMessage("%message.ur_friend_have_old_match%")->send();
        exit();
    }

    $game_id = DB::table('matches')->insert(['player1' => $telegram->from_id , 'player1_hash' => $helper->Hash() , 'type' => 'in_bot']);

    $telegram->sendMessage('%message.game_req_send%')->send();
    $telegram->sendMessage($locale->trans('message.new_game_req') . $user->userData()['username'])->inline_keyboard([
        [['text' => '❌ رد ' , 'callback_data' => 'game_d_'.$game_id] , ['text' => '✅ قبول' , 'callback_data' => 'game_a_'.$game_id]]
    ])->send($friend['telegram_id']);


    exit();
}
elseif (str_starts_with($data , "game_")){
    $exploded_data = explode('_' , $data);
    $match = DB::table('matches')->where('id', $exploded_data[2])->select('*')->first();
    if (!$match or $match['status'] != 'pending'){
        $telegram->deleteMessage();
        $telegram->sendMessage('⚠️ بازی شما شروع و یا منقضی شده است .')->send();
        exit();
    }

    if ($exploded_data[1] == 'a'){
        $friend = DB::table('users')->where('telegram_id', $match['player1'])->select('*')->first();
        $player2_hash = $helper->Hash();
        $telegram->deleteMessage();
        DB::table('matches')->where('id', $exploded_data[2])->update(['player2' => $telegram->from_id , 'player2_hash' => $player2_hash,'status' => 'playing']);
        $telegram->sendMessage($locale->trans('message.game_with_user') . $user->userData()['username'] . $locale->trans('message.started'))->inline_keyboard([
            [['text' => 'ورود به بازی', 'web_app' => ['url' => 'https://robot.bemola.site/XO/pages/match.php?id='.$match['id']."&hash=".$match['player1_hash']."&player=1"]]]
        ])->send($match['player1']);
        $telegram->sendMessage('%message.u_can_chat_now%')->keyboard('main.in_chat')->send($match['player1']);
        DB::table('users')->where('telegram_id' , $match['player1'])->update(['step' => 'in_chat']);


        $telegram->sendMessage($locale->trans(' message.game_with_user') . $friend['username'] . $locale->trans('message.started'))->inline_keyboard([
            [['text' => 'ورود به بازی', 'web_app' => ['url' => 'https://robot.bemola.site/XO/pages/match.php?id='.$match['id']."&hash=".$player2_hash."&player=2"]]]
        ])->send();
        $telegram->sendMessage('%message.u_can_chat_now%')->keyboard('main.in_chat')->send($telegram->from_id);
        DB::table('users')->where('telegram_id' , $telegram->from_id)->update(['step' => 'in_chat']);
        exit();
    }
    elseif ($exploded_data[1] == 'd'){
        $friend = DB::table('users')->where('telegram_id', $match['player1'])->select('*')->first();
        $telegram->deleteMessage();
        $telegram->sendMessage($locale->trans('message.u_reject_game') .$friend['username'] .  $locale->trans('message.u_reject'))->send();
        $telegram->sendMessage($locale->trans('message.he_reject_ur_game') . $user->userData()['username'] . $locale->trans('message.he_reject'))->send($match['player1']);

        DB::table('matches')->where('id', $exploded_data[2])->update(['status' => 'expired']);
        exit();
    }

}
elseif ($data == 'playing'){
    $telegram->answerCallbackQuery('%message.user_is_playing%');
    exit();
}
elseif (str_starts_with($data , 'offline_')){
    $exploded_data = explode('_' , $data);
    $friend_data = DB::table('users')->where('id', $exploded_data[1])->select('updated_at')->first();

    $now = new DateTime();
    $updatedAt = new DateTime($friend_data['updated_at']);
    $diffInSeconds = $now->getTimestamp() - $updatedAt->getTimestamp();

    if ($diffInSeconds < 60) {
        $activity = "به تازگی آفلاین شده";
    } elseif ($diffInSeconds < 3600) {
        $minutes = floor($diffInSeconds / 60);
        $activity = "$minutes  دقیقه " . " پیش";
    } elseif ($diffInSeconds < 86400) {
        $hours = floor($diffInSeconds / 3600);
        $activity = "$hours ساعت " . " پیش";
    } elseif ($diffInSeconds < 604800) {
        $days = floor($diffInSeconds / 86400);
        $activity = "$days روز " . " پیش";
    } else {
        $weeks = floor($diffInSeconds / 604800);
        $activity = "$weeks هفته " . " پیش";
    }

    $telegram->answerCallbackQuery('آخرین فعالیت ' . $activity);

}