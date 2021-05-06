<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;


/**
 * Class Notification
 *
 * @package App
 * @property string $name
 * @property string $surname
*/
class Notification extends Model
{

    protected $fillable = ['id_user', 'message', 'read_at'];

    public static function byUser( $user_id ) {

        return self::where('id_user', '=', $user_id)->get();

      }

}