<?php
use application\controllers\LocaleController;
$locale = new LocaleController();

return [
    'home' => [
//        [['text'=> $locale->trans('keyboard.home.play_in_gp_or_pv')],['text'=> $locale->trans('keyboard.home.play_with_unknown')]],
        [['text'=> $locale->trans('keyboard.home.play_with_unknown')]],
        [['text'=>  $locale->trans('keyboard.home.tournament')]],
        [['text'=> $locale->trans('keyboard.home.friend')],['text'=>$locale->trans('keyboard.home.account')],['text'=> $locale->trans('keyboard.home.leaderboard')]],
        [['text' => $locale->trans('keyboard.home.add_money')]],
        [['text'=> $locale->trans('keyboard.home.support')],['text'=> $locale->trans('keyboard.home.info')]]
    ] ,
    'decline' => [
        [['text'=> $locale->trans('keyboard.main.decline')]],
    ],
    'friend' => [
        [['text' => $locale->trans('keyboard.home.friend_add')] , ['text' => $locale->trans('keyboard.home.friend_list')]],
        [['text' => $locale->trans('keyboard.home.friend_requests')]]
    ],
    'leaderboard' => [
        [['text' => $locale->trans('keyboard.home.leaderboard_winrate')] , ['text' => $locale->trans('keyboard.home.leaderboard_score')]],
        [['text' => $locale->trans('keyboard.home.leaderboard_experience')] , ['text' => $locale->trans('keyboard.home.leaderboard_winer')]],
        [['text' => $locale->trans('keyboard.main.decline')]],

    ] ,
    'add_money' => [
        [['text' => $locale->trans('keyboard.home.referral')]],
        [['text' => $locale->trans('keyboard.home.withdrawal')] , ['text' => $locale->trans('keyboard.home.inventory')]] ,
        [['text' => $locale->trans('keyboard.main.decline')]],
    ] ,
    'in_chat' => [
        [['text' => $locale->trans('keyboard.home.reject')]],
    ],
    'send_to_pv' => [
        [[
            'text' => $locale->trans('keyboard.home.send_game') ,
            'switch_inline_query_chosen_chat' => [
                'allow_user_chats' => true,
                'allow_bot_chats' => false,
                'allow_group_chats' => true,
                'allow_channel_chats' => false
            ]
        ]
        ]
    ]
];