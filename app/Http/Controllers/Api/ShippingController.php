<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(
        private ShippingService $shippingService,
    ) {}

    public function cities(): JsonResponse
    {
        $cities = ShippingZone::active()
            ->whereNotNull('cities')
            ->get()
            ->pluck('cities')
            ->flatten()
            ->map(fn ($city) => trim($city))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $cities,
        ]);
    }

    public function rates(Request $request): JsonResponse
    {
        $request->validate([
            'city' => 'required|string|max:100',
            'country' => 'nullable|string|max:100',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $rates = $this->shippingService->getAvailableRates(
            $request->input('city'),
            (float) $request->input('subtotal'),
            $request->input('country', 'Bangladesh'),
        );

        return response()->json([
            'success' => true,
            'data' => $rates->values(),
        ]);
    }
}
