<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\AdminStoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        protected AdminStoreContext $context,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $store = $this->context->selected($request->user());

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Select a store first.',
            ], 422);
        }

        $store->load('subscription.plan');

        return response()->json([
            'success' => true,
            'data' => [
                'store' => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'billing_status' => $store->billingStatus(),
                ],
                'subscription' => $store->subscription,
                'plans' => Plan::active()->orderBy('sort_order')->orderBy('price')->get(),
            ],
        ]);
    }

    /**
     * Merchant self-service: request a plan. Records a pending
     * subscription for the platform to activate (gateway charging is a
     * later step); never downgrades an entitled subscription.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $store = $this->context->selected($request->user());

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Select a store first.',
            ], 422);
        }

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::active()->find($validated['plan_id']);

        if (! $plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan is not available.',
            ], 422);
        }

        $store->load('subscription');

        if ($store->subscription && $store->subscription->isEntitled()) {
            return response()->json([
                'success' => false,
                'message' => 'Store already has an active subscription.',
            ], 422);
        }

        $subscription = Subscription::updateOrCreate(
            ['store_id' => $store->id],
            [
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_PENDING,
                'canceled_at' => null,
            ]
        );

        return response()->json(['success' => true, 'data' => $subscription->fresh('plan')], 201);
    }
}
