<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Store;
use App\Scopes\BelongsToStore;
use App\Services\OrderService;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PathaoWebhookController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // The courier code is a global identifier: never scope it to the
        // resolved store, or webhooks for other stores are ignored.
        $secret = Courier::withoutGlobalScope(BelongsToStore::class)->where('code', 'pathao')->first()?->settings['webhook_secret'] ?? null;

        if ($secret && ! hash_equals((string) $secret, (string) $request->header('X-Webhook-Secret'))) {
            Log::warning('Pathao webhook: invalid signature');

            return response()->json(['status' => 'ignored'], 401);
        }

        Log::info('Pathao webhook received', [
            'event' => $payload['event'] ?? 'unknown',
            'consignment_id' => $payload['data']['consignment_id'] ?? null,
        ]);

        $consignmentId = $payload['data']['consignment_id'] ?? null;
        $event = $payload['event'] ?? null;

        if (! $consignmentId || ! $event) {
            return response()->json(['status' => 'ignored']);
        }

        $shipment = Shipment::withoutGlobalScope(BelongsToStore::class)
            ->where('courier_order_id', $consignmentId)->first();

        if (! $shipment) {
            Log::warning('Pathao webhook: shipment not found', ['consignment_id' => $consignmentId]);

            return response()->json(['status' => 'not_found']);
        }

        $status = $this->mapEventToStatus($event);

        if ($status) {
            $shipment->update([
                'status' => $status,
                'courier_response' => $payload,
            ]);

            $order = Order::withoutGlobalScope(BelongsToStore::class)->find($shipment->order_id);

            if (! $order) {
                return response()->json(['status' => 'processed']);
            }

            // Act in the order's store context from here on (see
            // CourierWebhookController::syncOrderStatus).
            app(CurrentStore::class)->set($order->store_id ? Store::find($order->store_id) : null);

            if ($status === 'delivered' || in_array($status, ['picked', 'in_transit', 'out_for_delivery'], true)) {
                try {
                    if ($status === 'delivered') {
                        $this->orderService->markDelivered($order);
                    } else {
                        $this->orderService->markShipped($order);
                    }
                } catch (\InvalidArgumentException $e) {
                    Log::warning('Pathao webhook: ignored out-of-state order sync', [
                        'order_id' => $order->id,
                        'shipment_id' => $shipment->id,
                        'from' => $order->status,
                        'to' => $status,
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif ($status === 'returned' && $order->status === 'shipped') {
                try {
                    $this->orderService->cancel($order, 'Returned by courier.');
                } catch (\InvalidArgumentException $e) {
                    Log::warning('Pathao webhook: could not cancel returned order', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Pathao webhook: shipment updated', [
                'shipment_id' => $shipment->id,
                'status' => $status,
            ]);
        }

        return response()->json(['status' => 'processed']);
    }

    private function mapEventToStatus(string $event): ?string
    {
        return match ($event) {
            'order.picked' => 'picked',
            'order.in-transit' => 'in_transit',
            'order.received-at-last-mile-hub' => 'in_transit',
            'order.assigned-for-delivery' => 'out_for_delivery',
            'order.delivered' => 'delivered',
            'order.delivery-failed' => 'failed',
            'order.returned' => 'returned',
            'order.cancelled' => 'failed',
            default => null,
        };
    }
}
