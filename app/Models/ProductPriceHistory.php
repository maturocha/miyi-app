<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceHistory extends Model
{
    protected $table = 'product_price_history';
    protected $primaryKey = 'id';
    protected $fillable = [
        'product_id', 'price_purchase', 'percentage_may', 'percentage_min',
        'price_unit', 'price_min', 'source_type', 'source_id', 'created_by_user_id',
        'occurred_at',
    ];
    protected $dates = ['occurred_at'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
