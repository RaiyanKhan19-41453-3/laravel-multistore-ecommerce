import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { router, useForm, Link } from '@inertiajs/react';
import { Package, Tag } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Category {
    id: number;
    name: string;
    parent_id: number | null;
}

interface Brand {
    id: number;
    name: string;
}

interface Attribute {
    id: number;
    name: string;
}

interface ProductData {
    id?: number;
    name: string;
    slug: string;
    brand_id: string;
    type: string;
    category_ids: number[];
    attribute_ids: number[];
    description: string;
    short_description: string;
    sku: string;
    barcode: string;
    price: string;
    compare_at_price: string;
    cost_price: string;
    quantity: string;
    is_active: boolean;
    is_featured: boolean;
    sort_order: string;
}

export default function ProductForm({
    product,
    brands,
    categories,
    attributes,
}: {
    product?: ProductData | null;
    brands: Brand[];
    categories: Category[];
    attributes: Attribute[];
}) {
    const isEdit = !!product;

    const { data, setData, post, put, errors, processing } = useForm({
        name: product?.name ?? '',
        slug: product?.slug ?? '',
        brand_id: product?.brand_id ?? '',
        type: product?.type ?? 'simple',
        category_ids: product?.category_ids ?? [],
        attribute_ids: product?.attribute_ids ?? [],
        description: product?.description ?? '',
        short_description: product?.short_description ?? '',
        sku: product?.sku ?? '',
        barcode: product?.barcode ?? '',
        price: product?.price ?? '',
        compare_at_price: product?.compare_at_price ?? '',
        cost_price: product?.cost_price ?? '',
        quantity: product?.quantity ?? '0',
        is_active: product?.is_active ?? true,
        is_featured: product?.is_featured ?? false,
        sort_order: product?.sort_order ?? '0',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.products.update', product.id));
        } else {
            post(route('admin.products.store'));
        }
    };

    const toggleCategory = (id: number) => {
        setData(
            'category_ids',
            data.category_ids.includes(id) ? data.category_ids.filter((i) => i !== id) : [...data.category_ids, id],
        );
    };

    const toggleAttribute = (id: number) => {
        const next = data.attribute_ids.includes(id) ? data.attribute_ids.filter((i) => i !== id) : [...data.attribute_ids, id];
        setData('attribute_ids', next);
    };

    const rootCategories = categories.filter((c) => c.parent_id === null);
    const childCategories = categories.filter((c) => c.parent_id !== null);
    const isVariable = data.type === 'variable';

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                {/* Main Content */}
                <div className="space-y-6">
                    <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="mb-6 flex items-center gap-2">
                            <Package className="h-5 w-5 text-neutral-500" />
                            <h2 className="text-base font-semibold">Product Information</h2>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required placeholder="Product name" className="mt-1.5" />
                                <InputError message={errors.name} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="slug">Slug</Label>
                                <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder="auto-generated" className="mt-1.5" />
                                <p className="mt-1 text-xs text-neutral-500">Leave blank to auto-generate.</p>
                                <InputError message={errors.slug} className="mt-1" />
                            </div>

                            <div>
                                <Label>Brand</Label>
                                <div className="mt-1.5">
                                    <Combobox
                                        options={brands.map((b) => ({ value: String(b.id), label: b.name }))}
                                        value={data.brand_id}
                                        onChange={(v) => setData('brand_id', v)}
                                        placeholder="No brand"
                                    />
                                </div>
                                <InputError message={errors.brand_id} className="mt-1" />
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="short_description">Short Description</Label>
                                <Input
                                    id="short_description"
                                    value={data.short_description}
                                    onChange={(e) => setData('short_description', e.target.value)}
                                    placeholder="Brief summary"
                                    className="mt-1.5"
                                />
                                <InputError message={errors.short_description} className="mt-1" />
                            </div>

                            <div className="sm:col-span-2">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={4}
                                    placeholder="Full product description"
                                    className="mt-1.5 flex min-h-[100px] w-full rounded-md border border-neutral-200 bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-neutral-500 focus:border-neutral-950 focus:outline-none focus:ring-1 focus:ring-neutral-950 dark:border-neutral-800 dark:placeholder:text-neutral-400 dark:focus:border-neutral-100 dark:focus:ring-neutral-100"
                                />
                                <InputError message={errors.description} className="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="mb-6 flex items-center gap-2">
                            <Tag className="h-5 w-5 text-neutral-500" />
                            <h2 className="text-base font-semibold">Pricing & Inventory</h2>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <Label htmlFor="price">Price</Label>
                                <Input
                                    id="price"
                                    type="number"
                                    step="0.01"
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    required={!isVariable}
                                    placeholder="0.00"
                                    className="mt-1.5"
                                />
                                <InputError message={errors.price} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="compare_at_price">Compare at Price</Label>
                                <Input
                                    id="compare_at_price"
                                    type="number"
                                    step="0.01"
                                    value={data.compare_at_price}
                                    onChange={(e) => setData('compare_at_price', e.target.value)}
                                    placeholder="0.00"
                                    className="mt-1.5"
                                />
                                <InputError message={errors.compare_at_price} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="cost_price">Cost Price</Label>
                                <Input
                                    id="cost_price"
                                    type="number"
                                    step="0.01"
                                    value={data.cost_price}
                                    onChange={(e) => setData('cost_price', e.target.value)}
                                    placeholder="0.00"
                                    className="mt-1.5"
                                />
                                <InputError message={errors.cost_price} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="sku">SKU</Label>
                                <Input id="sku" value={data.sku} onChange={(e) => setData('sku', e.target.value)} required placeholder="TSH-001" className="mt-1.5" />
                                <InputError message={errors.sku} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="barcode">Barcode</Label>
                                <Input id="barcode" value={data.barcode} onChange={(e) => setData('barcode', e.target.value)} placeholder="Optional" className="mt-1.5" />
                                <InputError message={errors.barcode} className="mt-1" />
                            </div>

                            <div>
                                <Label htmlFor="quantity">Quantity</Label>
                                <Input
                                    id="quantity"
                                    type="number"
                                    value={data.quantity}
                                    onChange={(e) => setData('quantity', e.target.value)}
                                    min={0}
                                    className="mt-1.5"
                                />
                                <InputError message={errors.quantity} className="mt-1" />
                            </div>
                        </div>
                    </div>

                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    <div className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h3 className="mb-4 text-sm font-semibold">Product Type</h3>
                        <div className="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                onClick={() => setData('type', 'simple')}
                                className={`rounded-lg border-2 px-4 py-3 text-sm font-medium transition ${
                                    !isVariable
                                        ? 'border-neutral-900 bg-neutral-900 text-white dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900'
                                        : 'border-neutral-200 bg-white text-neutral-600 hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400 dark:hover:border-neutral-600'
                                }`}
                            >
                                Simple
                            </button>
                            <button
                                type="button"
                                onClick={() => setData('type', 'variable')}
                                className={`rounded-lg border-2 px-4 py-3 text-sm font-medium transition ${
                                    isVariable
                                        ? 'border-neutral-900 bg-neutral-900 text-white dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900'
                                        : 'border-neutral-200 bg-white text-neutral-600 hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400 dark:hover:border-neutral-600'
                                }`}
                            >
                                Variable
                            </button>
                        </div>
                        <InputError message={errors.type} className="mt-2" />
                        <p className="mt-3 text-xs text-neutral-500">
                            {isVariable ? 'Create variants with different prices and stock.' : 'Single SKU with fixed price.'}
                        </p>
                    </div>

                    <div className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h3 className="mb-4 text-sm font-semibold">Categories</h3>
                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                            {rootCategories.map((cat) => (
                                <div key={cat.id}>
                                    <label className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                        <input
                                            type="checkbox"
                                            checked={data.category_ids.includes(cat.id)}
                                            onChange={() => toggleCategory(cat.id)}
                                            className="h-4 w-4 rounded border-neutral-300"
                                        />
                                        {cat.name}
                                    </label>
                                    {childCategories
                                        .filter((c) => c.parent_id === cat.id)
                                        .map((child) => (
                                            <label key={child.id} className="ml-5 flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                                <input
                                                    type="checkbox"
                                                    checked={data.category_ids.includes(child.id)}
                                                    onChange={() => toggleCategory(child.id)}
                                                    className="h-4 w-4 rounded border-neutral-300"
                                                />
                                                {child.name}
                                            </label>
                                        ))}
                                </div>
                            ))}
                        </div>
                        <InputError message={errors.category_ids} className="mt-2" />
                    </div>

                    {isVariable && (
                        <div className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                            <h3 className="mb-4 text-sm font-semibold">Attributes</h3>
                            <p className="mb-3 text-xs text-neutral-500">Select attributes for this product. Variants are managed separately.</p>
                            <div className="space-y-1 rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                                {attributes.map((attr) => (
                                    <label key={attr.id} className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                        <input
                                            type="checkbox"
                                            checked={data.attribute_ids.includes(attr.id)}
                                            onChange={() => toggleAttribute(attr.id)}
                                            className="h-4 w-4 rounded border-neutral-300"
                                        />
                                        {attr.name}
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.attribute_ids} className="mt-2" />
                            {data.attribute_ids.length > 0 && (
                                <Button asChild variant="link" size="sm" className="mt-3 h-auto p-0">
                                    <Link href={route('admin.products.variants.index', product?.id ?? 0)}>
                                        Manage variants →
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}

                    <div className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h3 className="mb-4 text-sm font-semibold">Visibility</h3>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="is_active" className="text-sm font-normal">Active</Label>
                                <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                            </div>
                            <div className="flex items-center justify-between">
                                <Label htmlFor="is_featured" className="text-sm font-normal">Featured</Label>
                                <Switch id="is_featured" checked={data.is_featured} onCheckedChange={(checked) => setData('is_featured', checked)} />
                            </div>
                            <div className="pt-2">
                                <Label htmlFor="sort_order" className="text-sm">Sort Order</Label>
                                <Input
                                    id="sort_order"
                                    type="number"
                                    value={data.sort_order}
                                    onChange={(e) => setData('sort_order', e.target.value)}
                                    min={0}
                                    className="mt-1.5 w-full"
                                />
                            </div>
                        </div>
                        <InputError message={errors.sort_order} className="mt-1" />
                    </div>
                </div>
            </div>

            <div className="flex items-center gap-4 rounded-xl border border-neutral-200 bg-white px-6 py-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <Button disabled={processing} size="lg">
                    {isEdit ? 'Update Product' : 'Create Product'}
                </Button>
                <Button type="button" variant="ghost" onClick={() => router.visit(route('admin.products.index'))}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
