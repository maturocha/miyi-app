<?php
namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;


/**
 * Class Meetup
 *
 * @package App
 * @property string $name
 * @property string $surname
*/
class Meetup extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'id_owner', 'date', 'beer_boxes', 'temperature'];

    public static function listWithInscriptors() {
        return self::select('meetups.id', 'meetups.name', 'meetups.description', 
                            'meetups.id_owner', 'meetups.date', 
                            'meetups.beer_boxes', 'meetups.temperature',
                            DB::raw('(count(*) - 1) as inscriptions'))
                    ->leftjoin('inscriptions','inscriptions.id_meetup','=','meetups.id')
                    ->groupBy('meetups.id');
      }

}