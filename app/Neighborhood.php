<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Neighborhood extends Authenticatable
{
  use SoftDeletes;

  protected $table = 'neighborhoods';
  protected $primaryKey = 'id';
  protected $fillable = [
      'name' , 'id_zone'
  ];

  public function zone(): BelongsTo
  {
    return $this->belongsTo(Zone::class, 'id_zone', 'id');
  }

  public function getRecordTitle()
  {
      return $this->name;
  }

  public static function getAll() {
    return self::join('zones','neighborhoods.id_zone','=','zones.id')
    ->select('id.neighborhoods', 'neighborhoods.name', 'zones.name as zone')
    ->get();
  }

}
