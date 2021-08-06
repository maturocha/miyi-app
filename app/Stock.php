<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Stock extends Model
{
  protected $table = 'stocks';
  protected $primaryKey = 'id';
  protected $fillable = [
      'date', 'id_user', 'type'
  ];

  public function getRecordTitle()
  {
      return 'Carga de stock del ' . Carbon::parse($this->date)->format('d/m/Y') ;
  }

  public static function getByID($id) {
    return self::where('stocks.id', '=', $id)
                ->join('users','stocks.id_user','=','users.id')
                ->select('stocks.id', 'stocks.date', 'stocks.type', 'users.name')
                ->first();

  }

  public static function getDetailsByID($id) {
    return self::where('stocks.id', '=', $id)
                ->join('stock_details','stock_details.id_stock','=','stocks.id')
                ->join('products','stock_details.id_product','=','products.id')
                ->select('products.name as product', 'stock_details.quantity','stock_details.price_purchase', 'stock_details.bulto_reference', 'stock_details.due_date' )
                ->get();

  }

  public function getproductsByID($id) {
    return self::where('stocks.id', '=', $id)
                ->join('stock_details','stock_details.id_stock','=','stocks.id')
                ->join('products','stock_details.id_product','=','products.id')
                ->distinct()
                ->pluck('products.name')->toArray();

  }
}
