<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CourierWebhookController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    public function handle(Request $request, string $courierCode): JsonResponse
    {
        $payload = $request->all();

        $courier = Courier::where('code', $courierCode)->first();

        if (! $courier) {
            Log::warning("{$courierCode} webhook: courier not found");

            return response()->json(['status' => 'ignored']);
        }

        $secret = $courier->settings['webhook_secret'] ?? null;

        if ($secret && ! hash_equals((string) $secret, (string) $request->header('X-Webhook-Secret'))) {
            Log::warning("{$courierCode} webhook: invalid signature");

            return response()->json(['status' => 'ignored'], 401);
        }

        Log::info("{$courierCode} webhook received");

        $trackingNumber = $this->extractTrackingNumber($payload, $courierCode);

        if (! $trackingNumber) {
            Log::warning("{$courierCode} webhook: no tracking number found");

            return response()->json(['status' => 'ignored']);
        }

        $shipment = Shipment::where('courier_order_id', $trackingNumber)
            ->orWhere('tracking_number', $trackingNumber)
            ->first();

        if (! $shipment) {
            Log::warning("{$courierCode} webhook: shipment not found", ['tracking_number' => $trackingNumber]);

            return response()->json(['status' => 'not_found']);
        }

        $rawStatus = $this->extractStatus($payload, $courierCode);

        if (! $rawStatus) {
            Log::warning("{$courierCode} webhook: no status found");

            return response()->json(['status' => 'ignored']);
        }

        $status = $this->mapStatus($rawStatus, $courierCode);

        if ($status) {
            $shipment->update([
                'status' => $status,
                'courier_response' => $payload,
            ]);

            $this->syncOrderStatus($shipment, $status);

            Log::info("{$courierCode} webhook: shipment updated", [
                'shipment_id' => $shipment->id,
                'status' => $status,
            ]);
        }

        return response()->json(['status' => 'processed']);
    }

    private function extractTrackingNumber(array $payload, string $courierCode): ?string
    {
        return match ($courierCode) {
            'redx' => $payload['tracking_number'] ?? null,
            'paperfly' => $payload['tracking_number'] ?? $payload['merchant_order_id'] ?? null,
            'steadfast' => $payload['consignment_id'] ?? $payload['invoice'] ?? null,
            'ecourier' => $payload['order_id'] ?? $payload['tracking_number'] ?? null,
            'sa_paribahan' => $payload['tracking_no'] ?? $payload['reference_no'] ?? null,
            'sundarban' => $payload['consignment_no'] ?? $payload['reference_no'] ?? null,
            default => $payload['tracking_number'] ?? null,
        };
    }

    private function extractStatus(array $payload, string $courierCode): ?string
    {
        return match ($courierCode) {
            'redx' => $payload['status'] ?? null,
            'paperfly' => $payload['status'] ?? null,
            'steadfast' => $payload['status'] ?? null,
            'ecourier' => $payload['status'] ?? null,
            'sa_paribahan' => $payload['status'] ?? null,
            'sundarban' => $payload['current_status'] ?? $payload['status'] ?? null,
            default => $payload['status'] ?? null,
        };
    }

    private function mapStatus(string $rawStatus, string $courierCode): ?string
    {
        $normalized = strtolower(trim($rawStatus));

        return match ($courierCode) {
            'redx' => match ($normalized) {
                'pickup-pending', 'pending' => 'pending',
                'ready-for-delivery' => 'picked',
                'delivery-in-progress' => 'out_for_delivery',
                'delivered' => 'delivered',
                'agent-hold' => 'pending',
                'agent-returning', 'returned' => 'returned',
                'agent-area-change' => 'in_transit',
                'paid' => null,
                default => null,
            },
            'paperfly' => match ($normalized) {
                'pending' => 'pending',
                'picked_up', 'picked' => 'picked',
                'in_transit' => 'in_transit',
                'partial_delivered' => 'out_for_delivery',
                'delivered' => 'delivered',
                'cancelled' => 'failed',
                'returned' => 'returned',
                default => null,
            },
            'steadfast' => match ($normalized) {
                'pending', 'in_review', 'unknown_approval_pending' => 'pending',
                'picked' => 'picked',
                'in_transit' => 'in_transit',
                'delivered_approval_pending', 'partial_delivered_approval_pending' => 'out_for_delivery',
                'delivered' => 'delivered',
                'cancelled', 'cancelled_approval_pending' => 'failed',
                'returned' => 'returned',
                default => null,
            },
            'ecourier' => match ($normalized) {
                'pending', 'initiated' => 'pending',
                'picked up', 'picked' => 'picked',
                'in transit', 'in source branch', 'in destination branch' => 'in_transit',
                'on the way to delivery' => 'out_for_delivery',
                'delivered' => 'delivered',
                'cancelled' => 'failed',
                'returned' => 'returned',
                default => null,
            },
            'sa_paribahan' => match ($normalized) {
                'booked', 'pending' => 'pending',
                'picked up' => 'picked',
                'in transit' => 'in_transit',
                'partial delivered' => 'out_for_delivery',
                'delivered' => 'delivered',
                'cancelled' => 'failed',
                'returned' => 'returned',
                default => null,
            },
            'sundarban' => match ($normalized) {
                'booked', 'pending' => 'pending',
                'picked up' => 'picked',
                'in transit', 'at hub' => 'in_transit',
                'partial delivered' => 'out_for_delivery',
                'delivered' => 'delivered',
                'cancelled' => 'failed',
                'returned' => 'returned',
                default => null,
            },
            default => null,
        };
    }

    private function syncOrderStatus(Shipment $shipment, string $status): void
    {
        $order = $shipment->order;

        if (! $order) {
            return;
        }

        match ($status) {
            'delivered' => $order->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]),
            'picked', 'in_transit', 'out_for_delivery' => $order->update([
                'status' => 'shipped',
                'shipped_at' => $order->shipped_at ?? now(),
            ]),
            'returned' => $this->handleReturned($order),
            default => null,
        };
    }

    private function handleReturned(Order $order): void
    {
        if ($order->status !== 'shipped') {
            return;
        }

        try {
            $this->orderService->cancel($order, 'Returned by courier.');
        } catch (\InvalidArgumentException $e) {
            Log::warning('Courier webhook: could not cancel returned order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
