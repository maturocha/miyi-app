<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountEntry extends Model
{
    protected $table = 'account_entries';

    protected $fillable = [
        'customer_id',
        'type',
        'direction',
        'amount',
        'occurred_at',
        'notes',
        'source_type',
        'source_id',
        'created_by_user_id',
        'validation_status',
        'balance_at_entry',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'balance_at_entry' => 'decimal:2',
    ];

    public function isValidated(): bool
    {
        return $this->validation_status === AccountEntryValidationStatus::VALIDATED;
    }

    public function isPendingValidation(): bool
    {
        return $this->validation_status === AccountEntryValidationStatus::PENDING;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(AccountEntryPaymentMethod::class, 'account_entry_id', 'id');
    }

    /**
     * When source_type is 'delivery', source_id is the delivery id.
     */
    public function getDeliveryIdAttribute()
    {
        return $this->source_type === 'delivery' ? $this->source_id : null;
    }

    public function isManual(): bool
    {
        return $this->source_type === 'manual';
    }
}
