<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Order_details extends Model
{
  protected $table = 'order_details';
  protected $primaryKey = 'id';
  protected $fillable = [
      'id_order', 'id_product', 'promotion_id', 'quantity', 'discount', 'price_unit', 'price_final', 'weight'
  ];

  public function getRecordTitle()
  {
      return 'Pedido del ' . Carbon::parse($this->date)->format('d/m/Y') ;
  }

  /**
   * Calculate the final price based on quantity, unit price and discount percentage.
   *
   * @param float $quantity
   * @param float $priceUnit
   * @param float $discountPercentage
   * @return float
   */
  public static function calculateFinalPrice(float $quantity, float $priceUnit, float $discountPercentage = 0): float
  {
      // Calcular el subtotal (cantidad * precio unitario)
      $subtotal = $quantity * $priceUnit;
      
      // Calcular el descuento en monto
      $discountAmount = ($subtotal * $discountPercentage) / 100;
      
      // Calcular el precio final (subtotal - descuento)
      $finalPrice = $subtotal - $discountAmount;
      
      // Redondear a 2 decimales
      return round($finalPrice, 2);
  }

  /**
   * Get the product relationship.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function product()
  {
      return $this->belongsTo(Product::class, 'id_product', 'id');
  }

  /**
   * Get the order relationship.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function order()
  {
      return $this->belongsTo(Order::class, 'id_order', 'id');
  }

  /**
   * Get the promotion relationship.
   *
   * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
   */
  public function promotion()
  {
      return $this->belongsTo(Promotion::class, 'promotion_id');
  }

}
