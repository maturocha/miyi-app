<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\DeliveryStatus;

class DeliveryUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('update', $this->route('delivery'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $delivery = $this->route('delivery');
        
        return [
            'delivery_date' => 'sometimes|date',
            'owner_user_id' => 'sometimes|exists:users,id',
            'status' => 'sometimes|in:' . implode(',', DeliveryStatus::all()),
            'expenses_amount' => 'nullable|numeric|min:0|max:999999.99',
            'expenses_notes' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:1000',
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
            $delivery = $this->route('delivery');
            
            // Validar transiciones de status
            if ($this->has('status') && $delivery) {
                $newStatus = $this->input('status');
                $currentStatus = $delivery->status;
                
                // Validar transiciones válidas
                $validTransitions = [
                    DeliveryStatus::NOT_STARTED => [DeliveryStatus::IN_PROGRESS],
                    DeliveryStatus::IN_PROGRESS => [DeliveryStatus::FINISHED],
                    DeliveryStatus::FINISHED => [DeliveryStatus::CLOSED],
                ];
                
                if (isset($validTransitions[$currentStatus]) && 
                    !in_array($newStatus, $validTransitions[$currentStatus]) && 
                    $newStatus !== $currentStatus) {
                    $validator->errors()->add('status', 'Transición de estado no válida.');
                }
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
            'delivery_date.date' => 'La fecha de reparto debe ser una fecha válida.',
            'owner_user_id.exists' => 'El usuario seleccionado no existe.',
            'status.in' => 'El estado seleccionado no es válido.',
            'expenses_amount.numeric' => 'El monto de gastos debe ser un número.',
            'expenses_amount.min' => 'El monto de gastos no puede ser negativo.',
            'expenses_amount.max' => 'El monto de gastos no puede exceder 999,999.99.',
            'expenses_notes.max' => 'Las notas de gastos no pueden exceder los 2000 caracteres.',
            'notes.max' => 'Las notas no pueden exceder los 1000 caracteres.',
        ];
    }
}
