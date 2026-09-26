<?php

namespace App\Http\Requests;

use App\Models\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountEntryRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('update', $this->route('account_entry'));
    }

    public function rules()
    {
        return [
            'amount' => 'sometimes|numeric|min:0.01|max:99999999.99',
            'occurred_at' => 'sometimes|date',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'sometimes|array|min:1',
            'lines.*.method' => 'required_with:lines|string|in:' . implode(',', PaymentMethod::all()),
            'lines.*.amount' => 'required_with:lines|numeric|min:0.01|max:99999999.99',
            'lines.*.reference' => 'nullable|string|max:191',
        ];
    }
}
