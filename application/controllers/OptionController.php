<?php

namespace application\controllers;

use Application\Model\DB;

class OptionController
{
    public $channels;
    public $forced_to_join;

    public function __construct(){
        $this->channels = json_decode(DB::table('options')->select('channels')->first()['channels'] , 1);
        $this->forced_to_join = json_decode(DB::table('options')->select('forced_to_join')->first()['forced_to_join'] , 1);
    }

}