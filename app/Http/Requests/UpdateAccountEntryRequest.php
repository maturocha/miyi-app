<?php

namespace App\Http\Requests;

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
        ];
    }
}
