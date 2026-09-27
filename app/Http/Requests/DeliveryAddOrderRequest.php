<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Delivery;

class DeliveryAddOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // El controller recibe `$id` (sin route model binding): `{delivery}`
        // llega como string y el Gate no encontraba policy → 403 siempre.
        $delivery = $this->route('delivery');
        if (!$delivery instanceof Delivery) {
            $delivery = Delivery::find($delivery);
        }

        return $delivery && $this->user()->can('addOrders', $delivery);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'order_id' => 'required|exists:orders,id',
            'override' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'order_id.required' => 'El ID del pedido es obligatorio.',
            'order_id.exists' => 'El pedido seleccionado no existe.',
            'override.boolean' => 'El campo override debe ser verdadero o falso.',
        ];
    }
}
