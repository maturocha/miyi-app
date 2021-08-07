<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use DB;

class Order extends Model
{
  protected $table = 'orders';
  protected $primaryKey = 'id';
  protected $fillable = [
    'date', 'reference_code', 'id_user', 'id_customer', 'notes', 'total', 'invoice_afip', 'nro_afip',
    'print_status', 'total_bruto', 'delivery_cost', 'payment_method','discount'
  ];

  public function getRecordTitle()
  {
      return 'Pedido del ' . Carbon::parse($this->date)->format('d/m/Y') ;
  }

  public static function getByID($id) {
    return self::where('orders.id', '=', $id)
                ->leftjoin('users','orders.id_user','=','users.id')
                ->leftjoin('customers','orders.id_customer','=','customers.id')
                ->leftjoin('neighborhoods','customers.id_neighborhood','=','neighborhoods.id')
                ->leftjoin('zones','neighborhoods.id_zone','=','zones.id')
                ->select('orders.id', 'orders.date', 'orders.total', 'orders.discount', 'orders.notes', 
                          'orders.total_bruto', 'orders.delivery_cost', 'orders.payment_method',
                          'users.name', 'id_customer', 
                          'customers.name as customer', 'customers.address', 'customers.cellphone', 
                          'neighborhoods.name as neighborhood', 'zones.name as zone')
                ->first();

  }

  public static function getDetailsByID($id) {
    return self::where('orders.id', '=', $id)
                ->join('order_details','order_details.id_order','=','orders.id')
                ->join('products','order_details.id_product','=','products.id')
                ->select('products.id as id_product','products.name', 'products.type_product', 'order_details.quantity', 'order_details.price_unit', 'order_details.discount', 'order_details.price_final', 'order_details.id')
                
                ->get();

  }

  public static function getDetailsToDelete($id) {
    return self::where('orders.id', '=', $id)
                  ->join('order_details','order_details.id_order','=','orders.id')
                ->pluck('order_details.id')->toArray();

  }

  public function getproductsByID($id) {
    return self::where('sales.id', '=', $id)
                ->join('sale_details','sale_details.id_sale','=','sales.id')
                ->join('products','sale_details.id_product','=','products.id')
                ->distinct()
                ->pluck('products.name')->toArray();

  }

  public static function getReturnDetailsByID($id) {
    return self::where('sales.id', '=', $id)
                ->join('stocks','sales.id_return','=','stocks.id')
                ->join('stock_details','stock_details.id_stock','=','sales.id_return')
                ->join('products','stock_details.id_product','=','products.id')
                ->join('colors','stock_details.id_color','=','colors.id')
                ->select('products.name as product', 'colors.name as color', 'stock_details.size', 'stock_details.quantity' )
                ->get();
  }

  public static function getPaymentDetailsByID($id) {
    return self::where('sales.id', '=', $id)
                ->join('payments','sales.id_payment','=','payments.id')
                ->join('payment_details','payment_details.id_payment','=','payments.id')
                ->select('payment_details.id', 'payment_details.subtotal', 'payment_details.payment_type', 'payment_details.card_type', 'payment_details.card_name', 'payment_details.card_number',  'payment_details.card_lote',  'payment_details.card_cupon',  'payment_details.card_fees' )
                ->get();
  }

  public static function getPaymentByDate($from, $to) {
    return self::join('users','sales.id_user','=','users.id')
                ->join('payments','sales.id_payment','=','payments.id')
                ->join('payment_details','payment_details.id_payment','=','payments.id')
                ->whereBetween('sales.date', [$from, $to])
                ->orderBy('payment_details.payment_type', 'payment_details.card_type','sales.id')
                ->select('sales.id', 'sales.date', 'users.name', 'payment_details.payment_type', 'payment_details.card_type', 'payment_details.subtotal')
                ->get();
  }

  public static function getProductsByDate($date, $zone, $own_product) {
    return self::join('order_details','order_details.id_order','=','orders.id')
                ->join('products','products.id','=','order_details.id_product')
                ->join('categories','products.id_category','=','categories.id')
                ->join('customers','orders.id_customer','=','customers.id')
                ->join('neighborhoods','customers.id_neighborhood','=','neighborhoods.id')
                ->join('zones','neighborhoods.id_zone','=','zones.id')
                ->where('orders.date', $date)
                ->where('zones.id', $zone)
                ->where('products.own_product', $own_product)
                ->orderBy('category')
                ->select('categories.name as category', 'products.name', DB::raw('sum(order_details.quantity) as cant'))
                ->groupBy('category', 'products.name')
                ->get();
  }

  public static function getCustomerByDate($date, $zone) {

    return self::join('customers','orders.id_customer','=','customers.id')
                ->join('neighborhoods','customers.id_neighborhood','=','neighborhoods.id')
                ->join('zones','neighborhoods.id_zone','=','zones.id')
                ->join('users','orders.id_user','=','users.id')
                ->whereDate('orders.date', $date)
                ->where('zones.id', $zone)
                ->orderBy('neighborhoods.name',  'asc')
                ->orderBy('customers.name',  'asc')
                //->groupBy('customers.id')
                //->groupBy('users.id')
                ->select('users.name as user', 'neighborhoods.name as neighborhood', 'customers.address', 'customers.name as customer', 'orders.total as total', 'orders.id as id_order')
                ->get();
  }

}
