<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductPriceHistory;

class ProductObserver
{
    protected const PRICE_FIELDS = [
        'price_purchase', 'percentage_may', 'percentage_min', 'price_unit', 'price_min',
    ];

    public function created(Product $product): void
    {
        $this->record($product);
    }

    public function updated(Product $product): void
    {
        if (!$product->wasChanged(self::PRICE_FIELDS)) {
            return;
        }
        $this->record($product);
    }

    protected function record(Product $product): void
    {
        ProductPriceHistory::create([
            'product_id' => $product->id,
            'price_purchase' => $product->price_purchase,
            'percentage_may' => $product->percentage_may,
            'percentage_min' => $product->percentage_min,
            'price_unit' => $product->price_unit,
            'price_min' => $product->price_min,
            'source_type' => 'product',
            'source_id' => null,
            'created_by_user_id' => auth()->id(),
            'occurred_at' => now(),
        ]);
    }
}
