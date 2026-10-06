import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatPrice } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileText, Package, Pencil, Printer, Truck, XCircle } from 'lucide-react';
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

interface Shipment {
    id: number;
    courier: string | null;
    tracking_number: string | null;
    status: string;
    note: string | null;
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
    shipments: Shipment[];
    user: { id: number; name: string; email: string } | null;
    guest_email: string | null;
    guest_phone: string | null;
    coupon: { id: number; code: string; discount: { name: string } } | null;
}

interface Courier {
    code: string;
    name: string;
    enabled: boolean;
    configured: boolean;
    supports_api: boolean;
    shipments_count: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Orders', href: '/admin/orders' },
];

const STATUS_FLOW: Record<string, string[]> = {
    pending: ['confirmed', 'cancelled', 'expired'],
    confirmed: ['processing', 'cancelled'],
    processing: ['shipped', 'cancelled'],
    shipped: ['delivered', 'cancelled'],
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

export default function OrderShow({ order, couriers }: { order: Order; couriers: Courier[] }) {
    const [showStatusDialog, setShowStatusDialog] = useState(false);
    const [showCancelDialog, setShowCancelDialog] = useState(false);

    const {
        data: statusData,
        setData: setStatusData,
        post: postStatus,
        processing: statusProcessing,
    } = useForm({
        status: '',
    });

    const {
        data: cancelData,
        setData: setCancelData,
        post: postCancel,
        processing: cancelProcessing,
    } = useForm({
        cancellation_reason: '',
    });

    const [showShipmentDialog, setShowShipmentDialog] = useState(false);
    const {
        data: shipmentData,
        setData: setShipmentData,
        post: postShipment,
        processing: shipmentProcessing,
        reset: resetShipment,
    } = useForm({
        courier_code: '',
        tracking_number: '',
        note: '',
    });

    const [editShipment, setEditShipment] = useState<Shipment | null>(null);
    const {
        data: updateShipmentData,
        setData: setUpdateShipmentData,
        put: putShipment,
        processing: updateShipmentProcessing,
    } = useForm({
        status: '',
        tracking_number: '',
        note: '',
    });

    const [showSendToCourierDialog, setShowSendToCourierDialog] = useState(false);
    const {
        data: sendCourierData,
        setData: setSendCourierData,
        post: postSendCourier,
        processing: sendCourierProcessing,
        errors: sendCourierErrors,
    } = useForm({
        courier_code: '',
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

    const handleAddShipment: FormEventHandler = (e) => {
        e.preventDefault();
        postShipment(route('admin.orders.shipments.store', order.id), {
            onSuccess: () => {
                setShowShipmentDialog(false);
                resetShipment();
            },
        });
    };

    const handleUpdateShipment: FormEventHandler = (e) => {
        e.preventDefault();
        if (!editShipment) return;
        putShipment(route('admin.orders.shipments.update', [order.id, editShipment.id]), {
            onSuccess: () => setEditShipment(null),
        });
    };

    const handleSendToCourier: FormEventHandler = (e) => {
        e.preventDefault();
        postSendCourier(route('admin.orders.send-to-courier', order.id), {
            onSuccess: () => setShowSendToCourierDialog(false),
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
                            <p className="text-sm text-neutral-500">Placed on {new Date(order.created_at).toLocaleString()}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={status.variant}>{status.label}</Badge>
                        <Link
                            href={route('admin.invoices.show', order.id)}
                            className="hover:bg-accent inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
                        >
                            <FileText className="h-4 w-4" /> Invoice
                        </Link>
                        <Link
                            href={route('admin.invoices.print', order.id)}
                            className="hover:bg-accent inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm"
                        >
                            <Printer className="h-4 w-4" /> Print
                        </Link>
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
                        {['pending', 'confirmed', 'processing', 'shipped'].includes(order.status) && (
                            <Button size="sm" variant="destructive" onClick={() => setShowCancelDialog(true)}>
                                <XCircle className="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
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
                                                SKU: {item.sku} &middot; {formatPrice(item.unit_price)} &times; {item.quantity}
                                            </p>
                                        </div>
                                        <p className="font-medium">{formatPrice(item.total)}</p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Shipping</h3>
                            </div>
                            <div className="space-y-1 px-4 py-3">
                                <p className="font-medium">{order.shipping_name}</p>
                                <p className="text-sm text-neutral-500">{order.shipping_phone}</p>
                                <p className="text-sm text-neutral-500">
                                    {order.shipping_address}, {order.shipping_city}, {order.shipping_state}
                                    {order.shipping_postal_code && ` ${order.shipping_postal_code}`}
                                </p>
                                <p className="text-sm text-neutral-500">{order.shipping_country}</p>
                                {order.notes && <p className="mt-2 text-sm text-neutral-400 italic">Note: {order.notes}</p>}
                            </div>
                        </div>

                        {order.cancellation_reason && (
                            <div className="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                <h3 className="font-semibold text-red-700 dark:text-red-400">Cancellation Reason</h3>
                                <p className="mt-1 text-sm text-red-600 dark:text-red-300">{order.cancellation_reason}</p>
                            </div>
                        )}

                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Fulfillment</h3>
                                {['confirmed', 'processing'].includes(order.status) && (
                                    <div className="flex items-center gap-2">
                                        <Button size="sm" variant="outline" onClick={() => setShowSendToCourierDialog(true)}>
                                            Send to Courier
                                        </Button>
                                        <Button size="sm" onClick={() => setShowShipmentDialog(true)}>
                                            Add Tracking
                                        </Button>
                                    </div>
                                )}
                            </div>
                            <div className="px-4 py-3">
                                {order.shipments.length === 0 ? (
                                    <p className="text-sm text-neutral-500">
                                        No shipments yet. Add tracking info when you send this order to a courier.
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {order.shipments.map((shipment) => (
                                            <div key={shipment.id} className="rounded-lg border border-neutral-100 p-3 dark:border-neutral-800">
                                                <div className="flex items-center justify-between">
                                                    <div>
                                                        <p className="font-medium">{shipment.courier}</p>
                                                        <p className="text-sm text-neutral-500">Tracking: {shipment.tracking_number}</p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant={shipment.status === 'delivered' ? 'default' : 'secondary'}>
                                                            {shipment.status.replace('_', ' ')}
                                                        </Badge>
                                                        <button
                                                            onClick={() => {
                                                                setEditShipment(shipment);
                                                                setUpdateShipmentData('status', shipment.status);
                                                                setUpdateShipmentData('tracking_number', shipment.tracking_number ?? '');
                                                                setUpdateShipmentData('note', shipment.note ?? '');
                                                            }}
                                                            className="inline-flex h-7 w-7 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                        >
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                </div>
                                                {shipment.note && <p className="mt-1 text-xs text-neutral-400 italic">{shipment.note}</p>}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Summary</h3>
                            </div>
                            <div className="space-y-2 px-4 py-3 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-neutral-500">Subtotal</span>
                                    <span>{formatPrice(order.subtotal)}</span>
                                </div>
                                {Number(order.discount_total) > 0 && (
                                    <div className="flex justify-between text-emerald-600">
                                        <span>Discount</span>
                                        <span>-{formatPrice(order.discount_total)}</span>
                                    </div>
                                )}
                                {Number(order.shipping_cost) > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-neutral-500">Shipping</span>
                                        <span>{formatPrice(order.shipping_cost)}</span>
                                    </div>
                                )}
                                {Number(order.tax_amount) > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-neutral-500">Tax</span>
                                        <span>{formatPrice(order.tax_amount)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between border-t border-neutral-200 pt-2 font-semibold dark:border-neutral-800">
                                    <span>Total</span>
                                    <span>{formatPrice(order.total)}</span>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="border-b border-neutral-200 px-4 py-3 dark:border-neutral-800">
                                <h3 className="font-semibold">Payment</h3>
                            </div>
                            <div className="space-y-2 px-4 py-3">
                                {order.payments.length > 0 ? (
                                    order.payments.map((payment) => (
                                        <div key={payment.id} className="text-sm">
                                            <div className="flex justify-between">
                                                <span className="capitalize">{payment.method}</span>
                                                <Badge variant={payment.status === 'paid' ? 'default' : 'secondary'}>{payment.status}</Badge>
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
                            <div className="space-y-1 px-4 py-3 text-sm">
                                <p className="font-medium">{order.user?.name ?? order.guest_email ?? 'Guest'}</p>
                                <p className="text-neutral-500">{order.user?.email ?? order.guest_email}</p>
                                {!order.user && order.guest_phone && <p className="text-neutral-500">{order.guest_phone}</p>}
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
                        <DialogDescription>Move order {order.order_number} to the next status.</DialogDescription>
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
                        <DialogDescription>This will cancel order {order.order_number} and release reserved inventory.</DialogDescription>
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

            <Dialog open={showShipmentDialog} onOpenChange={setShowShipmentDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Shipment Tracking</DialogTitle>
                        <DialogDescription>Enter the courier and tracking number for order {order.order_number}.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleAddShipment} className="space-y-4">
                        <div className="grid gap-2">
                            <Label>Courier</Label>
                            <Select value={shipmentData.courier_code} onValueChange={(v) => setShipmentData('courier_code', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select courier" />
                                </SelectTrigger>
                                <SelectContent>
                                    {couriers.map((c) => (
                                        <SelectItem key={c.code} value={c.code}>
                                            {c.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Tracking Number</Label>
                            <input
                                type="text"
                                required
                                value={shipmentData.tracking_number}
                                onChange={(e) => setShipmentData('tracking_number', e.target.value)}
                                className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                placeholder="e.g. PTH-98765"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Note (optional)</Label>
                            <Textarea
                                value={shipmentData.note}
                                onChange={(e) => setShipmentData('note', e.target.value)}
                                placeholder="e.g. Fragile items"
                                rows={2}
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowShipmentDialog(false)}>
                                Cancel
                            </Button>
                            <Button disabled={shipmentProcessing}>Add Shipment</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Shipment Dialog */}
            <Dialog open={!!editShipment} onOpenChange={(open) => !open && setEditShipment(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Update Shipment</DialogTitle>
                        <DialogDescription>Update tracking for {editShipment?.courier}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleUpdateShipment} className="space-y-4">
                        <div className="grid gap-2">
                            <Label>Status</Label>
                            <Select value={updateShipmentData.status} onValueChange={(v) => setUpdateShipmentData('status', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending">Pending</SelectItem>
                                    <SelectItem value="picked">Picked Up</SelectItem>
                                    <SelectItem value="in_transit">In Transit</SelectItem>
                                    <SelectItem value="out_for_delivery">Out for Delivery</SelectItem>
                                    <SelectItem value="delivered">Delivered</SelectItem>
                                    <SelectItem value="failed">Failed</SelectItem>
                                    <SelectItem value="returned">Returned</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label>Tracking Number</Label>
                            <input
                                type="text"
                                value={updateShipmentData.tracking_number}
                                onChange={(e) => setUpdateShipmentData('tracking_number', e.target.value)}
                                className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Note (optional)</Label>
                            <Textarea value={updateShipmentData.note} onChange={(e) => setUpdateShipmentData('note', e.target.value)} rows={2} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setEditShipment(null)}>
                                Cancel
                            </Button>
                            <Button disabled={updateShipmentProcessing}>Update Shipment</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Send to Courier Dialog */}
            <Dialog open={showSendToCourierDialog} onOpenChange={setShowSendToCourierDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Send to Courier</DialogTitle>
                        <DialogDescription>This will create a shipment via the courier API and auto-mark the order as shipped.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSendToCourier} className="space-y-4">
                        <div className="grid gap-2">
                            <Label>Courier</Label>
                            <Select value={sendCourierData.courier_code} onValueChange={(v) => setSendCourierData('courier_code', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select courier" />
                                </SelectTrigger>
                                <SelectContent>
                                    {couriers
                                        .filter((c) => c.supports_api && c.configured)
                                        .map((c) => (
                                            <SelectItem key={c.code} value={c.code}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            {sendCourierErrors.courier_code && (
                                <p className="text-sm text-red-500">{sendCourierErrors.courier_code}</p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowSendToCourierDialog(false)}>
                                Cancel
                            </Button>
                            <Button disabled={sendCourierProcessing}>Send to Courier</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
