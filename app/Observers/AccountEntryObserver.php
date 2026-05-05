<?php

namespace App\Observers;

use App\AccountEntry;
use App\AccountEntryValidationStatus;
use App\Services\UpdateCustomerBalanceService;

class AccountEntryObserver
{
    protected $balanceService;

    public function __construct(UpdateCustomerBalanceService $balanceService)
    {
        $this->balanceService = $balanceService;
    }

    /**
     * Customer balance only changes when validation_status is validated.
     */
    public function created(AccountEntry $entry): void
    {
        if ($entry->validation_status !== AccountEntryValidationStatus::VALIDATED) {
            return;
        }
        $this->applyEntryDelta($entry);
    }

    public function updated(AccountEntry $entry): void
    {
        $wasValidated = $entry->getOriginal('validation_status') === AccountEntryValidationStatus::VALIDATED;
        $isValidated = $entry->validation_status === AccountEntryValidationStatus::VALIDATED;

        if (!$wasValidated && !$isValidated) {
            return;
        }

        if (!$wasValidated && $isValidated) {
            $this->applyEntryDelta($entry);
            return;
        }

        if ($wasValidated && !$isValidated) {
            $oldDirection = $entry->getOriginal('direction');
            $oldAmount = (float) $entry->getOriginal('amount');
            $oldDelta = UpdateCustomerBalanceService::deltaForEntry($oldDirection, $oldAmount);
            $this->balanceService->increment((int) $entry->getOriginal('customer_id'), -$oldDelta);
            return;
        }

        if (!$entry->wasChanged(['direction', 'amount'])) {
            return;
        }

        $oldDirection = $entry->getOriginal('direction');
        $oldAmount = (float) $entry->getOriginal('amount');
        $oldDelta = UpdateCustomerBalanceService::deltaForEntry($oldDirection, $oldAmount);
        $newDelta = UpdateCustomerBalanceService::deltaForEntry(
            $entry->direction,
            (float) $entry->amount
        );
        $netDelta = $newDelta - $oldDelta;
        $this->balanceService->increment((int) $entry->customer_id, $netDelta);
    }

    public function deleted(AccountEntry $entry): void
    {
        if ($entry->validation_status !== AccountEntryValidationStatus::VALIDATED) {
            return;
        }
        $delta = UpdateCustomerBalanceService::deltaForEntry(
            $entry->direction,
            (float) $entry->amount
        );
        $this->balanceService->increment($entry->customer_id, -$delta);
    }

    protected function applyEntryDelta(AccountEntry $entry): void
    {
        $delta = UpdateCustomerBalanceService::deltaForEntry(
            $entry->direction,
            (float) $entry->amount
        );
        $this->balanceService->increment($entry->customer_id, $delta);
    }
}
