<?php

namespace App;

use App\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderPayment extends Model
{
    protected $table = 'delivery_order_payments';

    protected $fillable = [
        'delivery_order_id',
        'payment_method',
        'amount',
        'payment_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
    ];

    /**
     * Get the delivery order that owns the payment.
     */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }
}

