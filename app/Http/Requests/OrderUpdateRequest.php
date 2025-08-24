<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class OrderUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'notes' => 'nullable|string|max:1000',
            'delivery_cost' => 'nullable|numeric|min:0|max:999999.99',
            'discount' => 'nullable|numeric|min:0|max:100',
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
            'notes.string' => 'Las notas deben ser texto.',
            'notes.max' => 'Las notas no pueden exceder los 1000 caracteres.',
            'delivery_cost.numeric' => 'El costo de entrega debe ser un número.',
            'delivery_cost.min' => 'El costo de entrega no puede ser negativo.',
            'delivery_cost.max' => 'El costo de entrega no puede exceder 999,999.99.',
            'discount.numeric' => 'El descuento debe ser un número.',
            'discount.min' => 'El descuento no puede ser negativo.',
            'discount.max' => 'El descuento no puede exceder el 100%.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'notes' => 'notas',
            'delivery_cost' => 'costo de entrega',
            'discount' => 'descuento',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Convertir valores vacíos a null para campos opcionales
        $this->merge([
            'notes' => $this->input('notes') ?: null,
            'delivery_cost' => $this->input('delivery_cost') ? (float) $this->input('delivery_cost') : 0,
            'discount' => $this->input('discount') ? (float) $this->input('discount') : 0,
        ]);
    }

   

    /**
     * Get the validated data from the request.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Calcular el total basado en los detalles de la orden
        if ($this->route('order')) {
            $order = $this->route('order');
            $totalBruto = $order->details()->sum('price_final');
            $deliveryCost = isset($validated['delivery_cost']) ? $validated['delivery_cost'] : 0;
            $discountPercentage = isset($validated['discount']) ? $validated['discount'] : 0;
            
            // Calcular el descuento en monto
            $discountAmount = ($totalBruto * $discountPercentage) / 100;
            
            // Calcular el total final
            $total = $totalBruto + $deliveryCost - $discountAmount;
            
            // Agregar los campos calculados
            $validated['total_bruto'] = round($totalBruto, 2);
            $validated['total'] = round($total, 2);
        }
        
        return $validated;
    }
}
