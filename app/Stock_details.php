<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Stock_details extends Model
{
  protected $table = 'stock_details';
  protected $primaryKey = 'id';
  protected $fillable = [
      'id_stock', 'id_product', 'quantity', 'due_date', 'bulto_reference', 'id_provider', 'price_purchase'
  ];

}
