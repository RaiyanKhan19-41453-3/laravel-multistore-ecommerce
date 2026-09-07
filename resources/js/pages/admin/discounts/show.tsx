import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Coupon {
    id: number;
    code: string;
    usage_limit: number | null;
    usage_count: number;
    per_user_limit: number | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
}

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
    productVariants: { id: number; name: string; product: { id: number; name: string } }[];
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
    coupons: Coupon[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Discounts', href: '/admin/discounts' },
    { title: 'Details', href: '#' },
];

function CouponForm({ discount, coupon, onClose }: { discount: Discount; coupon?: Coupon | null; onClose: () => void }) {
    const isEdit = !!coupon;

    const { data, setData, post, put, errors, processing } = useForm({
        code: coupon?.code ?? '',
        usage_limit: coupon?.usage_limit?.toString() ?? '',
        per_user_limit: coupon ? (coupon.per_user_limit?.toString() ?? '') : '1',
        starts_at: coupon?.starts_at ? coupon.starts_at.slice(0, 16) : '',
        ends_at: coupon?.ends_at ? coupon.ends_at.slice(0, 16) : '',
        is_active: coupon?.is_active ?? true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.discounts.coupons.update', [discount.id, coupon.id]), {
                onSuccess: () => onClose(),
            });
        } else {
            post(route('admin.discounts.coupons.store', discount.id), {
                onSuccess: () => onClose(),
            });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="code">Coupon Code</Label>
                <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value.toUpperCase())} required placeholder="e.g. WELCOME500" />
                {errors.code && <p className="text-sm text-red-500">{errors.code}</p>}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="usage_limit">Usage Limit (optional)</Label>
                <Input id="usage_limit" type="number" min="1" value={data.usage_limit} onChange={(e) => setData('usage_limit', e.target.value)} placeholder="Unlimited" className="w-48" />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="per_user_limit">Per-Customer Limit (optional)</Label>
                <Input id="per_user_limit" type="number" min="1" value={data.per_user_limit} onChange={(e) => setData('per_user_limit', e.target.value)} placeholder="Blank = unlimited" className="w-48" />
                {errors.per_user_limit && <p className="text-sm text-red-500">{errors.per_user_limit}</p>}
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="starts_at">Starts At (optional)</Label>
                    <Input id="starts_at" type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="ends_at">Ends At (optional)</Label>
                    <Input id="ends_at" type="datetime-local" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                </div>
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

export default function DiscountShow({ discount }: { discount: Discount }) {
    const [showCreateCoupon, setShowCreateCoupon] = useState(false);
    const [editCoupon, setEditCoupon] = useState<Coupon | null>(null);

    const handleDeleteCoupon = (coupon: Coupon) => {
        if (confirm(`Delete coupon "${coupon.code}"?`)) {
            router.delete(route('admin.discounts.coupons.destroy', [discount.id, coupon.id]));
        }
    };

    const handleToggleCoupon = (coupon: Coupon) => {
        router.post(route('admin.discounts.coupons.toggle', [discount.id, coupon.id]));
    };

    const formatValue = () => {
        return discount.type === 'percentage' ? `${discount.value}%` : `$${discount.value}`;
    };

    const getStatus = () => {
        if (!discount.is_active) return { label: 'Inactive', variant: 'secondary' as const };
        if (discount.ends_at && new Date(discount.ends_at) < new Date()) return { label: 'Expired', variant: 'destructive' as const };
        if (discount.starts_at && new Date(discount.starts_at) > new Date()) return { label: 'Upcoming', variant: 'outline' as const };
        return { label: 'Active', variant: 'default' as const };
    };

    const status = getStatus();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={discount.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link href={route('admin.discounts.index')} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <Heading title={discount.name} description={`${formatValue()} discount`} />
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={status.variant}>{status.label}</Badge>
                        <Link href={route('admin.discounts.edit', discount.id)}>
                            <Button variant="outline" size="sm">
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <h3 className="mb-4 text-lg font-semibold">Discount Details</h3>
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Type</dt>
                                <dd className="capitalize">{discount.type}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Value</dt>
                                <dd className="font-medium">{formatValue()}</dd>
                            </div>
                            {discount.max_discount_amount && (
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">Max Discount</dt>
                                    <dd>${discount.max_discount_amount}</dd>
                                </div>
                            )}
                            {discount.minimum_order_amount && (
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">Min Order</dt>
                                    <dd>${discount.minimum_order_amount}</dd>
                                </div>
                            )}
                            {discount.starts_at && (
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">Starts</dt>
                                    <dd>{new Date(discount.starts_at).toLocaleDateString()}</dd>
                                </div>
                            )}
                            {discount.ends_at && (
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">Ends</dt>
                                    <dd>{new Date(discount.ends_at).toLocaleDateString()}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Usage</dt>
                                <dd>{discount.usage_count}{discount.usage_limit !== null ? ` / ${discount.usage_limit}` : ''}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Priority</dt>
                                <dd>{discount.priority}</dd>
                            </div>
                        </dl>
                    </div>

                    <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold">Targets</h3>
                            <Link href={route('admin.products.index', { discount_id: discount.id })}>
                                <Button variant="outline" size="sm">View Affected Products</Button>
                            </Link>
                        </div>
                        {(discount.products.length === 0 && (discount.productVariants?.length ?? 0) === 0 && discount.categories.length === 0 && discount.brands.length === 0) ? (
                            <p className="text-sm text-neutral-500">Applies to all products.</p>
                        ) : (
                            <div className="space-y-3">
                                {discount.products.length > 0 && (
                                    <div>
                                        <p className="mb-1 text-xs font-medium uppercase text-neutral-500">Products</p>
                                        <div className="flex flex-wrap gap-1">
                                            {discount.products.map((p) => (
                                                <Link key={p.id} href={route('admin.products.show', p.id)}>
                                                    <Badge variant="secondary" className="cursor-pointer hover:bg-neutral-200 dark:hover:bg-neutral-700">{p.name}</Badge>
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {(discount.productVariants?.length ?? 0) > 0 && (
                                    <div>
                                        <p className="mb-1 text-xs font-medium uppercase text-neutral-500">Variants</p>
                                        <div className="flex flex-wrap gap-1">
                                            {discount.productVariants?.map((v) => (
                                                <Badge key={v.id} variant="secondary">{v.product.name} — {v.name}</Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {discount.categories.length > 0 && (
                                    <div>
                                        <p className="mb-1 text-xs font-medium uppercase text-neutral-500">Categories</p>
                                        <div className="flex flex-wrap gap-1">
                                            {discount.categories.map((c) => (
                                                <Badge key={c.id} variant="secondary">{c.name}</Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {discount.brands.length > 0 && (
                                    <div>
                                        <p className="mb-1 text-xs font-medium uppercase text-neutral-500">Brands</p>
                                        <div className="flex flex-wrap gap-1">
                                            {discount.brands.map((b) => (
                                                <Badge key={b.id} variant="secondary">{b.name}</Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-800">
                        <h3 className="text-lg font-semibold">Coupons</h3>
                        <Button size="sm" onClick={() => setShowCreateCoupon(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Coupon
                        </Button>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-800/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Code</th>
                                <th className="px-4 py-3 text-left font-medium">Usage</th>
                                <th className="px-4 py-3 text-left font-medium">Per Customer</th>
                                <th className="px-4 py-3 text-left font-medium">Valid Period</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                            {discount.coupons.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                        No coupons yet. Add a coupon code for this discount.
                                    </td>
                                </tr>
                            ) : (
                                discount.coupons.map((coupon) => (
                                    <tr key={coupon.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                        <td className="px-4 py-3 font-mono font-medium">{coupon.code}</td>
                                        <td className="px-4 py-3 text-neutral-500">
                                            {coupon.usage_count}{coupon.usage_limit !== null ? ` / ${coupon.usage_limit}` : ''}
                                        </td>
                                        <td className="px-4 py-3 text-neutral-500">
                                            {coupon.per_user_limit !== null ? `${coupon.per_user_limit}×` : 'Unlimited'}
                                        </td>
                                        <td className="px-4 py-3 text-neutral-500">
                                            {coupon.starts_at ? new Date(coupon.starts_at).toLocaleDateString() : '—'}
                                            {' to '}
                                            {coupon.ends_at ? new Date(coupon.ends_at).toLocaleDateString() : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant={coupon.is_active ? 'default' : 'secondary'}>
                                                {coupon.is_active ? 'Active' : 'Inactive'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Switch
                                                    checked={coupon.is_active}
                                                    onCheckedChange={() => handleToggleCoupon(coupon)}
                                                />
                                                <button
                                                    onClick={() => setEditCoupon(coupon)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => handleDeleteCoupon(coupon)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={showCreateCoupon} onOpenChange={setShowCreateCoupon}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Coupon</DialogTitle>
                        <DialogDescription>Create a coupon code for this discount.</DialogDescription>
                    </DialogHeader>
                    <CouponForm discount={discount} onClose={() => setShowCreateCoupon(false)} />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editCoupon} onOpenChange={(open) => !open && setEditCoupon(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Coupon</DialogTitle>
                        <DialogDescription>Update coupon details.</DialogDescription>
                    </DialogHeader>
                    {editCoupon && <CouponForm discount={discount} coupon={editCoupon} onClose={() => setEditCoupon(null)} />}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
