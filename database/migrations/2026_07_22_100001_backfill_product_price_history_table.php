<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BackfillProductPriceHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('stock_details')
            ->join('stocks', 'stock_details.id_stock', '=', 'stocks.id')
            ->where('stock_details.price_purchase', '>', 0)
            ->orderBy('stocks.created_at', 'asc')
            ->select(
                'stock_details.id as id',
                'stock_details.id_product as product_id',
                'stock_details.price_purchase as price_purchase',
                'stocks.id as source_id',
                'stocks.id_user as created_by_user_id',
                'stocks.created_at as occurred_at'
            )
            ->chunkById(500, function ($rows) {
                $data = [];
                foreach ($rows as $row) {
                    $data[] = [
                        'product_id' => $row->product_id,
                        'price_purchase' => $row->price_purchase,
                        'percentage_may' => null,
                        'percentage_min' => null,
                        'price_unit' => null,
                        'price_min' => null,
                        'source_type' => 'stock',
                        'source_id' => $row->source_id,
                        'created_by_user_id' => $row->created_by_user_id,
                        'occurred_at' => $row->occurred_at,
                        'created_at' => $row->occurred_at,
                        'updated_at' => $row->occurred_at,
                    ];
                }
                if (!empty($data)) {
                    DB::table('product_price_history')->insert($data);
                }
            }, 'stock_details.id', 'id');

        DB::table('products')
            ->orderBy('id')
            ->chunkById(500, function ($products) {
                $data = [];
                foreach ($products as $product) {
                    $data[] = [
                        'product_id' => $product->id,
                        'price_purchase' => $product->price_purchase,
                        'percentage_may' => $product->percentage_may,
                        'percentage_min' => $product->percentage_min,
                        'price_unit' => $product->price_unit,
                        'price_min' => $product->price_min,
                        'source_type' => 'product',
                        'source_id' => null,
                        'created_by_user_id' => null,
                        'occurred_at' => $product->updated_at ?? $product->created_at,
                        'created_at' => $product->updated_at ?? $product->created_at,
                        'updated_at' => $product->updated_at ?? $product->created_at,
                    ];
                }
                if (!empty($data)) {
                    DB::table('product_price_history')->insert($data);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('product_price_history')->truncate();
    }
}
