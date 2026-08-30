import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Package, Truck, XCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface OrderItem {
    id: number;
    name: string;
    sku: string;
    unit_price: string;
    quantity: number;
    subtotal: string;
    discount_amount: string;
    total: string;
    product: { id: number; name: string } | null;
    product_variant: { id: number; name: string } | null;
}

interface Payment {
    id: number;
    method: string;
    status: string;
    amount: string;
    gateway: string;
    gateway_transaction_id: string | null;
    paid_at: string | null;
    created_at: string;
}

interface Order {
    id: number;
    order_number: string;
    status: string;
    subtotal: string;
    discount_total: string;
    shipping_cost: string;
    tax_amount: string;
    total: string;
    shipping_name: string;
    shipping_phone: string;
    shipping_address: string;
    shipping_city: string;
    shipping_state: string;
    shipping_postal_code: string | null;
    shipping_country: string;
    notes: string | null;
    cancellation_reason: string | null;
    created_at: string;
    paid_at: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
    cancelled_at: string | null;
    items: OrderItem[];
    payments: Payment[];
    user: { id: number; name: string; email: string } | null;
    guest_email: string | null;
    guest_phone: string | null;
    coupon: { id: number; code: string; discount: { name: string } } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Orders', href: '/admin/orders' },
];

const STATUS_FLOW: Record<string, string[]> = {
    pending: ['confirmed', 'cancelled'],
    confirmed: ['processing', 'cancelled'],
    processing: ['shipped', 'cancelled'],
    shipped: ['delivered'],
    delivered: ['completed'],
};

function getStatusBadge(status: string) {
    const map: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
        pending: { label: 'Pending', variant: 'outline' },
        confirmed: { label: 'Confirmed', variant: 'default' },
        processing: { label: 'Processing', variant: 'default' },
        shipped: { label: 'Shipped', variant: 'default' },
        delivered: { label: 'Delivered', variant: 'default' },
        completed: { label: 'Completed', variant: 'default' },
        cancelled: { label: 'Cancelled', variant: 'destructive' },
        expired: { label: 'Expired', variant: 'secondary' },
    };
    return map[status] ?? { label: status, variant: 'secondary' as const };
}

export default function OrderShow({ order }: { order: Order }) {
    const [showStatusDialog, setShowStatusDialog] = useState(false);
    const [showCancelDialog, setShowCancelDialog] = useState(false);

    const { data: statusData, setData: setStatusData, post: postStatus, processing: statusProcessing } = useForm({
        status: '',
    });

    const { data: cancelData, setData: setCancelData, post: postCancel, processing: cancelProcessing } = useForm({
        cancellation_reason: '',
    });

    const handleStatusUpdate: FormEventHandler = (e) => {
        e.preventDefault();
        postStatus(route('admin.orders.update-status', order.id), {
            onSuccess: () => setShowStatusDialog(false),
        });
    };

    const handleCancel: FormEventHandler = (e) => {
        e.preventDefault();
        postCancel(route('admin.orders.cancel', order.id), {
            onSuccess: () => setShowCancelDialog(false),
        });
    };

    const nextStatuses = STATUS_FLOW[order.status] ?? [];
    const status = getStatusBadge(order.status);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Order ${order.order_number}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('admin.orders.index')}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{order.order_number}</h1>
                            <p className="text-sm text-neutral-500">
                                Placed on {new Date(order.created_at).toLocaleString()}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={status.variant}>{status.label}</Badge>
                        {nextStatuses.length > 0 && (
                            <Button
                                size="sm"
                                onClick={() => {
                                    setStatusData('status', nextStatuses[0]);
                                    setShowStatusDialog(true);
                                }}
                            >
                                <Truck className="mr-2 h-4 w-4" />
                                Advance Status
                            </Button>
                        )}
                        {['pending', 'confirmed'].includes(order.status) && (
                            <Button size="sm" variant="destructive" onClick={() => setShowCancelDialog(true)}>
                                <XCircle className="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2 space-y-6">
                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Order Items</h3>
                            </div>
                            <div className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                {order.items.map((item) => (
                                    <div key={item.id} className="flex items-center justify-between px-4 py-3">
                                        <div>
                                            <p className="font-medium">{item.name}</p>
                                            <p className="text-sm text-neutral-500">
                                                SKU: {item.sku} &middot; ৳{Number(item.unit_price).toLocaleString()} &times; {item.quantity}
                                            </p>
                                        </div>
                                        <p className="font-medium">৳{Number(item.total).toLocaleString()}</p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Shipping</h3>
                            </div>
                            <div className="px-4 py-3 space-y-1">
                                <p className="font-medium">{order.shipping_name}</p>
                                <p className="text-sm text-neutral-500">{order.shipping_phone}</p>
                                <p className="text-sm text-neutral-500">
                                    {order.shipping_address}, {order.shipping_city}, {order.shipping_state}
                                    {order.shipping_postal_code && ` ${order.shipping_postal_code}`}
                                </p>
                                <p className="text-sm text-neutral-500">{order.shipping_country}</p>
                                {order.notes && (
                                    <p className="mt-2 text-sm text-neutral-400 italic">Note: {order.notes}</p>
                                )}
                            </div>
                        </div>

                        {order.cancellation_reason && (
                            <div className="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                <h3 className="font-semibold text-red-700 dark:text-red-400">Cancellation Reason</h3>
                                <p className="mt-1 text-sm text-red-600 dark:text-red-300">{order.cancellation_reason}</p>
                            </div>
                        )}
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Summary</h3>
                            </div>
                            <div className="space-y-2 px-4 py-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-neutral-500">Subtotal</span>
                                    <span>৳{Number(order.subtotal).toLocaleString()}</span>
                                </div>
                                {Number(order.discount_total) > 0 && (
                                    <div className="flex justify-between text-emerald-600">
                                        <span>Discount</span>
                                        <span>-৳{Number(order.discount_total).toLocaleString()}</span>
                                    </div>
                                )}
                                {Number(order.shipping_cost) > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-neutral-500">Shipping</span>
                                        <span>৳{Number(order.shipping_cost).toLocaleString()}</span>
                                    </div>
                                )}
                                {Number(order.tax_amount) > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-neutral-500">Tax</span>
                                        <span>৳{Number(order.tax_amount).toLocaleString()}</span>
                                    </div>
                                )}
                                <div className="flex justify-between border-t border-neutral-200 pt-2 font-semibold dark:border-neutral-800">
                                    <span>Total</span>
                                    <span>৳{Number(order.total).toLocaleString()}</span>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Payment</h3>
                            </div>
                            <div className="px-4 py-3 space-y-2">
                                {order.payments.length > 0 ? (
                                    order.payments.map((payment) => (
                                        <div key={payment.id} className="text-sm">
                                            <div className="flex justify-between">
                                                <span className="capitalize">{payment.method}</span>
                                                <Badge variant={payment.status === 'paid' ? 'default' : 'secondary'}>
                                                    {payment.status}
                                                </Badge>
                                            </div>
                                            {payment.gateway_transaction_id && (
                                                <p className="mt-1 text-xs text-neutral-500">TXN: {payment.gateway_transaction_id}</p>
                                            )}
                                            {payment.paid_at && (
                                                <p className="text-xs text-neutral-500">Paid: {new Date(payment.paid_at).toLocaleString()}</p>
                                            )}
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-neutral-500">Cash on Delivery</p>
                                )}
                            </div>
                        </div>

                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Customer</h3>
                            </div>
                            <div className="px-4 py-3 space-y-1 text-sm">
                                <p className="font-medium">{order.user?.name ?? order.guest_email ?? 'Guest'}</p>
                                <p className="text-neutral-500">{order.user?.email ?? order.guest_email}</p>
                                {!order.user && order.guest_phone && (
                                    <p className="text-neutral-500">{order.guest_phone}</p>
                                )}
                            </div>
                        </div>

                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Timeline</h3>
                            </div>
                            <div className="space-y-3 px-4 py-3 text-sm">
                                <div className="flex items-center gap-3">
                                    <Package className="h-4 w-4 text-neutral-400" />
                                    <div>
                                        <p>Order placed</p>
                                        <p className="text-xs text-neutral-500">{new Date(order.created_at).toLocaleString()}</p>
                                    </div>
                                </div>
                                {order.paid_at && (
                                    <div className="flex items-center gap-3">
                                        <div className="h-4 w-4 rounded-full bg-emerald-500" />
                                        <div>
                                            <p>Payment confirmed</p>
                                            <p className="text-xs text-neutral-500">{new Date(order.paid_at).toLocaleString()}</p>
                                        </div>
                                    </div>
                                )}
                                {order.shipped_at && (
                                    <div className="flex items-center gap-3">
                                        <Truck className="h-4 w-4 text-blue-500" />
                                        <div>
                                            <p>Shipped</p>
                                            <p className="text-xs text-neutral-500">{new Date(order.shipped_at).toLocaleString()}</p>
                                        </div>
                                    </div>
                                )}
                                {order.delivered_at && (
                                    <div className="flex items-center gap-3">
                                        <div className="h-4 w-4 rounded-full bg-emerald-500" />
                                        <div>
                                            <p>Delivered</p>
                                            <p className="text-xs text-neutral-500">{new Date(order.delivered_at).toLocaleString()}</p>
                                        </div>
                                    </div>
                                )}
                                {order.cancelled_at && (
                                    <div className="flex items-center gap-3">
                                        <XCircle className="h-4 w-4 text-red-500" />
                                        <div>
                                            <p>Cancelled</p>
                                            <p className="text-xs text-neutral-500">{new Date(order.cancelled_at).toLocaleString()}</p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog open={showStatusDialog} onOpenChange={setShowStatusDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Update Order Status</DialogTitle>
                        <DialogDescription>
                            Move order {order.order_number} to the next status.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleStatusUpdate} className="space-y-4">
                        <div className="grid gap-2">
                            <Label>Status</Label>
                            <Select value={statusData.status} onValueChange={(v) => setStatusData('status', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {nextStatuses.map((s) => (
                                        <SelectItem key={s} value={s}>
                                            {s.charAt(0).toUpperCase() + s.slice(1)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowStatusDialog(false)}>
                                Cancel
                            </Button>
                            <Button disabled={statusProcessing}>Update Status</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={showCancelDialog} onOpenChange={setShowCancelDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel Order</DialogTitle>
                        <DialogDescription>
                            This will cancel order {order.order_number} and release reserved inventory.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCancel} className="space-y-4">
                        <div className="grid gap-2">
                            <Label>Reason (optional)</Label>
                            <Textarea
                                value={cancelData.cancellation_reason}
                                onChange={(e) => setCancelData('cancellation_reason', e.target.value)}
                                placeholder="Why is this order being cancelled?"
                                rows={3}
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowCancelDialog(false)}>
                                Keep Order
                            </Button>
                            <Button variant="destructive" disabled={cancelProcessing}>
                                Cancel Order
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
