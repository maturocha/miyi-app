<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Tamaño de página acotado: lee perPage (o per_page), lo castea a int y lo
     * limita a [1, $max]. Si falta o no es numérico, usa $default.
     */
    protected function perPage(Request $request, int $default = 40, int $max = 100): int
    {
        $value = $request->input('perPage', $request->input('per_page'));

        if (!is_numeric($value)) {
            return $default;
        }

        return min(max((int) $value, 1), $max);
    }

    /**
     * Columna de orden validada contra una whitelist (clave => columna SQL).
     * Si sortBy falta o no está permitido, devuelve $default.
     */
    protected function sortColumn(Request $request, array $allowed, string $default): string
    {
        $sortBy = $request->input('sortBy');

        if (is_string($sortBy) && isset($allowed[$sortBy])) {
            return $allowed[$sortBy];
        }

        return $default;
    }

    /**
     * Dirección de orden: asc/desc (case-insensitive); cualquier otro valor
     * devuelve $default.
     */
    protected function sortDirection(Request $request, string $default): string
    {
        $sortType = $request->input('sortType');

        if (is_string($sortType) && in_array(strtolower($sortType), ['asc', 'desc'], true)) {
            return strtoupper($sortType);
        }

        return $default;
    }
}
