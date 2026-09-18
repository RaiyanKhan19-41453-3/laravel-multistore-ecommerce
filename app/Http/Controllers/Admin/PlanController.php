<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::orderBy('sort_order')->orderBy('name')->paginate(20);

        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:plans,slug',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'interval' => 'required|string|in:monthly,yearly,lifetime',
            'trial_days' => 'required|integer|min:0|max:365',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);
        $validated['currency'] = strtoupper($validated['currency']);

        $plan = Plan::create($validated);

        return response()->json(['success' => true, 'data' => $plan], 201);
    }

    public function show(Plan $plan): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $plan]);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'interval' => 'required|string|in:monthly,yearly,lifetime',
            'trial_days' => 'required|integer|min:0|max:365',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        if (array_key_exists('slug', $validated)) {
            $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);
        }

        if (array_key_exists('currency', $validated)) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        $plan->update($validated);

        return response()->json(['success' => true, 'data' => $plan->fresh()]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Plan has subscriptions and cannot be deleted.',
            ], 422);
        }

        $plan->delete();

        return response()->json(['success' => true, 'message' => 'Plan deleted.']);
    }
}
