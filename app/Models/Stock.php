<?php

namespace App\Models;

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

  /**
   * Movimientos para el listado (sin `details`): solo los que tienen al menos
   * un detalle, igual que el INNER JOIN anterior (el join a products no filtra:
   * FK stock_details_id_product_foreign). Los nombres se agregan después con
   * getDetailsNamesByIds() solo para la página (F2-10).
   */
  public static function getAll() {
    return self::join('users','stocks.id_user','=','users.id')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('stock_details')
                        ->whereColumn('stock_details.id_stock', 'stocks.id');
                })
                ->select('stocks.id', 'stocks.date', 'stocks.type', 'stocks.notes', 'users.name');

  }

  /**
   * id_stock => "Producto A, Producto B" (orden de stock_details.id, como el
   * group_concat del listado anterior).
   */
  public static function getDetailsNamesByIds(array $ids) {
    return DB::table('stock_details')
                ->join('products','stock_details.id_product','=','products.id')
                ->whereIn('stock_details.id_stock', $ids)
                ->groupBy('stock_details.id_stock')
                ->select('stock_details.id_stock',
                DB::raw('group_concat(products.name order by stock_details.id separator ", ") AS details'))
                ->pluck('details', 'id_stock');

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
