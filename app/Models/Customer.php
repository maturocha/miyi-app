<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
  use SoftDeletes;

  protected $table = 'customers';
  protected $primaryKey = 'id';
  protected $fillable = [
        'cuit', 'fullname', 'name', 'email', 'address', 'time_visit', 'id_neighborhood', 'lat', 'long', 'cellphone', 'telephone', 'type', 'current_balance'
  ];

  protected $casts = [
        'current_balance' => 'decimal:2',
  ];

  public function neighborhood()
  {
    return $this->belongsTo(Neighborhood::class, 'id_neighborhood', 'id');
  }

  public function orders()
  {
    return $this->hasMany(Order::class, 'id_customer', 'id');
  }

  public function accountEntries()
  {
    return $this->hasMany(AccountEntry::class, 'customer_id', 'id');
  }


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
                ->select('customers.*', 'neighborhoods.name as neighborhood')
                ->first();
  }

  public function getProductRanking(int $top = 20)
  {
      return $this->where('customers.id', '=', $this->id)
                  ->join('orders','orders.id_customer','=','customers.id')
                  ->join('order_details','orders.id','=','order_details.id_order')
                  ->join('products','order_details.id_product','=','products.id')
                  ->select('products.name', DB::raw('ROUND(SUM(order_details.price_final * ((100 - order_details.discount)/100) * ((100 - orders.discount)/100) ) , 2) as total'))
                  ->orderByRaw('total DESC')
                  ->groupBy('products.id')
                  ->take($top)->get();
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
