import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Discount {
    id: number;
    name: string;
    type: string;
    value: number;
    max_discount_amount: number | null;
    minimum_order_amount: number | null;
    starts_at: string | null;
    ends_at: string | null;
    usage_limit: number | null;
    usage_count: number;
    is_active: boolean;
    priority: number;
    stackable: boolean;
    products: { id: number; name: string }[];
    productVariants: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
    coupons: { id: number; code: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Discounts', href: '/admin/discounts' },
];

function DiscountForm({ discount, onClose }: { discount?: Discount | null; onClose: () => void }) {
    const isEdit = !!discount;

    const { data, setData, post, put, errors, processing } = useForm({
        name: discount?.name ?? '',
        type: discount?.type ?? 'percentage',
        value: discount?.value?.toString() ?? '',
        max_discount_amount: discount?.max_discount_amount?.toString() ?? '',
        minimum_order_amount: discount?.minimum_order_amount?.toString() ?? '',
        starts_at: discount?.starts_at ? discount.starts_at.slice(0, 16) : '',
        ends_at: discount?.ends_at ? discount.ends_at.slice(0, 16) : '',
        usage_limit: discount?.usage_limit?.toString() ?? '',
        is_active: discount?.is_active ?? true,
        priority: discount?.priority?.toString() ?? '0',
        stackable: discount?.stackable ?? false,
        product_ids: discount?.products?.map((p) => p.id) ?? [],
        category_ids: discount?.categories?.map((c) => c.id) ?? [],
        brand_ids: discount?.brands?.map((b) => b.id) ?? [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.discounts.update', discount.id), {
                onSuccess: () => onClose(),
            });
        } else {
            post(route('admin.discounts.store'), {
                onSuccess: () => onClose(),
            });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
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
                    <Label htmlFor="max_discount_amount">Max Discount Amount (optional)</Label>
                    <Input id="max_discount_amount" type="number" step="0.01" min="0" value={data.max_discount_amount} onChange={(e) => setData('max_discount_amount', e.target.value)} placeholder="No limit" />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="minimum_order_amount">Minimum Order Amount (optional)</Label>
                    <Input id="minimum_order_amount" type="number" step="0.01" min="0" value={data.minimum_order_amount} onChange={(e) => setData('minimum_order_amount', e.target.value)} placeholder="No minimum" />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="starts_at">Starts At (optional)</Label>
                    <Input id="starts_at" type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="ends_at">Ends At (optional)</Label>
                    <Input id="ends_at" type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                    {errors.ends_at && <p className="text-sm text-red-500">{errors.ends_at}</p>}
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="usage_limit">Usage Limit (optional)</Label>
                <Input id="usage_limit" type="number" min="1" value={data.usage_limit} onChange={(e) => setData('usage_limit', e.target.value)} placeholder="Unlimited" className="w-48" />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="priority">Priority</Label>
                <Input id="priority" type="number" min="0" value={data.priority} onChange={(e) => setData('priority', e.target.value)} className="w-48" />
                <p className="text-xs text-neutral-500">Higher wins when multiple discounts apply.</p>
            </div>

            <div className="flex items-center gap-2">
                <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                <Label htmlFor="is_active">Active</Label>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>
                    Cancel
                </Button>
                <Button disabled={processing}>{isEdit ? 'Update' : 'Create'}</Button>
            </DialogFooter>
        </form>
    );
}

export default function DiscountsIndex({ discounts }: { discounts: Discount[] }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editDiscount, setEditDiscount] = useState<Discount | null>(null);

    const handleDelete = (discount: Discount) => {
        if (confirm(`Delete "${discount.name}"?`)) {
            router.delete(route('admin.discounts.destroy', discount.id));
        }
    };

    const handleToggle = (discount: Discount) => {
        router.post(route('admin.discounts.toggle', discount.id));
    };

    const formatValue = (discount: Discount) => {
        return discount.type === 'percentage' ? `${discount.value}%` : `$${discount.value}`;
    };

    const getStatus = (discount: Discount) => {
        if (!discount.is_active) return { label: 'Inactive', variant: 'secondary' as const };
        if (discount.ends_at && new Date(discount.ends_at) < new Date()) return { label: 'Expired', variant: 'destructive' as const };
        if (discount.starts_at && new Date(discount.starts_at) > new Date()) return { label: 'Upcoming', variant: 'outline' as const };
        return { label: 'Active', variant: 'default' as const };
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Discounts" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Discounts" description="Manage discounts and promotions" />
                    <Button onClick={() => setShowCreate(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Discount
                    </Button>
                </div>

                <div className="border-sidebar-border/70 relative overflow-hidden rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Type</th>
                                <th className="px-4 py-3 font-medium">Value</th>
                                <th className="px-4 py-3 font-medium">Targets</th>
                                <th className="px-4 py-3 font-medium">Usage</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {discounts.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-neutral-500">
                                        No discounts yet. Create your first discount to get started.
                                    </td>
                                </tr>
                            ) : (
                                discounts.map((discount) => {
                                    const status = getStatus(discount);
                                    const targetCount = discount.products.length + (discount.productVariants?.length ?? 0) + discount.categories.length + discount.brands.length;
                                    return (
                                        <tr key={discount.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                            <td className="px-4 py-3">
                                                <Link href={route('admin.discounts.show', discount.id)} className="font-medium hover:underline">
                                                    {discount.name}
                                                </Link>
                                                {discount.coupons.length > 0 && (
                                                    <div className="mt-1 flex flex-wrap gap-1">
                                                        {discount.coupons.slice(0, 3).map((coupon) => (
                                                            <span key={coupon.id} className="inline-flex items-center rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-xs dark:bg-neutral-800">
                                                                {coupon.code}
                                                            </span>
                                                        ))}
                                                        {discount.coupons.length > 3 && (
                                                            <span className="text-xs text-neutral-500">+{discount.coupons.length - 3} more</span>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 capitalize">{discount.type}</td>
                                            <td className="px-4 py-3 font-medium">{formatValue(discount)}</td>
                                            <td className="px-4 py-3 text-neutral-500">
                                                {targetCount > 0 ? `${targetCount} target(s)` : 'All products'}
                                            </td>
                                            <td className="px-4 py-3 text-neutral-500">
                                                {discount.usage_count}
                                                {discount.usage_limit !== null && ` / ${discount.usage_limit}`}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={status.variant}>{status.label}</Badge>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1">
                                                    <Link
                                                        href={route('admin.products.index', { discount_id: discount.id })}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                    <Switch
                                                        checked={discount.is_active}
                                                        onCheckedChange={() => handleToggle(discount)}
                                                    />
                                                    <button
                                                        onClick={() => setEditDiscount(discount)}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDelete(discount)}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={showCreate} onOpenChange={setShowCreate}>
                <DialogContent className="max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Create Discount</DialogTitle>
                        <DialogDescription>Add a new discount or promotion.</DialogDescription>
                    </DialogHeader>
                    <DiscountForm onClose={() => setShowCreate(false)} />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editDiscount} onOpenChange={(open) => !open && setEditDiscount(null)}>
                <DialogContent className="max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Discount</DialogTitle>
                        <DialogDescription>Update discount details.</DialogDescription>
                    </DialogHeader>
                    {editDiscount && <DiscountForm discount={editDiscount} onClose={() => setEditDiscount(null)} />}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
