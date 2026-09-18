<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformStoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Store::with(['subscription.plan', 'users']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $stores = $query->latest()->paginate(20)->withQueryString();

        $stores->getCollection()->transform(fn ($store) => $this->present($store));

        return response()->json(['success' => true, 'data' => $stores]);
    }

    public function show(Store $store): JsonResponse
    {
        $store->load(['subscription.plan', 'users']);

        return response()->json(['success' => true, 'data' => $this->present($store)]);
    }

    public function updateSubscription(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'nullable|exists:plans,id',
            'status' => ['required', 'string', Rule::in([
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_PAST_DUE,
                Subscription::STATUS_CANCELED,
                Subscription::STATUS_PENDING,
            ])],
            'trial_ends_at' => 'nullable|date',
            'current_period_started_at' => 'nullable|date',
            'current_period_ends_at' => 'nullable|date|after_or_equal:current_period_started_at',
            'gateway' => 'nullable|string|max:32',
            'gateway_subscription_id' => 'nullable|string|max:255',
        ]);

        $subscription = Subscription::updateOrCreate(
            ['store_id' => $store->id],
            [...$validated, 'canceled_at' => $validated['status'] === Subscription::STATUS_CANCELED ? now() : null]
        );

        return response()->json(['success' => true, 'data' => $subscription->fresh('plan')]);
    }

    public function toggle(Store $store): JsonResponse
    {
        $store->update(['is_active' => ! $store->is_active]);

        return response()->json(['success' => true, 'data' => $this->present($store->fresh('subscription.plan'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Store $store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'domain' => $store->domain,
            'country' => $store->country,
            'currency' => $store->currency,
            'is_active' => $store->is_active,
            'billing_status' => $store->billingStatus(),
            'subscription' => $store->subscription ? [
                'id' => $store->subscription->id,
                'status' => $store->subscription->status,
                'plan' => $store->subscription->plan ? [
                    'id' => $store->subscription->plan->id,
                    'name' => $store->subscription->plan->name,
                ] : null,
                'trial_ends_at' => $store->subscription->trial_ends_at,
                'current_period_ends_at' => $store->subscription->current_period_ends_at,
            ] : null,
            'members_count' => $store->relationLoaded('users') ? $store->users->count() : $store->users()->count(),
        ];
    }
}
