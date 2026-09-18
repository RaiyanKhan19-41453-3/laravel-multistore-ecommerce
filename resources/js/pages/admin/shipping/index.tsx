import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { formatPrice } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface ShippingMethod {
    id: number;
    name: string;
    description: string | null;
    estimated_days: number | null;
    is_active: boolean;
    sort_order: number;
}

interface ShippingZone {
    id: number;
    name: string;
    cities: string[] | null;
    is_fallback: boolean;
    country: string;
    is_active: boolean;
    sort_order: number;
}

interface ShippingRate {
    id: number;
    shipping_method_id: number;
    shipping_zone_id: number;
    price: number;
    free_shipping_min: number | null;
    shipping_method: ShippingMethod;
    shipping_zone: ShippingZone;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Shipping', href: '/admin/shipping' },
];

function MethodForm({ method, onClose }: { method?: ShippingMethod | null; onClose: () => void }) {
    const isEdit = !!method;
    const { data, setData, post, put, errors, processing } = useForm({
        name: method?.name ?? '',
        description: method?.description ?? '',
        estimated_days: method?.estimated_days?.toString() ?? '',
        is_active: method?.is_active ?? true,
        sort_order: method?.sort_order?.toString() ?? '0',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.shipping.methods.update', method.id), { onSuccess: () => onClose() });
        } else {
            post(route('admin.shipping.methods.store'), { onSuccess: () => onClose() });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required placeholder="e.g. Standard Delivery" />
                {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
            </div>
            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="e.g. Delivered in 2-3 days" />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="estimated_days">Estimated Days</Label>
                    <Input id="estimated_days" type="number" min="1" value={data.estimated_days} onChange={(e) => setData('estimated_days', e.target.value)} placeholder="e.g. 3" />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="sort_order">Sort Order</Label>
                    <Input id="sort_order" type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} />
                </div>
            </div>
            <div className="flex items-center gap-2">
                <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                <Label htmlFor="is_active">Active</Label>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                <Button disabled={processing}>{isEdit ? 'Update' : 'Create'}</Button>
            </DialogFooter>
        </form>
    );
}

function ZoneForm({ zone, onClose }: { zone?: ShippingZone | null; onClose: () => void }) {
    const isEdit = !!zone;
    const { data, setData, post, put, transform, errors, processing } = useForm({
        name: zone?.name ?? '',
        country: zone?.country ?? 'Bangladesh',
        cities: zone?.cities?.join(', ') ?? '',
        is_fallback: zone?.is_fallback ?? false,
        is_active: zone?.is_active ?? true,
        sort_order: zone?.sort_order?.toString() ?? '0',
    });

    const isFallback = data.is_fallback as boolean;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((formData) => ({
            ...formData,
            cities: isFallback
                ? []
                : String(formData.cities ?? '')
                      .split(',')
                      .map((c: string) => c.trim())
                      .filter(Boolean),
        }));
        if (isEdit) {
            put(route('admin.shipping.zones.update', zone.id), { onSuccess: () => onClose() });
        } else {
            post(route('admin.shipping.zones.store'), { onSuccess: () => onClose() });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Zone Name</Label>
                <Input id="name" value={data.name as string} onChange={(e) => setData('name', e.target.value)} required placeholder="e.g. Dhaka" />
                {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
            </div>
            <div className="grid gap-2">
                <Label htmlFor="country">Country</Label>
                <Input id="country" value={data.country as string} onChange={(e) => setData('country', e.target.value)} required placeholder="e.g. Bangladesh" />
                {errors.country && <p className="text-sm text-red-500">{errors.country}</p>}
            </div>
            <div className="flex items-center gap-2">
                <Switch id="is_fallback" checked={isFallback} onCheckedChange={(checked) => setData('is_fallback', checked)} />
                <Label htmlFor="is_fallback">Fallback zone (catch-all for unmatched cities)</Label>
            </div>
            {errors.is_fallback && <p className="text-sm text-red-500">{errors.is_fallback}</p>}
            {!isFallback && (
                <div className="grid gap-2">
                    <Label htmlFor="cities">Cities (comma-separated)</Label>
                    <Input id="cities" value={data.cities as string} onChange={(e) => setData('cities', e.target.value)} required placeholder="e.g. Dhaka, Chattogram, Sylhet" />
                    {errors.cities && <p className="text-sm text-red-500">{errors.cities}</p>}
                </div>
            )}
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="sort_order">Sort Order</Label>
                    <Input id="sort_order" type="number" min="0" value={data.sort_order as string} onChange={(e) => setData('sort_order', e.target.value)} />
                </div>
                <div className="flex items-end">
                    <div className="flex items-center gap-2">
                        <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                        <Label htmlFor="is_active">Active</Label>
                    </div>
                </div>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                <Button disabled={processing}>{isEdit ? 'Update' : 'Create'}</Button>
            </DialogFooter>
        </form>
    );
}

function RateForm({ rate, methods, zones, onClose }: { rate?: ShippingRate | null; methods: ShippingMethod[]; zones: ShippingZone[]; onClose: () => void }) {
    const { data, setData, post, processing } = useForm({
        shipping_method_id: rate?.shipping_method_id?.toString() ?? (methods[0]?.id?.toString() ?? ''),
        shipping_zone_id: rate?.shipping_zone_id?.toString() ?? (zones[0]?.id?.toString() ?? ''),
        price: rate?.price?.toString() ?? '',
        free_shipping_min: rate?.free_shipping_min?.toString() ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.shipping.rates.store'), { onSuccess: () => onClose() });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label>Shipping Method</Label>
                <select
                    value={data.shipping_method_id}
                    onChange={(e) => setData('shipping_method_id', e.target.value)}
                    className="rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    {methods.map((m) => (
                        <option key={m.id} value={m.id}>{m.name}</option>
                    ))}
                </select>
            </div>
            <div className="grid gap-2">
                <Label>Zone</Label>
                <select
                    value={data.shipping_zone_id}
                    onChange={(e) => setData('shipping_zone_id', e.target.value)}
                    className="rounded-md border border-neutral-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    {zones.map((z) => (
                        <option key={z.id} value={z.id}>{z.name}</option>
                    ))}
                </select>
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="price">Price</Label>
                    <Input id="price" type="number" step="0.01" min="0" value={data.price} onChange={(e) => setData('price', e.target.value)} required placeholder="0" />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="free_shipping_min">Free Shipping Min</Label>
                    <Input id="free_shipping_min" type="number" step="0.01" min="0" value={data.free_shipping_min} onChange={(e) => setData('free_shipping_min', e.target.value)} placeholder="No minimum" />
                </div>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                <Button disabled={processing}>Save Rate</Button>
            </DialogFooter>
        </form>
    );
}

export default function ShippingIndex({ methods, zones, rates }: { methods: ShippingMethod[]; zones: ShippingZone[]; rates: ShippingRate[] }) {
    const [showMethodForm, setShowMethodForm] = useState(false);
    const [editMethod, setEditMethod] = useState<ShippingMethod | null>(null);
    const [showZoneForm, setShowZoneForm] = useState(false);
    const [editZone, setEditZone] = useState<ShippingZone | null>(null);
    const [showRateForm, setShowRateForm] = useState(false);
    const [editRate, setEditRate] = useState<ShippingRate | null>(null);

    const activeMethods = methods.filter((m) => m.is_active);
    const activeZones = zones.filter((z) => z.is_active);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Shipping" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <Heading title="Shipping Management" description="Configure shipping methods, zones, and rates" />

                {/* Shipping Methods */}
                <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Shipping Methods</h2>
                        <Button size="sm" onClick={() => setShowMethodForm(true)}>
                            <Plus className="mr-1 h-4 w-4" /> Add Method
                        </Button>
                    </div>
                    {methods.length === 0 ? (
                        <p className="text-sm text-neutral-500">No shipping methods configured.</p>
                    ) : (
                        <div className="space-y-2">
                            {methods.map((method) => (
                                <div key={method.id} className="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-800">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{method.name}</span>
                                            {!method.is_active && <Badge variant="secondary">Inactive</Badge>}
                                        </div>
                                        {method.description && <p className="text-xs text-neutral-500">{method.description}</p>}
                                        {method.estimated_days && <p className="text-xs text-neutral-400">{method.estimated_days} days</p>}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <button onClick={() => setEditMethod(method)} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                        <button onClick={() => { if (confirm(`Delete "${method.name}"?`)) router.delete(route('admin.shipping.methods.destroy', method.id)); }} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Shipping Zones */}
                <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Shipping Zones</h2>
                        <Button size="sm" onClick={() => setShowZoneForm(true)}>
                            <Plus className="mr-1 h-4 w-4" /> Add Zone
                        </Button>
                    </div>
                    {zones.length === 0 ? (
                        <p className="text-sm text-neutral-500">No shipping zones configured.</p>
                    ) : (
                        <div className="space-y-2">
                            {zones.map((zone) => (
                                <div key={zone.id} className="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-800">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{zone.name}</span>
                                            {zone.is_fallback && <Badge variant="outline" className="border-amber-300 text-amber-700 dark:border-amber-700 dark:text-amber-300">Fallback</Badge>}
                                            {!zone.is_active && <Badge variant="secondary">Inactive</Badge>}
                                        </div>
                                        {zone.cities && zone.cities.length > 0 && (
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {zone.cities.map((city) => (
                                                    <Badge key={city} variant="outline">{city}</Badge>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <button onClick={() => setEditZone(zone)} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                        <button onClick={() => { if (confirm(`Delete "${zone.name}"?`)) router.delete(route('admin.shipping.zones.destroy', zone.id)); }} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Shipping Rates */}
                <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Shipping Rates</h2>
                        <Button size="sm" onClick={() => setShowRateForm(true)} disabled={activeMethods.length === 0 || activeZones.length === 0}>
                            <Plus className="mr-1 h-4 w-4" /> Add Rate
                        </Button>
                    </div>
                    {rates.length === 0 ? (
                        <p className="text-sm text-neutral-500">
                            {activeMethods.length === 0 || activeZones.length === 0
                                ? 'Create at least one shipping method and one zone first.'
                                : 'No shipping rates configured. Set prices for each method + zone combination.'}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">Method</th>
                                        <th className="px-4 py-2 font-medium">Zone</th>
                                        <th className="px-4 py-2 font-medium">Price</th>
                                        <th className="px-4 py-2 font-medium">Free Above</th>
                                        <th className="px-4 py-2 font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rates.map((rate) => (
                                        <tr key={rate.id}>
                                            <td className="px-4 py-2">{rate.shipping_method.name}</td>
                                            <td className="px-4 py-2">{rate.shipping_zone.name}</td>
                                            <td className="px-4 py-2">{formatPrice(rate.price)}</td>
                                            <td className="px-4 py-2">{rate.free_shipping_min ? formatPrice(rate.free_shipping_min) : '—'}</td>
                                            <td className="px-4 py-2">
                                                <button onClick={() => setEditRate(rate)} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button onClick={() => { if (confirm('Delete this rate?')) router.delete(route('admin.shipping.rates.destroy', rate.id)); }} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Method Dialog */}
            <Dialog open={showMethodForm} onOpenChange={setShowMethodForm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Shipping Method</DialogTitle>
                        <DialogDescription>Create a new shipping method like Standard or Express.</DialogDescription>
                    </DialogHeader>
                    <MethodForm onClose={() => setShowMethodForm(false)} />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editMethod} onOpenChange={(open) => !open && setEditMethod(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Shipping Method</DialogTitle>
                    </DialogHeader>
                    {editMethod && <MethodForm method={editMethod} onClose={() => setEditMethod(null)} />}
                </DialogContent>
            </Dialog>

            {/* Zone Dialog */}
            <Dialog open={showZoneForm} onOpenChange={setShowZoneForm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Shipping Zone</DialogTitle>
                        <DialogDescription>Create a zone with cities that share the same shipping rates.</DialogDescription>
                    </DialogHeader>
                    <ZoneForm onClose={() => setShowZoneForm(false)} />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editZone} onOpenChange={(open) => !open && setEditZone(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Shipping Zone</DialogTitle>
                    </DialogHeader>
                    {editZone && <ZoneForm zone={editZone} onClose={() => setEditZone(null)} />}
                </DialogContent>
            </Dialog>

            {/* Rate Dialog */}
            <Dialog open={showRateForm} onOpenChange={setShowRateForm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Shipping Rate</DialogTitle>
                        <DialogDescription>Set the price for a method + zone combination.</DialogDescription>
                    </DialogHeader>
                    <RateForm methods={activeMethods} zones={activeZones} onClose={() => setShowRateForm(false)} />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editRate} onOpenChange={(open) => !open && setEditRate(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Shipping Rate</DialogTitle>
                    </DialogHeader>
                    {editRate && <RateForm rate={editRate} methods={activeMethods} zones={activeZones} onClose={() => setEditRate(null)} />}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
