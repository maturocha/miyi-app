<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class StorePromotionRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Formatear fechas si están presentes
        if ($this->has('starts_at')) {
            $this->merge([
                'starts_at' => $this->formatDate($this->starts_at)
            ]);
        }

        if ($this->has('ends_at') && $this->ends_at) {
            $this->merge([
                'ends_at' => $this->formatDate($this->ends_at)
            ]);
        }
    }

    /**
     * Format date to Y-m-d format
     *
     * @param string $date
     * @return string
     */
    private function formatDate($date)
    {
        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return $date; // Return original if parsing fails
        }
    }

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
        $rules = [
            'name' => 'required|string|max:120',
            'type' => 'required|in:BUY_X_GET_Y,NTH_PERCENT,LINE_PERCENT',
            'params' => 'required|array',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer',
            'exclusive' => 'sometimes|boolean',
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ];

        // Reglas específicas según el tipo de promoción
        if ($this->input('type') === 'BUY_X_GET_Y') {
            $rules['params.x'] = 'required|integer|min:1';
            $rules['params.y'] = 'required|integer|min:1';
        }

        if ($this->input('type') === 'NTH_PERCENT') {
            $rules['params.n'] = 'required|integer|min:2';
            $rules['params.percent'] = 'required|numeric|min:0|max:100';
        }

        if ($this->input('type') === 'LINE_PERCENT') {
            $rules['params.percent'] = 'required|numeric|min:0|max:100';
        }

        return $rules;
    }
}
