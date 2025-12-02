<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    // Constantes de tipos
    const BUY_X_GET_Y = 'BUY_X_GET_Y';
    const NTH_PERCENT = 'NTH_PERCENT';
    const LINE_PERCENT = 'LINE_PERCENT';
    const BUY_X_TOTAL_DISCOUNT = 'BUY_X_TOTAL_DISCOUNT';

    protected $fillable = [
        'name',
        'type',
        'params',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
        'exclusive'
    ];

    protected $casts = [
        'params' => 'array',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
        'exclusive' => 'boolean',
    ];

    /**
     * Relación con productos
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_products');
    }

    /**
     * Scope para promociones activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>=', now()->toDateString());
            });
    }
}
