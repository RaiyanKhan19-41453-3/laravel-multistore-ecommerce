<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminStoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreContextController extends Controller
{
    public function __construct(
        protected AdminStoreContext $context,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $selectedId = $this->context->selectedId($user);

        return response()->json([
            'success' => true,
            'data' => [
                'selected_id' => $selectedId,
                'is_explicit' => $selectedId !== null,
                'stores' => $this->context->availableStores($user)->map(fn ($store) => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'slug' => $store->slug,
                ])->all(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        if (! $this->context->select($request->user(), $validated['store_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot manage this store.',
            ], 403);
        }

        return $this->show($request);
    }

    public function destroy(Request $request): JsonResponse
    {
        if (! $this->context->select($request->user(), null)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot clear the store selection.',
            ], 403);
        }

        return $this->show($request);
    }
}
