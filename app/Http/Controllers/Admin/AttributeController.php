<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller
{
    public function index(): Response
    {
        $attributes = Attribute::with(['values' => function ($query) {
            $query->orderBy('sort_order')->orderBy('value');
        }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/attributes/index', [
            'attributes' => $attributes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $storeId = app(CurrentStore::class)->scopeId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('attributes', 'slug')->where('store_id', $storeId)],
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);

        Attribute::create($validated);

        return to_route('admin.attributes.index');
    }

    public function update(Request $request, Attribute $attribute): RedirectResponse
    {
        $storeId = app(CurrentStore::class)->scopeId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('attributes', 'slug')->ignore($attribute->id)->where('store_id', $storeId)],
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);

        $attribute->update($validated);

        return to_route('admin.attributes.index');
    }

    public function toggle(Attribute $attribute): RedirectResponse
    {
        $attribute->update(['is_active' => ! $attribute->is_active]);

        return to_route('admin.attributes.index');
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        $attribute->delete();

        return to_route('admin.attributes.index');
    }

    public function storeValue(Request $request, Attribute $attribute): RedirectResponse
    {
        $validated = $request->validate([
            'value' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:attribute_values,slug,NULL,id,attribute_id,'.$attribute->id,
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['attribute_id'] = $attribute->id;
        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['value']);

        AttributeValue::create($validated);

        return to_route('admin.attributes.index');
    }

    public function updateValue(Request $request, Attribute $attribute, AttributeValue $value): RedirectResponse
    {
        if ($value->attribute_id !== $attribute->id) {
            abort(422, 'Value does not belong to this attribute.');
        }

        $validated = $request->validate([
            'value' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:attribute_values,slug,'.$value->id.',id,attribute_id,'.$attribute->id,
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['value']);

        $value->update($validated);

        return to_route('admin.attributes.index');
    }

    public function toggleValue(Attribute $attribute, AttributeValue $value): RedirectResponse
    {
        if ($value->attribute_id !== $attribute->id) {
            abort(422, 'Value does not belong to this attribute.');
        }

        $value->update(['is_active' => ! $value->is_active]);

        return to_route('admin.attributes.index');
    }

    public function destroyValue(Attribute $attribute, AttributeValue $value): RedirectResponse
    {
        if ($value->attribute_id !== $attribute->id) {
            abort(422, 'Value does not belong to this attribute.');
        }

        $value->delete();

        return to_route('admin.attributes.index');
    }
}
