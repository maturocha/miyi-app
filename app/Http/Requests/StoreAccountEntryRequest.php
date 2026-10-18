<?php

namespace App\Http\Requests;

use App\Models\AccountEntry;
use App\Models\Delivery;
use App\Models\Enums\AccountEntryDirection;
use App\Models\Enums\DeliveryStatus;
use App\Models\Enums\AccountEntryType;
use App\Models\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccountEntryRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->can('create', \App\Models\AccountEntry::class);
    }

    /** Regla de forma de la clave de idempotencia (única regla en un replay). */
    const IDEMPOTENCY_KEY_RULES = ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'];

    /** @var AccountEntry|null|false false = todavía no se buscó */
    protected $replayEntry = false;

    /**
     * Asiento ya creado por este usuario con la `idempotency_key` recibida (o null).
     * Solo se busca si la clave tiene forma válida.
     */
    public function replayEntry(): ?AccountEntry
    {
        if ($this->replayEntry === false) {
            $this->replayEntry = null;
            $key = $this->input('idempotency_key');
            if (is_string($key) && strlen($key) <= 64 && preg_match('/^[A-Za-z0-9_-]+$/', $key) && $this->user()) {
                $this->replayEntry = AccountEntry::with('paymentMethods')
                    ->where('created_by_user_id', $this->user()->id)
                    ->where('idempotency_key', $key)
                    ->first();
            }
        }
        return $this->replayEntry;
    }

    public function rules()
    {
        // Reintento de un cobro ya creado: solo se valida la clave, así un cambio posterior
        // (p. ej. reparto cerrado) no convierte el replay en 422.
        if ($this->replayEntry()) {
            return ['idempotency_key' => self::IDEMPOTENCY_KEY_RULES];
        }

        return [
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:' . implode(',', AccountEntryType::all()),
            'direction' => 'required|in:' . implode(',', AccountEntryDirection::all()),
            'amount' => 'required|numeric|min:0.01|max:99999999.99',
            'occurred_at' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'delivery_id' => [
                'nullable',
                'exists:deliveries,id',
                // Un cobro con delivery_id queda PENDING hasta el cierre; si el
                // reparto ya está cerrado nunca se valida ni impacta el saldo.
                function ($attribute, $value, $fail) {
                    $delivery = Delivery::find($value);
                    if ($delivery && $delivery->status === DeliveryStatus::CLOSED) {
                        $fail('El reparto ya está cerrado; registrá el cobro sin reparto.');
                    }
                },
            ],
            'lines' => 'nullable|array|min:0',
            'lines.*.method' => 'required_with:lines|string|in:' . implode(',', PaymentMethod::all()),
            'lines.*.amount' => 'required_with:lines|numeric|min:0.01|max:99999999.99',
            'lines.*.reference' => 'nullable|string|max:191',
            // Opcional: reintentos con la misma clave devuelven el asiento ya creado.
            'idempotency_key' => self::IDEMPOTENCY_KEY_RULES,
        ];
    }
}
