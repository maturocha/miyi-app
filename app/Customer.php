<?php

namespace App;

use App\Traits\Models\Impersonator;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use DB;

use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
  use SoftDeletes;

  protected $table = 'customers';
  protected $primaryKey = 'id';
  protected $fillable = [
        'cuit', 'fullname', 'name', 'email', 'address', 'neighborhood', 'id_neighborhood', 'lat', 'long', 'cellphone', 'telephone', 'type'
  ];


  public function getRecordTitle()
  {
      return $this->name;
  }

  public function getAll()
  {
      return $this->name;
  }

  public static function getByID($id)
  {
    return self::join('neighborhoods','customers.id_neighborhood','=','neighborhoods.id')
                ->where('customers.id', '=', $id)
                ->select('customers.id', 'customers.fullname', 'customers.name', 'customers.address',
                'neighborhoods.name as neighborhood', 'customers.email', 'customers.cellphone', 'customers.type',
                'customers.telephone' )
                ->first();
  }



  public function getOrders()
  {
      return $this->where('customers.id', '=', $this->id)
                  ->join('orders','orders.id_customer','=','customers.id')
                  ->select('orders.id', 'orders.date', 'orders.total')
                  ->orderBy('orders.date', 'DESC')
                  ->get()
                  ->take(10);
  }


}
