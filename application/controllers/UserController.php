<?php
namespace application\controllers;
use Application\Model\DB;

class UserController extends HelperController
{
    private $telegram_id;
    public $is_ref=0;
    public function __construct($update , $ref_id=null)
    {
        $this->telegram_id = $this->getUserTelegramID($update);
        if ($this->telegram_id){
            if (!DB::table('users')->where('telegram_id', $this->telegram_id)->select('id')->get()) {

                if ($ref_id){
                    DB::table('users')->insert(['telegram_id' => $this->telegram_id , 'refere_id' => $ref_id]);
                }else{
                    DB::table('users')->insert(['telegram_id' => $this->telegram_id]);
                }
                $id = DB::table('users')->where('telegram_id', $this->telegram_id)->select('id')->first()['id'];
                DB::table('users_extra')->insert(['user_id' => $id]);

                if ($ref_id){
                    $this->is_ref = '1';
                }

            }
            else{
                if ($ref_id){
                    $this->is_ref = 0;
                }
            }
        }
        DB::rawQuery("UPDATE users 
SET updated_at = CURRENT_TIMESTAMP
WHERE telegram_id = $this->telegram_id;
");
    }

    public function userData()
    {
        return DB::table('users')->where(['telegram_id' => $this->telegram_id])->select('*')->first();
    }

    public function userExtra($userId)
    {
        return DB::table('users_extra')->where(['user_id' => $userId])->select('*')->first();
    }

    public function userMatchesRank($userId)
    {
         $x = DB::rawQuery('SELECT user_id, matches, user_rank FROM ( SELECT id, user_id, matches, RANK() OVER (ORDER BY matches DESC) AS user_rank FROM users_extra ) AS ranked_users WHERE user_id = ?;', [$userId]);
         if (!empty($x)) return $x[0];
         else return null;
    }

    public function userWinRateRank($userId)
    {
        $x = DB::rawQuery('SELECT user_id, winRate, user_rank FROM ( SELECT user_id, (wins / matches) * 100 AS winRate, RANK() OVER (ORDER BY (wins / matches) DESC) AS user_rank FROM users_extra WHERE matches > 0 ) AS ranked_users WHERE user_id = ?;', [$userId]);
        if (!empty($x)) return $x[0];
        else return null;
    }

    public function userRank($userId)
    {
        $x = DB::rawQuery('
        SELECT user_id, matches, winRate, match_rank, winRate_rank
        FROM (
            SELECT 
                user_id, 
                matches, 
                (wins / matches) * 100 AS winRate, 
                RANK() OVER (ORDER BY matches DESC) AS match_rank, 
                RANK() OVER (ORDER BY (wins / matches) DESC) AS winRate_rank
            FROM users_extra
            WHERE matches > 0
        ) AS ranked_users
        WHERE user_id = ?;
    ', [$userId]);

        if (!empty($x)) return $x[0];
        else return null;
    }

    public function isAdmin(): bool
    {
        $checkUser = DB::table('users')->where(['telegram_id' => $this->telegram_id])->select('*')->first();
        if ($checkUser){
            if ($checkUser['type'] == 'admin' or $checkUser['type'] == 'owner'){
                return true;
            } else return false;
        } else return false;
    }

}