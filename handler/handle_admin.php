<?php

use Application\Model\DB;
global $telegram;
global $locale;
global $user;
global $helper;
//$from_id = $telegram->get()->message->from->id;
//$first_name = $telegram->get()->message->from->first_name;


if (isset($update->message)) {
    $text = $telegram->get()->message->text;
    if ($text == "/panel") {
        $telegram->sendMessage('%admin.message.panel%')->keyboard('admin.panel')->send();
        exit();
    } elseif ($text == $locale->trans('keyboard.admin.statistics')) {
        $telegram->sendMessage('%admin.message.statistics%')->send();

        exit();
    }
}
elseif (isset($update->inline_query)) {

}
elseif (isset($update->callback_query)) {

}
