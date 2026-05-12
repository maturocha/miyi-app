<?php

namespace App\Support;

use App\Models\Product;

/**
 * Valida cantidad/peso de línea de pedido vs stock e intervalo de venta (MIY-6).
 * Alineado con validateProductQuantity en el dashboard.
 */
class OrderDetailQuantityValidator
{
    /**
     * @param float $amount Cantidad (tipo u) o peso (tipo w) usado para precio/stock
     * @return string|null mensaje de error o null si es válido
     */
    public static function validateAmountAgainstProduct(Product $product, $amount)
    {
        $amount = (float) $amount;
        if ($amount <= 0) {
            return 'La cantidad debe ser mayor a 0';
        }

        $interval = (float) $product->interval_quantity;
        if ($interval <= 0) {
            $interval = 1;
        }

        $ownProduct = (int) $product->own_product === 1;
        $stock = (float) $product->stock;
        $unitLabel = $product->type_product === 'w' ? 'kg' : 'unidades';

        if ($ownProduct) {
            if ($amount > $stock + self::epsilon($stock)) {
                return 'Stock insuficiente. Disponible: ' . self::formatNumber($stock) . ' ' . $unitLabel;
            }

            if ($stock > 0 && $stock < $interval) {
                if (self::almostEqual($amount, $stock)) {
                    return null;
                }

                return 'El stock remanente (' . self::formatNumber($stock) . ' ' . $unitLabel
                    . ') es menor al intervalo de venta (' . self::formatNumber($interval) . '). '
                    . 'Solo puede agregar el remanente disponible.';
            }
        }

        if (!self::isMultipleOfInterval($amount, $interval)) {
            return 'La cantidad debe ser múltiplo de ' . self::formatNumber($interval) . ' ' . $unitLabel
                . '. Cantidades válidas: ' . self::formatNumber($interval) . ', '
                . self::formatNumber($interval * 2) . ', ' . self::formatNumber($interval * 3) . ', etc.';
        }

        return null;
    }

    /**
     * @param float $a
     * @param float $b
     */
    private static function almostEqual($a, $b)
    {
        $scale = max(1.0, abs($a), abs($b));

        return abs($a - $b) <= self::epsilon($scale);
    }

    /**
     * @param float $scale
     */
    private static function epsilon($scale)
    {
        return 1e-9 * max(1.0, abs($scale));
    }

    /**
     * @param float $amount
     * @param float $interval
     */
    private static function isMultipleOfInterval($amount, $interval)
    {
        if ($interval <= 0) {
            return true;
        }

        $rest = fmod($amount, $interval);
        $eps = self::epsilon($interval);

        return $rest <= $eps || abs($rest - $interval) <= $eps;
    }

    /**
     * @param float $n
     */
    private static function formatNumber($n)
    {
        if (floor($n) == $n) {
            return (string) (int) $n;
        }

        return rtrim(rtrim(sprintf('%.4f', $n), '0'), '.');
    }
}
