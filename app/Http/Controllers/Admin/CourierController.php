<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Services\Couriers\CourierGatewayFactory;
use App\Services\Couriers\CourierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourierController extends Controller
{
    public function __construct(
        protected CourierService $courierService,
    ) {}

    public function index(): Response
    {
        $couriers = Courier::withCount('shipments')->orderBy('sort_order')->get()
            ->map(fn (Courier $courier) => array_merge($courier->toArray(), [
                'supports_api' => CourierGatewayFactory::supportsApi($courier->code),
                'settings_schema' => CourierGatewayFactory::getSettingsSchema($courier->code),
            ]));

        return Inertia::render('admin/couriers/index', [
            'couriers' => $couriers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:couriers,code',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        Courier::create($validated);

        return to_route('admin.couriers.index');
    }

    public function update(Request $request, Courier $courier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:couriers,code,'.$courier->id,
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $courier->update($validated);

        return to_route('admin.couriers.index');
    }

    public function updateSettings(Request $request, Courier $courier): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.client_id' => 'nullable|string|max:255',
            'settings.client_secret' => 'nullable|string|max:255',
            'settings.username' => 'nullable|string|max:255',
            'settings.password' => 'nullable|string|max:255',
            'settings.store_id' => 'nullable|string|max:255',
            'settings.api_token' => 'nullable|string|max:255',
            'settings.merchant_id' => 'nullable|string|max:255',
            'settings.api_key' => 'nullable|string|max:255',
            'settings.secret_key' => 'nullable|string|max:255',
            'settings.user_id' => 'nullable|string|max:255',
            'settings.booking_branch' => 'nullable|string|max:255',
            'settings.booking_user_id' => 'nullable|string|max:255',
            'settings.sandbox' => 'boolean',
        ]);

        $courier->update(['settings' => $validated['settings']]);

        return to_route('admin.couriers.index');
    }

    public function testConnection(Courier $courier): JsonResponse
    {
        try {
            $success = $this->courierService->testConnection($courier);

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

    public function destroy(Courier $courier): RedirectResponse
    {
        $courier->delete();

        return to_route('admin.couriers.index');
    }
}
