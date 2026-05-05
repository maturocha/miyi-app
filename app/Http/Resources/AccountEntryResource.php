<?php

namespace App\Http\Resources;

use App\Helpers\AccountEntrySourceHelper;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountEntryResource extends JsonResource
{
    public function toArray($request)
    {
        $sourceDisplay = AccountEntrySourceHelper::resolve($this->source_type, $this->source_id);

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer' => $this->when($this->relationLoaded('customer') && $this->customer, function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                ];
            }),
            'type' => $this->type,
            'direction' => $this->direction,
            'amount' => (float) $this->amount,
            'occurred_at' => $this->occurred_at ? $this->occurred_at->toDateTimeString() : null,
            'notes' => $this->notes,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'source_label' => $sourceDisplay['label'],
            'source_link' => $sourceDisplay['link'],
            'created_by_user_id' => $this->created_by_user_id,
            'created_by_user' => $this->when($this->relationLoaded('createdByUser') && $this->createdByUser, function () {
                return [
                    'id' => $this->createdByUser->id,
                    'name' => $this->createdByUser->name,
                ];
            }),
            'delivery_id' => $this->delivery_id,
            'validation_status' => $this->validation_status,
            'balance_at_entry' => $this->balance_at_entry !== null ? (float) $this->balance_at_entry : null,
            'payment_methods' => $this->when($this->relationLoaded('paymentMethods'), function () {
                return $this->paymentMethods->map(function ($pm) {
                    return [
                        'id' => $pm->id,
                        'payment_method' => $pm->payment_method,
                        'amount' => (float) $pm->amount,
                        'payment_reference' => $pm->payment_reference,
                    ];
                })->values();
            }),
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}
