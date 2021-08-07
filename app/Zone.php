<?php

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Authenticatable
{
  use SoftDeletes;

  protected $table = 'zones';
  protected $primaryKey = 'id';
  protected $fillable = [
      'name' , 'code'
  ];

  public function getRecordTitle()
  {
      return $this->name;
  }

  public function getAll()
  {
      return $this->name;
  }
}
