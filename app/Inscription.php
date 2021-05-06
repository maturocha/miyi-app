<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;


/**
 * Class Meetup
 *
 * @package App
 * @property string $name
 * @property string $surname
*/
class Inscription extends Model
{

    protected $fillable = ['id_user', 'id_meetup', 'date', 'check_in'];

    public static function getQuantityByMeetup() {
        return self::select('meetups.id', DB::raw('count(*) as quantity'))
                    ->join('meetups','inscriptions.id_meetup','=','meetups.id')
                    ->groupBy('meetups.id')
                    ->get()
                    ;
      }

}