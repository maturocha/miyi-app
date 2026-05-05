<?php

namespace App\Policies;

use App\AccountEntry;
use App\AccountEntryValidationStatus;
use App\Delivery;
use App\DeliveryOrder;
use App\DeliveryStatus;
use App\User;

class AccountEntryPolicy
{
    /** Admin, administración, rol de gestión operativa (reparto / farmacia). */
    protected static function privilegedRoles(): array
    {
        return [1, 3, 4];
    }

    public function view(User $user, AccountEntry $entry)
    {
        return true;
    }

    public function create(User $user)
    {
        return in_array($user->role_id, self::privilegedRoles());
    }

    /**
     * Update: admin/administración, entry not validated, and (manual OR from delivery that is not closed).
     */
    public function update(User $user, AccountEntry $entry)
    {
        if (!in_array($user->role_id, self::privilegedRoles())) {
            return false;
        }
        if ($entry->validation_status === AccountEntryValidationStatus::VALIDATED) {
            return false;
        }
        if ($entry->isManual()) {
            return true;
        }
        if ($entry->source_type === 'delivery' && $entry->source_id) {
            $delivery = Delivery::find($entry->source_id);
            return $delivery && $delivery->status !== DeliveryStatus::CLOSED
                && (in_array($user->role_id, self::privilegedRoles(), true) || $delivery->owner_user_id === $user->id);
        }
        if ($entry->source_type === 'delivery_orders' && $entry->source_id) {
            $deliveryOrder = DeliveryOrder::with('delivery')->find($entry->source_id);
            $delivery = $deliveryOrder ? $deliveryOrder->delivery : null;
            return $delivery && $delivery->status !== DeliveryStatus::CLOSED
                && (in_array($user->role_id, self::privilegedRoles(), true) || $delivery->owner_user_id === $user->id);
        }
        return false;
    }

    /**
     * Solo desde UI: not_validated → validated (no pending).
     */
    public function validate(User $user, AccountEntry $entry)
    {
        if (!in_array($user->role_id, self::privilegedRoles())) {
            return false;
        }
        if ($entry->validation_status !== AccountEntryValidationStatus::NOT_VALIDATED) {
            return false;
        }

        return true;
    }

    public function delete(User $user, AccountEntry $entry)
    {
        if (!in_array($user->role_id, self::privilegedRoles())) {
            return false;
        }
        return $entry->isManual();
    }
}
