<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\Stock_details;

class StockDetailsObserver
{
    /**
     * A stock detail only carries price_purchase when it comes from an 'in' load
     * (see StockController::buildStockDetails), so that's the only signal needed here.
     */
    public function created(Stock_details $stockDetail): void
    {
        if (!$stockDetail->price_purchase || $stockDetail->price_purchase <= 0) {
            return;
        }

        $product = Product::find($stockDetail->id_product);
        if (!$product) {
            return;
        }

        $stock = $stockDetail->stock;

        ProductPriceHistory::create([
            'product_id' => $product->id,
            'price_purchase' => $stockDetail->price_purchase,
            'percentage_may' => $product->percentage_may,
            'percentage_min' => $product->percentage_min,
            'price_unit' => $product->price_unit,
            'price_min' => $product->price_min,
            'source_type' => 'stock',
            'source_id' => $stockDetail->id_stock,
            'created_by_user_id' => $stock ? $stock->id_user : null,
            'occurred_at' => $stock ? $stock->date : now(),
        ]);
    }
}
