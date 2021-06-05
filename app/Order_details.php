<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Order_details extends Model
{
  protected $table = 'order_details';
  protected $primaryKey = 'id';
  protected $fillable = [
      'id_order', 'id_product', 'quantity', 'discount', 'price_unit', 'price_final'
  ];

  public function getRecordTitle()
  {
      return 'Pedido del ' . Carbon::parse($this->date)->format('d/m/Y') ;
  }

}
