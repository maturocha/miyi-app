<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Helpers para los endpoints GET /x/options (datos de referencia livianos).
 * Formato: {"data":[{id,name,...}]} con ETag y Cache-Control de revalidación.
 */
trait RespondsWithOptions
{
    /** Segundos que se cachea en servidor la referencia (solo zonas/barrios/categorías/proveedores). */
    protected static $optionsTtl = 60;

    protected static function optionsCacheKey(string $name) : string
    {
        return 'ref:options:' . $name;
    }

    /** Invalida la caché de /options de las entidades indicadas. */
    protected static function forgetOptions(string ...$names) : void
    {
        foreach ($names as $name) {
            Cache::forget(self::optionsCacheKey($name));
        }
    }

    /** Ejecuta $callback (devuelve array) cacheado ≤ 60 s bajo la key de $name. */
    protected function rememberOptions(string $name, \Closure $callback) : array
    {
        return Cache::remember(self::optionsCacheKey($name), self::$optionsTtl, $callback);
    }

    /** Responde {"data":$rows} con ETag; 304 sin body si If-None-Match coincide. */
    protected function optionsResponse(Request $request, array $rows) : Response
    {
        $body = json_encode(['data' => $rows]);
        $etag = '"' . md5($body) . '"';

        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ];

        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch !== null) {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                $candidate = trim($candidate);
                if (strpos($candidate, 'W/') === 0) {
                    $candidate = substr($candidate, 2);
                }
                if ($candidate === $etag || $candidate === '*') {
                    return response('', 304, $headers);
                }
            }
        }

        return response($body, 200, $headers + ['Content-Type' => 'application/json']);
    }
}
