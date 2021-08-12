<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;

class Product extends Model
{
  use SoftDeletes;

  protected $table = 'products';
  protected $primaryKey = 'id';
  protected $fillable = [
      'name', 'description', 'price_unit', 'stock', 'id_category', 'interval_quantity', 'own_product',
      'code_miyi', 'price_purchase', 'percentage_may', 'percentage_min', 'price_min', 'bulto', 'show_store',
      'min_stock', 'type_product'
  ];
  protected $dates = ['deleted_at'];
  protected $hidden = ['created_at', 'updated_at'];

  public function getRecordTitle()
  {
      return $this->name;
  }

  public static function inStock() {
    return self::select('products.id as id', 'products.name as name', 'products.code_miyi as code', 'products.stock', 'products.price_unit', 'products.interval_quantity', 'products.own_product', 'products.bulto')
                ->groupBy('products.id')
                ->orderBy('products.name')
                ->havingRaw('(products.stock > 0) AND (products.own_product = 1) OR ((products.stock <> 0) AND (products.own_product = 0))')
                ->get();
  }

  public static function inLowStock($min) {
    return self::select('products.id as id', 'products.name as name', 'products.code_miyi as code', 'products.stock', 'products.price_unit', 'products.interval_quantity', 'products.own_product', 'products.bulto')
                ->where('products.stock', '>=', 0)
                ->whereRaw('products.stock <= products.min_stock')
                //->where('products.stock', '<=', 'products.min_stock')
                ->where('products.own_product', '=', 1)
                ->groupBy('products.id')
                ->orderBy('products.stock', 'asc')
                
                ->get();
  }

  public static function inStockStore() {
    return self::select('categories.name as category','products.id as id', 
                        'products.name as name', 'products.code_miyi as code', 
                        'products.stock', 'products.price_min as price', 'products.own_product', 
                        'products.interval_quantity', 'products.bulto',
                        DB::raw("CONCAT('https://admin.distribuidoramiyi.com.ar', images.path) AS img") )
                ->join('categories','products.id_category','=','categories.id')
                ->leftjoin('images','products.id','=','images.id_product')
                ->where('show_store','=', 1)
                ->where(function($queryContainer){
                  $queryContainer->where(function($q){
                      $q->where('stock','>',0)
                          ->where('own_product','=',1);
                      })
                      ->orwhere(function($q){
                          $q->where('stock','<>',0)
                          ->where('own_product','=', 0);
                          });    
                  })
                //->groupBy('products.id')
                ->orderBy('products.name')
                ->get();
  }

  public static function listStock($price = 'price_unit', $exclude_category = false) {
    return self::select('products.id as id', 'products.name as name', 'products.stock', 'products.' . $price, 'products.interval_quantity', 'products.own_product', 'categories.name as category')
                ->join('categories','products.id_category','=','categories.id')
                ->groupBy('products.id')
                ->orderBy('categories.name')
                ->orderBy('products.name')
                ->havingRaw('products.stock <> 0')
                ->when($exclude_category, function ($q, $exclude_category) {
                  return $q->where('categories.name', '<>', $exclude_category);
                })
                ->get();
  }

  public static function sales() {
    return self::select('products.id', 'products.name')
                ->join('sale_details','products.id','=','sale_details.id_product')
                ->groupBy('products.id')
                ->havingRaw('sum(sale_details.quantity) > 0')
                ->get();
  }

  public static function getByID($id) {
    return self::where('products.id', '=', $id)
                ->join('categories','products.id_category','=','categories.id')
                ->select('products.id', 'products.name', 'products.description', 'products.price_unit', 'products.interval_quantity', 'own_product',
                        'products.stock', 'categories.name as category', 'code_miyi', 'price_purchase', 'percentage_may', 'percentage_min', 'price_min', 'bulto', 'show_store', 'min_stock')
                ->first();

  }

  public static function getStockByProductID($id)
  {
    return self::where('id_product', '=', $id)
                    ->join('product_in_stock','products.id','=','product_in_stock.id_product')
                    ->join('colors','product_in_stock.id_color','=','colors.id')
                    ->select('product_in_stock.size', 'colors.name','colors.id as id_color', DB::raw('sum(product_in_stock.stock_actual) as cant'))
                    ->groupBy('product_in_stock.size', 'colors.name', 'colors.id')
                    ->orderByRaw(\DB::raw("FIND_IN_SET(size, 'unico,xs,s,m,l,xl, 34, 35, 36, 37, 38, 39, 40' )"))
                    ->havingRaw('sum(product_in_stock.stock_actual) > 0')
                    ->get();

  }

  public static function getProductSoldByDate($from, $to) {
    return self::join('sale_details','products.id','=','sale_details.id_product')
                ->join('sales','sales.id','=','sale_details.id_sale')
                ->join('categories','products.id_category','=','categories.id')
                ->join('colors','sale_details.id_color','=','colors.id')
                ->whereBetween('sales.date', [$from, $to])
                ->select('products.id', 'sales.date', 'products.name as product', 'categories.name as category', 'sale_details.size', 'colors.name as color', 'sale_details.quantity')
                ->get();
  }

  public static function getProductChargeByDate($from, $to) {
    return self::join('stock_details','products.id','=','stock_details.id_product')
                ->join('stocks','stocks.id','=','stock_details.id_stock')
                ->join('categories','products.id_category','=','categories.id')
                ->join('colors','stock_details.id_color','=','colors.id')
                ->whereBetween('stocks.date', [$from, $to])
                ->select('products.id', 'stocks.date', 'products.name as product', 'categories.name as category', 'stock_details.size', 'colors.name as color', 'stock_details.quantity')
                ->get();
  }

  // public static function getStockMoving($id, $from, $to, $options) {
  //   return self::join('stock_details','products.id','=','stock_details.id_product')
  //               ->join('stocks','stocks.id','=','stock_details.id_stock')
  //               ->join('categories','products.id_category','=','categories.id')
  //               ->join('colors','stock_details.id_color','=','colors.id')
  //               ->when($id, function ($q, $id) {
  //                 return $q->where('products.id', '=', $id);
  //               })
  //               ->whereIn('stocks.type', $options)
  //               ->whereBetween('stocks.date', [$from, $to])
  //               ->orderBy('stocks.date')
  //               ->select('stocks.date', 'stocks.id', 'products.name as product', 'stocks.type', 'categories.name as category', 'stock_details.size', 'colors.name as color', 'stock_details.quantity')
  //               ->get();

  // }

  public static function getSalesMoving($id, $from, $to) {
    return self::join('sale_details','products.id','=','sale_details.id_product')
                ->join('sales','sales.id','=','sale_details.id_sale')
                ->join('categories','products.id_category','=','categories.id')
                ->join('colors','sale_details.id_color','=','colors.id')
                ->when($id, function ($q, $id) {
                  return $q->where('products.id', '=', $id);
                })
                ->whereBetween('sales.date', [$from, $to])
                ->orderBy('sales.date')
                ->select('sales.date', 'sales.id', 'products.name as product', 'categories.name as category', 'sale_details.size', 'colors.name as color', 'sale_details.quantity')
                ->get();

  }

  public function getImages() {
    return self::join('images','products.id','=','images.id_product')
                ->where('products.id', '=', $this->id)
                ->select('images.path')
                ->first();

  }

  public function stockMoving() {
    return self::join('stock_details','products.id','=','stock_details.id_product')
                ->join('stocks','stock_details.id_stock','=','stocks.id')
                ->where('products.id', '=', $this->id)
                ->select('stock_details.created_at as date', DB::raw('SUM(stock_details.quantity) as quantity'), 'stocks.type as type')
                ->orderBy('stock_details.created_at', 'DESC')
                ->groupBy('stock_details.id')
                ->get();

  }

  public function orderMoving() {
    return self::join('order_details','products.id','=','order_details.id_product')
                ->join('orders','order_details.id_order','=','orders.id')
                ->where('products.id', '=', $this->id)
                ->select('order_details.created_at as date', 'orders.id', DB::raw('SUM(order_details.quantity) as quantity'))
                ->orderBy('order_details.created_at', 'DESC')
                ->groupBy('order_details.id')
                ->get();

  }

  public function historyPrices() {
    return self::join('stock_details','products.id','=','stock_details.id_product')
                ->join('providers','providers.id','=','stock_details.id_provider')
                ->where('products.id', '=', $this->id)
                ->where('stock_details.price_purchase', '>', 0)
                ->select('stock_details.created_at as date', 'stock_details.price_purchase as price', 'providers.fullname as provider')
                ->get();

  }

}
