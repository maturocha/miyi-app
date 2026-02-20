<?php

namespace App\Http\Requests;

use App\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', \App\Delivery::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'delivery_date' => 'required|date|after_or_equal:today',
            'owner_user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:1000',
            'orders' => 'required|array',
            'orders.*.id' => [
                'required_with:orders',
                'integer',
                Rule::exists('orders', 'id')->whereIn('status', [OrderStatus::READY_TO_SHIP, OrderStatus::FAILED]),
            ],
            'orders.*.sequence' => 'nullable|integer|min:1',
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
            'delivery_date.required' => 'La fecha de reparto es obligatoria.',
            'delivery_date.date' => 'La fecha de reparto debe ser una fecha válida.',
            'owner_user_id.required' => 'El encargado del reparto es obligatorio.',
            'owner_user_id.exists' => 'El usuario seleccionado no existe.',
            'notes.string' => 'Las notas deben ser texto.',
            'notes.max' => 'Las notas no pueden exceder los 1000 caracteres.',
            'orders.array' => 'Los pedidos deben ser un array.',
            'orders.*.integer' => 'Cada ID de pedido debe ser un número entero.',
            'orders.*.exists' => 'Uno de los pedidos seleccionados no existe.',
        ];
    }
}
