<?php
namespace application\controllers;

use Application\Model\DB;

class StepController extends HelperController
{
    private static $telegram_id;

    public function __construct($update)
    {
        self::$telegram_id = $this->getUserTelegramID($update);
    }

    public static function set($data , $telegram_id = null)
    {
        if (!isset($telegram_id))
            $telegram_id = self::$telegram_id;
        DB::table('users')->where('telegram_id',$telegram_id)->update(['step' => $data]);
        return true;
    }

    public static function clear($telegram_id = null)
    {
        if (!isset($telegram_id))
            $telegram_id = self::$telegram_id;
        DB::table('users')->where('telegram_id',$telegram_id)->update(['step'=>null]);
        return true;
    }

    public static function get($telegram_id = null)
    {
        if (!isset($telegram_id))
            $telegram_id = self::$telegram_id;
        return DB::table('users')->where('telegram_id',$telegram_id)->select('step')->first()['step'];
    }
}