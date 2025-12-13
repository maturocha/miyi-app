<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PromotionResource;

class ProductResource extends JsonResource
{

    public function toArray($request)
    {
        $user = auth()->guard('api')->user();
        $isAdmin = $user && $user->hasRole('admin');
        $isContable = $user && $user->hasRole('administracion');
        $isAdminOrContable = $isAdmin || $isContable;

        $data = [
            'id'                => $this->id,
            'barcode'           => $this->barcode,
            'name'              => $this->name,
            'description'       => $this->description,
            'interval_quantity' => $this->interval_quantity,
            'own_product'       => (bool) $this->own_product,
            'no_stock'          => (bool) $this->no_stock,
            'category'          => $this->category->name,
            'id_category'       => $this->id_category,
            'code_miyi'         => $this->code_miyi,
            'price_min'      => $this->price_min,
            'price_unit'     => $this->price_unit,
            'stock'           => $this->stock,
            'bulto'           => $this->bulto,
            'type_product'    => $this->type_product,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
            'image_path'      => ($image = $this->getImages()) ? $image->path : null,
            'active_promotions' => $this->activePromotions->map(function ($promotion) {
                return [
                    'id' => $promotion->id,
                    'type' => $promotion->type,
                    'name' => $promotion->name,
                    'params' => $promotion->params,
                ];
            }),
        ];

        if ($isAdmin) {
            $data = array_merge($data, [
                'price_purchase' => $this->price_purchase,
                'percentage_may' => $this->percentage_may,
                'percentage_min' => $this->percentage_min,
                'show_store'      => (bool) $this->show_store,
            ]);
        }

        if ($isAdminOrContable) {
            $data = array_merge($data, [
                
                'history_prices'  => $this->historyPrices(),
                'history_stock'   => $this->stockMoving(),
                'history_sales'   => $this->orderMoving(),
                'min_stock'       => $this->min_stock
            ]);
        }

        return $data;
    }
} 