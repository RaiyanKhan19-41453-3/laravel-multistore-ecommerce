import Heading from '@/components/heading';
import { MultiCombobox } from '@/components/ui/multi-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormEventHandler } from 'react';

interface SelectableItem {
    id: number;
    name: string;
    sku?: string;
}

interface SelectableVariant {
    id: number;
    name: string;
    sku?: string;
    product_id: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Discounts', href: '/admin/discounts' },
    { title: 'Create', href: '#' },
];

export default function DiscountCreate({
    products,
    categories,
    brands,
    variants,
}: {
    products: SelectableItem[];
    categories: SelectableItem[];
    brands: SelectableItem[];
    variants: SelectableVariant[];
}) {
    const { data, setData, post, errors, processing } = useForm({
        name: '',
        type: 'percentage',
        value: '',
        max_discount_amount: '',
        minimum_order_amount: '',
        starts_at: '',
        ends_at: '',
        usage_limit: '',
        is_active: true,
        priority: '0',
        stackable: false,
        product_ids: [] as string[],
        category_ids: [] as string[],
        brand_ids: [] as string[],
        variant_ids: [] as string[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.discounts.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Discount" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.discounts.index')} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                    <Heading title="Create Discount" description="Add a new discount or promotion" />
                </div>

                <form onSubmit={submit} className="max-w-3xl space-y-6">
                    <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h3 className="mb-4 text-lg font-semibold">Discount Rules</h3>
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required placeholder="e.g. Summer Sale" />
                                {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label>Type</Label>
                                    <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="percentage">Percentage (%)</SelectItem>
                                            <SelectItem value="fixed">Fixed Amount</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="value">{data.type === 'percentage' ? 'Percentage' : 'Amount'}</Label>
                                    <Input id="value" type="number" step="0.01" min="0" value={data.value} onChange={(e) => setData('value', e.target.value)} required placeholder="0" />
                                    {errors.value && <p className="text-sm text-red-500">{errors.value}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="max_discount_amount">Max Discount Amount</Label>
                                    <Input id="max_discount_amount" type="number" step="0.01" min="0" value={data.max_discount_amount} onChange={(e) => setData('max_discount_amount', e.target.value)} placeholder="No limit" />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="minimum_order_amount">Minimum Order Amount</Label>
                                    <Input id="minimum_order_amount" type="number" step="0.01" min="0" value={data.minimum_order_amount} onChange={(e) => setData('minimum_order_amount', e.target.value)} placeholder="No minimum" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="starts_at">Starts At</Label>
                                    <Input id="starts_at" type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ends_at">Ends At</Label>
                                    <Input id="ends_at" type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                                    {errors.ends_at && <p className="text-sm text-red-500">{errors.ends_at}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="usage_limit">Usage Limit</Label>
                                    <Input id="usage_limit" type="number" min="1" value={data.usage_limit} onChange={(e) => setData('usage_limit', e.target.value)} placeholder="Unlimited" className="w-48" />
                                </div>
                                <div className="flex items-end">
                                    <div className="flex items-center gap-2">
                                        <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                                        <Label htmlFor="is_active">Active</Label>
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                    <Label htmlFor="priority">Priority (higher wins)</Label>
                                    <Input id="priority" type="number" min="0" value={data.priority} onChange={(e) => setData('priority', e.target.value)} className="w-48" />
                                    <p className="text-xs text-neutral-500">When multiple discounts apply, the one with higher priority wins.</p>
                                </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h3 className="mb-4 text-lg font-semibold">Targets</h3>
                        <p className="mb-4 text-sm text-neutral-500">Leave all empty to apply discount to all products.</p>
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label>Products</Label>
                                <MultiCombobox
                                    options={products.map((p) => ({ value: p.id.toString(), label: p.sku ? `${p.name} (${p.sku})` : p.name }))}
                                    value={data.product_ids}
                                    onChange={(v) => setData('product_ids', v)}
                                    placeholder="All products"
                                    searchPlaceholder="Search products..."
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Variants</Label>
                                <MultiCombobox
                                    options={variants.map((v) => ({ value: v.id.toString(), label: `${v.product.name} — ${v.name}${v.sku ? ` (${v.sku})` : ''}` }))}
                                    value={data.variant_ids}
                                    onChange={(v) => setData('variant_ids', v)}
                                    placeholder="All variants"
                                    searchPlaceholder="Search variants..."
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Categories</Label>
                                <MultiCombobox
                                    options={categories.map((c) => ({ value: c.id.toString(), label: c.name }))}
                                    value={data.category_ids}
                                    onChange={(v) => setData('category_ids', v)}
                                    placeholder="All categories"
                                    searchPlaceholder="Search categories..."
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>Brands</Label>
                                <MultiCombobox
                                    options={brands.map((b) => ({ value: b.id.toString(), label: b.name }))}
                                    value={data.brand_ids}
                                    onChange={(v) => setData('brand_ids', v)}
                                    placeholder="All brands"
                                    searchPlaceholder="Search brands..."
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating...' : 'Create Discount'}
                        </Button>
                        <Link href={route('admin.discounts.index')}>
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
