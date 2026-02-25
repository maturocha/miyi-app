<?php

namespace App\Policies;

use App\Delivery;
use App\User;
use App\DeliveryStatus;

class DeliveryPolicy
{
    /**
     * Determine if the user can view any deliveries.
     */
    public function viewAny(User $user)
    {
        return true; // Todos los autenticados pueden ver el listado
    }

    /**
     * Determine if the user can view the delivery.
     */
    public function view(User $user, Delivery $delivery)
    {
        return true; // Todos los autenticados pueden ver detalles
    }

    /**
     * Determine if the user can create deliveries.
     */
    public function create(User $user)
    {
        // Solo admin (role_id = 1) o administración (role_id = 3)
        return in_array($user->role_id, [1, 3]);
    }

    /**
     * Determine if the user can update the delivery.
     */
    public function update(User $user, Delivery $delivery)
    {
        // Solo admin o administración, y solo si el reparto NO ha sido iniciado todavía
        if (!in_array($user->role_id, [1, 3])) {
            return false;
        }

        return $delivery->status === DeliveryStatus::NOT_STARTED;
    }

    /**
     * Determine if the user can delete the delivery.
     */
    public function delete(User $user)
    {
        // Solo admin
        return $user->role_id === 1;
    }

    /**
     * Determine if the user can start the delivery.
     */
    public function start(User $user, Delivery $delivery)
    {
        // Repartidor (owner) puede iniciar si status == NOT_STARTED
        if ($delivery->owner_user_id === $user->id && $delivery->status === DeliveryStatus::NOT_STARTED) {
            return true;
        }
        // Admin/administración puede iniciar siempre
        return in_array($user->role_id, [1, 3]);
    }

    /**
     * Determine if the user can finish the delivery.
     */
    public function finish(User $user, Delivery $delivery)
    {
        // Si IN_PROGRESS: repartidor (owner) puede finalizar
        if ($delivery->status === DeliveryStatus::IN_PROGRESS && $delivery->owner_user_id === $user->id) {
            return true;
        }
        // Admin/administración puede finalizar desde cualquier estado
        return in_array($user->role_id, [1, 3]);
    }

    /**
     * Determine if the user can close the delivery.
     */
    public function close(User $user, Delivery $delivery)
    {
        // Solo admin o administración, y solo desde FINISHED
        if (!in_array($user->role_id, [1, 3])) {
            return false;
        }
        return $delivery->status === DeliveryStatus::FINISHED;
    }

    /**
     * Determine if the user can add orders to the delivery.
     */
    public function addOrders(User $user, Delivery $delivery)
    {
        // Solo admin o administración, y solo si status == NOT_STARTED
        if (!in_array($user->role_id, [1, 3])) {
            return false;
        }
        return $delivery->status === DeliveryStatus::NOT_STARTED;
    }

    /**
     * Determine if the user can update an order in the delivery.
     */
    public function updateOrder(User $user, Delivery $delivery)
    {
        // Si IN_PROGRESS: repartidor (owner) puede actualizar sus pedidos
        if ($delivery->status === DeliveryStatus::IN_PROGRESS && $delivery->owner_user_id === $user->id) {
            return true;
        }
        // Admin/administración puede actualizar siempre
        return in_array($user->role_id, [1, 3]);
    }

    /**
     * Determine if the user can update expenses of the delivery.
     */
    public function updateExpenses(User $user, Delivery $delivery)
    {
        // Si IN_PROGRESS: repartidor (owner) puede actualizar gastos
        if ($delivery->status === DeliveryStatus::IN_PROGRESS && $delivery->owner_user_id === $user->id) {
            return true;
        }
        // Admin/administración puede actualizar siempre
        return in_array($user->role_id, [1, 3]);
    }
}
