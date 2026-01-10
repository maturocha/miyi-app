<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use DB;

class Stock extends Model
{
  protected $table = 'stocks';
  protected $primaryKey = 'id';
  protected $fillable = [
      'date', 'id_user', 'type', 'notes'
  ];

  public function getRecordTitle()
  {
      return 'Carga de stock del ' . Carbon::parse($this->date)->format('d/m/Y') ;
  }

  public static function getAll() {
    return self::join('users','stocks.id_user','=','users.id')
                ->join('stock_details','stock_details.id_stock','=','stocks.id')
                ->join('products','stock_details.id_product','=','products.id')
                ->select('stocks.id', 'stocks.date', 'stocks.type', 'stocks.notes', 'users.name',
                DB::raw('group_concat(products.name separator ", ") AS details'),)
                ->groupBy('stocks.id');

  }

  public static function getByID($id) {
    return self::where('stocks.id', '=', $id)
                ->join('users','stocks.id_user','=','users.id')
                ->select('stocks.id', 'stocks.date', 'stocks.type', 'stocks.notes', 'users.name')
                ->first();

  }

  public static function getDetailsByID($id) {
    return self::where('stocks.id', '=', $id)
                ->join('stock_details','stock_details.id_stock','=','stocks.id')
                ->join('products','stock_details.id_product','=','products.id')
                ->join('providers','stock_details.id_provider','=','providers.id')
                ->select('products.name as product', 'stock_details.quantity','stock_details.price_purchase', 'stock_details.bulto_reference', 'stock_details.due_date', 'providers.fullname as provider')
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
