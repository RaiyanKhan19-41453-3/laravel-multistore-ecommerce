import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Trash2, Wand2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface AttributeValue {
    id: number;
    value: string;
    slug: string;
}

interface Attribute {
    id: number;
    name: string;
    slug: string;
    values: AttributeValue[];
}

interface VariantData {
    id?: number;
    name: string;
    sku: string;
    barcode: string;
    price: string;
    compare_at_price: string;
    cost_price: string;
    quantity: string;
    is_active: boolean;
    attribute_value_ids: number[];
    [key: string]: string | number | boolean | number[] | undefined;
}

interface Product {
    id: number;
    name: string;
    sku: string;
    type: string;
    price: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Products', href: '/admin/products' },
    { title: 'Variants', href: '#' },
];

export default function ProductVariants({
    product,
    variants,
    attributes,
}: {
    product: Product;
    variants: VariantData[];
    attributes: Attribute[];
}) {
    const [selectedAttributes, setSelectedAttributes] = useState<number[]>([]);
    const [showAddForm, setShowAddForm] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{
        variants: VariantData[];
    }>({
        variants: variants.map((v) => ({
            id: v.id,
            name: v.name,
            sku: v.sku,
            barcode: v.barcode ?? '',
            price: v.price,
            compare_at_price: v.compare_at_price ?? '',
            cost_price: v.cost_price ?? '',
            quantity: v.quantity,
            is_active: v.is_active,
            attribute_value_ids: v.attribute_value_ids,
        })),
    });

    const toggleAttribute = (id: number) => {
        setSelectedAttributes((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    };

    const generateVariants = () => {
        const attrs = attributes.filter((a) => selectedAttributes.includes(a.id));
        if (attrs.length === 0) return;

        const combinations: number[][] = [[]];
        for (const attr of attrs) {
            const next: number[][] = [];
            for (const combo of combinations) {
                for (const val of attr.values) {
                    next.push([...combo, val.id]);
                }
            }
            combinations.length = 0;
            combinations.push(...next);
        }

        const newVariants: VariantData[] = combinations.map((valueIds) => {
            const nameParts = attrs.map((attr) => {
                const val = attr.values.find((v) => valueIds.includes(v.id));
                return val?.value ?? '';
            });

            return {
                name: nameParts.join(' / '),
                sku: `${product.sku ? product.sku.toUpperCase().replace(/\s+/g, '-') : 'SKU'}-${valueIds.map((id) => {
                    for (const attr of attrs) {
                        const val = attr.values.find((v) => v.id === id);
                        if (val) return val.slug.toUpperCase().slice(0, 3);
                    }
                    return '';
                }).join('-')}`,
                barcode: '',
                price: product.price || '0',
                compare_at_price: '',
                cost_price: '',
                quantity: '0',
                is_active: true,
                attribute_value_ids: valueIds,
            };
        });

        setData('variants', [...data.variants, ...newVariants]);
    };

    const updateVariant = (index: number, field: keyof VariantData, value: string | number | boolean | number[]) => {
        const updated = [...data.variants];
        updated[index][field] = value;
        setData('variants', updated);
    };

    const removeVariant = (index: number) => {
        setData(
            'variants',
            data.variants.filter((_, i) => i !== index),
        );
    };

    const addNewVariant = () => {
        setData('variants', [
            ...data.variants,
            {
                name: '',
                sku: '',
                barcode: '',
                price: '0',
                compare_at_price: '',
                cost_price: '',
                quantity: '0',
                is_active: true,
                attribute_value_ids: [],
            },
        ]);
        setShowAddForm(true);
    };

    const handleSave: FormEventHandler = (e) => {
        e.preventDefault();

        if (data.variants.length === 0) {
            router.visit(route('admin.products.index'));
            return;
        }

        // Save all variants at once
        router.post(
            route('admin.products.variants.generate', product.id),
            {
                _method: 'PUT',
                variants: data.variants,
            },
            {
                preserveState: true,
                onSuccess: () => {
                    router.visit(route('admin.products.variants.index', product.id));
                },
            },
        );
    };

    const handleDeleteVariant = (variant: VariantData) => {
        if (!variant.id) return;
        if (confirm(`Delete variant "${variant.name}"?`)) {
            router.delete(route('admin.products.variants.destroy', [product.id, variant.id]), {
                preserveState: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Variants — ${product.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.products.index')}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{product.name}</h1>
                            <p className="text-sm text-neutral-500">
                                SKU: {product.sku} &middot; {data.variants.length} variant(s)
                            </p>
                        </div>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={route('admin.products.edit', product.id)}>Edit Product</Link>
                    </Button>
                </div>

                {attributes.length > 0 && (
                    <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="mb-4 flex items-center justify-between">
                            <div>
                                <h2 className="text-base font-semibold">Generate Variants</h2>
                                <p className="text-sm text-neutral-500">Select attributes and generate all combinations</p>
                            </div>
                        </div>

                        <div className="space-y-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                            {attributes.map((attr) => (
                                <label key={attr.id} className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                    <input
                                        type="checkbox"
                                        checked={selectedAttributes.includes(attr.id)}
                                        onChange={() => toggleAttribute(attr.id)}
                                        className="h-4 w-4 rounded border-neutral-300"
                                    />
                                    {attr.name}
                                    <span className="ml-auto text-xs text-neutral-400">{attr.values.length} values</span>
                                </label>
                            ))}
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={generateVariants}
                            disabled={selectedAttributes.length === 0}
                            className="mt-4"
                        >
                            <Wand2 className="mr-2 h-3.5 w-3.5" />
                            Generate Variants
                        </Button>
                    </div>
                )}

                <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="mb-4 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="flex h-6 w-6 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800">
                                <span className="text-xs font-medium">{data.variants.length}</span>
                            </div>
                            <h2 className="text-base font-semibold">Variants</h2>
                        </div>
                        <Button type="button" variant="outline" size="sm" onClick={addNewVariant}>
                            <Plus className="mr-1 h-3.5 w-3.5" />
                            Add Variant
                        </Button>
                    </div>

                    {data.variants.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-neutral-300 p-8 text-center dark:border-neutral-600">
                            <p className="text-sm text-neutral-500">No variants yet. Generate from attributes or add manually.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <table className="w-full text-sm">
                                <thead className="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-800/50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-neutral-500">Name</th>
                                        <th className="px-4 py-3 text-left font-medium text-neutral-500">SKU</th>
                                        <th className="px-4 py-3 text-left font-medium text-neutral-500">Barcode</th>
                                        <th className="px-4 py-3 text-left font-medium text-neutral-500">Price</th>
                                        <th className="px-4 py-3 text-left font-medium text-neutral-500">Compare At</th>
                                        <th className="px-4 py-3 text-left font-medium text-neutral-500">Cost</th>
                                        <th className="px-4 py-3 text-left font-medium text-neutral-500">Stock</th>
                                        <th className="px-4 py-3 text-left font-medium text-neutral-500">Active</th>
                                        <th className="px-4 py-3 w-12"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                    {data.variants.map((variant, index) => (
                                        <tr key={index} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                            <td className="px-4 py-3">
                                                <Input
                                                    value={variant.name}
                                                    onChange={(e) => updateVariant(index, 'name', e.target.value)}
                                                    className="h-9 text-sm"
                                                    placeholder="Variant name"
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <Input
                                                    value={variant.sku}
                                                    onChange={(e) => updateVariant(index, 'sku', e.target.value)}
                                                    className="h-9 w-32 text-sm"
                                                    placeholder="SKU"
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <Input
                                                    value={variant.barcode}
                                                    onChange={(e) => updateVariant(index, 'barcode', e.target.value)}
                                                    className="h-9 w-32 text-sm"
                                                    placeholder="Optional"
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    value={variant.price}
                                                    onChange={(e) => updateVariant(index, 'price', e.target.value)}
                                                    className="h-9 w-24 text-sm"
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    value={variant.compare_at_price}
                                                    onChange={(e) => updateVariant(index, 'compare_at_price', e.target.value)}
                                                    className="h-9 w-24 text-sm"
                                                    placeholder="0.00"
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    value={variant.cost_price}
                                                    onChange={(e) => updateVariant(index, 'cost_price', e.target.value)}
                                                    className="h-9 w-24 text-sm"
                                                    placeholder="0.00"
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <Input
                                                    type="number"
                                                    value={variant.quantity}
                                                    onChange={(e) => updateVariant(index, 'quantity', e.target.value)}
                                                    className="h-9 w-20 text-sm"
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <Switch
                                                    checked={variant.is_active}
                                                    onCheckedChange={(checked) => updateVariant(index, 'is_active', checked)}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1">
                                                    {variant.id && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDeleteVariant(variant)}
                                                            className="text-neutral-400 hover:text-red-500"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                    {!variant.id && (
                                                        <button
                                                            type="button"
                                                            onClick={() => removeVariant(index)}
                                                            className="text-neutral-400 hover:text-red-500"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <InputError message={errors.variants} className="mt-2" />
                </div>

                <div className="flex items-center gap-4 rounded-xl border border-neutral-200 bg-white px-6 py-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <Button onClick={handleSave} disabled={processing} size="lg">
                        <Save className="mr-2 h-4 w-4" />
                        Save Variants
                    </Button>
                    <Button type="button" variant="ghost" onClick={() => router.visit(route('admin.products.index'))}>
                        Cancel
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
