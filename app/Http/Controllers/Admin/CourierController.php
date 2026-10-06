<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Couriers\CourierGatewayFactory;
use App\Services\Couriers\CourierService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CourierController extends Controller
{
    public function __construct(
        protected CourierService $courierService,
    ) {}

    public function index(): Response
    {
        $shipmentsByCode = Shipment::query()
            ->whereNotNull('courier_code')
            ->selectRaw('courier_code, COUNT(*) as shipments_count')
            ->groupBy('courier_code')
            ->pluck('shipments_count', 'courier_code');

        $couriers = collect(CourierGatewayFactory::catalog())
            ->map(fn (array $courier) => $courier + [
                'shipments_count' => (int) $shipmentsByCode->get($courier['code'], 0),
            ]);

        return Inertia::render('admin/couriers/index', [
            'couriers' => $couriers,
        ]);
    }

    public function testConnection(string $courierCode): JsonResponse
    {
        if (! config("couriers.{$courierCode}")) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown courier.',
            ], 404);
        }

        try {
            $success = $this->courierService->testConnection($courierCode);

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Connection successful.' : 'Connection failed. Check your credentials.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
