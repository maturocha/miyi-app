<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HomeDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Página de Inicio: un solo endpoint con todas las secciones que el rol puede
 * ver. Cache corto por usuario (pedidos y repartos tienen scope por usuario).
 */
class HomeController extends Controller
{
    const CACHE_TTL_SECONDS = 60;

    /** @var HomeDashboardService */
    protected $service;

    public function __construct(HomeDashboardService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = Cache::remember('home:v1:user:' . $user->id, self::CACHE_TTL_SECONDS, function () use ($user) {
            return $this->service->summary($user);
        });

        return response()->json($data);
    }
}
