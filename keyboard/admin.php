<?php
use application\controllers\LocaleController;
$locale = new LocaleController();

return [
    'panel' => [
        [['text'=> $locale->trans('keyboard.admin.statistics')]],
    ] ,
];