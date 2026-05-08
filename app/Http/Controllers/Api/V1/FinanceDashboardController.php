<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FinanceDashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceDashboardController extends Controller
{
    /** @var FinanceDashboardService */
    protected $service;

    public function __construct(FinanceDashboardService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if (!$dateFrom || !$dateTo) {
            return response()->json(['message' => 'date_from and date_to are required'], 422);
        }

        $filters = [
            'date_from' => Carbon::parse($dateFrom)->format('Y-m-d'),
            'date_to' => Carbon::parse($dateTo)->format('Y-m-d'),
            'payment_method' => $request->input('payment_method'),
            'owner_user_id' => $request->filled('owner_user_id') ? (int) $request->input('owner_user_id') : null,
            'zone_id' => $request->filled('zone_id') ? (int) $request->input('zone_id') : null,
            'customer_id' => $request->filled('customer_id') ? (int) $request->input('customer_id') : null,
            'collection_status' => $request->input('collection_status'),
            'search' => $request->input('search'),
        ];

        return response()->json($this->service->dashboard($filters));
    }

}

