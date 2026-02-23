<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MigrateDeliveryOrdersPaymentsData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::transaction(function () {
            DB::table('delivery_orders')
                ->orderBy('id')
                ->chunk(100, function ($orders) {
                    foreach ($orders as $order) {
                        // Si ya tiene pagos migrados, no hacer nada (idempotente)
                        $hasPayments = DB::table('delivery_order_payments')
                            ->where('delivery_order_id', $order->id)
                            ->exists();

                        if ($hasPayments) {
                            continue;
                        }

                        $paymentsToInsert = [];
                        $now = now();
                        $newCollectedAmount = (float) ($order->collected_amount ?? 0);

                        // Pago base usando los datos actuales del pivot
                        if ($order->collected_amount > 0 && !empty($order->payment_method)) {
                            $paymentsToInsert[] = [
                                'delivery_order_id' => $order->id,
                                'payment_method' => $order->payment_method,
                                'amount' => (float) $order->collected_amount,
                                'payment_reference' => $order->payment_reference,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }

                        // Caso especial conocido: referencia "100.000 mp" usada para Mercado Pago adicional
                        $reference = $order->payment_reference ? trim(mb_strtolower($order->payment_reference)) : null;
                        if ($reference === '100.000 mp') {
                            // Interpretamos 100.000 como 100000.00 (formato con punto como separador de miles)
                            $mpAmount = 100000.00;

                            $paymentsToInsert[] = [
                                'delivery_order_id' => $order->id,
                                'payment_method' => 'mercado_pago',
                                'amount' => $mpAmount,
                                'payment_reference' => $order->payment_reference,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];

                            // Actualizar el total cobrado para reflejar ambos medios de pago
                            $newCollectedAmount += $mpAmount;
                        }

                        if (!empty($paymentsToInsert)) {
                            DB::table('delivery_order_payments')->insert($paymentsToInsert);

                            // Si el total cambió (por el caso especial), actualizar el pivot
                            if ($newCollectedAmount !== (float) ($order->collected_amount ?? 0)) {
                                DB::table('delivery_orders')
                                    ->where('id', $order->id)
                                    ->update(['collected_amount' => $newCollectedAmount]);
                            }
                        }
                    }
                });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No se revierte la migración de datos para evitar pérdida de información.
    }
}

