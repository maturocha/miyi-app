<?php

namespace App\Http\Requests;

use App\Models\AccountEntryDirection;
use App\Models\AccountEntryType;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountEntryRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('create', \App\Models\AccountEntry::class);
    }

    public function rules()
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:' . implode(',', AccountEntryType::all()),
            'direction' => 'required|in:' . implode(',', AccountEntryDirection::all()),
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'occurred_at' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'delivery_id' => 'nullable|exists:deliveries,id',
            'lines' => 'nullable|array|min:0',
            'lines.*.method' => 'required_with:lines|string|in:' . implode(',', PaymentMethod::all()),
            'lines.*.amount' => 'required_with:lines|numeric|min:0.01|max:99999999.99',
            'lines.*.reference' => 'nullable|string|max:191',
        ];
    }
}
