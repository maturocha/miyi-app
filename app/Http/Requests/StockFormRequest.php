<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockFormRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'type' => 'required|in:in,out',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id_product' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];

        if ($this->get('type') === 'in') {
            $rules['items.*.id_provider'] = 'required|integer|exists:providers,id';
            $rules['items.*.price_purchase'] = 'required|numeric|min:0';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'type.required' => 'El tipo es obligatorio.',
            'type.in' => 'El tipo debe ser in u out.',
            'items.required' => 'Debes enviar al menos un item.',
            'items.array' => 'El campo items debe ser un array.',
            'items.*.id_product.required' => 'El producto es obligatorio.',
            'items.*.id_product.integer' => 'El producto debe ser un número.',
            'items.*.id_product.exists' => 'El producto no existe.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.numeric' => 'La cantidad debe ser un número.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
            'items.*.id_provider.integer' => 'El proveedor debe ser un número.',
            'items.*.id_provider.exists' => 'El proveedor no existe.',
            'items.*.price_purchase.numeric' => 'El precio de compra debe ser un número.',
            'items.*.price_purchase.min' => 'El precio de compra debe ser mayor o igual a 0.',
        ];
    }
} 