<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Delivery;
use App\DeliveryOrderStatus;
use App\PaymentMethod;

class DeliveryOrderUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $delivery = $this->route('delivery');
        if ($delivery instanceof Delivery) {
            return $this->user()->can('updateOrder', $delivery);
        }
        $delivery = Delivery::find($delivery);

        return $delivery && $this->user()->can('updateOrder', $delivery);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'delivery_status' => 'required|in:' . implode(',', DeliveryOrderStatus::all()),
            'collected_amount' => 'nullable|numeric|min:0|max:9000000',
            'payment_method' => 'nullable|in:' . implode(',', PaymentMethod::all()),
            'payment_reference' => 'nullable|string|max:255',
            'observations' => 'nullable|string|max:1000',
            'failure_reason' => 'required_if:delivery_status,' . DeliveryOrderStatus::FAILED . '|nullable|string|max:500',
            'payments' => 'nullable|array',
            'payments.*.payment_method' => 'required_with:payments.*.amount|in:' . implode(',', PaymentMethod::all()),
            'payments.*.amount' => 'nullable|numeric|min:0.01|max:9000000',
            'payments.*.payment_reference' => 'nullable|string|max:255',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $collectedAmount = (float) $this->input('collected_amount', 0);
            $payments = $this->input('payments', []);

            $hasPayments = is_array($payments) && collect($payments)
                ->filter(function ($p) {
                    return isset($p['amount']) && (float) $p['amount'] > 0 && !empty($p['payment_method']);
                })
                ->isNotEmpty();

            // Si hay monto cobrado pero no se informan pagos ni método legacy, marcar error
            if ($collectedAmount > 0 && !$hasPayments && !$this->input('payment_method')) {
                $validator->errors()->add('payments', 'Debe registrar al menos un pago con método y monto cuando hay monto cobrado.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'delivery_status.required' => 'El estado de entrega es obligatorio.',
            'delivery_status.in' => 'El estado de entrega no es válido.',
            'collected_amount.numeric' => 'El monto cobrado debe ser un número.',
            'collected_amount.min' => 'El monto cobrado no puede ser negativo.',
            'collected_amount.max' => 'El monto cobrado no puede exceder 9,000,000.',
            'payment_method.required_if' => 'El método de pago es requerido cuando hay monto cobrado.',
            'payment_method.in' => 'El método de pago no es válido.',
            'payment_reference.max' => 'La referencia de pago no puede exceder los 255 caracteres.',
            'observations.max' => 'Las observaciones no pueden exceder los 1000 caracteres.',
            'failure_reason.required_if' => 'La razón del fallo es obligatoria cuando el estado es fallido.',
            'failure_reason.max' => 'La razón del fallo no puede exceder los 500 caracteres.',
        ];
    }
}
