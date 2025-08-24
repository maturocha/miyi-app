<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductFormRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $price_purchase = $this->input('price_purchase');
        $percentage_may = $this->input('percentage_may');
        $percentage_min = $this->input('percentage_min');

        if ($price_purchase !== null && $percentage_may !== null && $percentage_min !== null) {
            $price_unit = $price_purchase + (($price_purchase * $percentage_may) / 100);
            $price_min = $price_purchase + (($price_purchase * $percentage_min) / 100);

            $this->merge([
                'price_unit' => number_format((float)$price_unit, 2, '.', ''),
                'price_min'  => number_format((float)$price_min, 2, '.', ''),
            ]);
        }
    }

    public function rules()
    {
        return [
            'barcode'         => 'nullable|string|max:191',
            'name'            => 'required|string|max:191',
            'description'     => 'nullable|string|max:191',
            'price_unit'      => 'required|numeric',
            'interval_quantity' => 'required|numeric',
            'own_product'     => 'required|boolean',
            'id_category'     => 'required|exists:categories,id',
            'code_miyi'       => 'required|string|max:191',
            'price_purchase'  => 'required|numeric',
            'percentage_may'  => 'required|numeric|min:0.01|max:100',
            'percentage_min'  => 'required|numeric|min:0.01|max:100',
            'price_min'       => 'required|numeric',
            'bulto'           => 'required|numeric',
            'show_store'      => 'required|boolean',
            'min_stock'       => 'required|integer',
            'type_product'    => 'required|string|in:u,w',
        ];
    }
} 