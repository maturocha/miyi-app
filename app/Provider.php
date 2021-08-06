<?php

namespace App;

use App\Traits\Models\Impersonator;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
  use SoftDeletes;

  protected $table = 'providers';
  protected $primaryKey = 'id';
  protected $fillable = [
        'cuit', 'fullname','email', 'address', 'enterprise', 'cellphone', 'telephone'
  ];

  public function getAll()
  {
      return $this->name;
  }

  public function getRecordTitle()
  {
      return $this->fullname;
  }


}
