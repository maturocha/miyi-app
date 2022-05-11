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
                  ->select('orders.date', 'orders.total', 'orders.id')
                  ->orderBy('orders.date', 'DESC')
                  ->get()
                  ->take(10);
  }

  public function getProducts()
  {
      return $this->where('customers.id', '=', $this->id)
                  ->join('orders','orders.id_customer','=','customers.id')
                  ->join('order_details','orders.id','=','order_details.id_order')
                  ->join('products','order_details.id_product','=','products.id')
                  ->select('products.name', DB::raw('ROUND(SUM(order_details.price_final * ((100 - order_details.discount)/100) * ((100 - orders.discount)/100) ) , 2) as total'))
                  ->orderByRaw('total DESC')
                  ->groupBy('products.id')
                  ->take(20)->get();
  }

  

  public static function getRankPurchase($dates, $topList) {

    return self::join('orders','customers.id','=','orders.id_customer')
                ->join('order_details','orders.id','=','order_details.id_order')
                ->join('products','order_details.id_product','=','products.id')
                ->select('customers.name', DB::raw('ROUND(SUM(order_details.price_final * ((100 - order_details.discount)/100) * ((100 - orders.discount)/100) ) , 2) as total'))
                ->whereBetween('orders.date', [$dates[0], $dates[1]])
                ->orderByRaw('total DESC')
                ->groupBy('customers.id')
                ->take(20)->get();

  }


}
