<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountEntryPaymentMethod extends Model
{
    protected $table = 'account_entry_payment_methods';

    protected $fillable = [
        'account_entry_id',
        'payment_method',
        'amount',
        'payment_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function accountEntry(): BelongsTo
    {
        return $this->belongsTo(AccountEntry::class, 'account_entry_id', 'id');
    }
}
