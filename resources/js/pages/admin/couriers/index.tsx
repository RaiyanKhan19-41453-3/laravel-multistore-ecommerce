import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plug, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface SettingsField {
    key: string;
    label: string;
    type: 'text' | 'password' | 'toggle';
    required: boolean;
    placeholder?: string;
}

interface Courier {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
    sort_order: number;
    shipments_count: number;
    settings: Record<string, string | boolean> | null;
    supports_api: boolean;
    settings_schema: SettingsField[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Couriers', href: '/admin/couriers' },
];

function CourierForm({ courier, onClose }: { courier?: Courier | null; onClose: () => void }) {
    const isEdit = !!courier;
    const { data, setData, post, put, errors, processing } = useForm({
        name: courier?.name ?? '',
        code: courier?.code ?? '',
        is_active: courier?.is_active ?? true,
        sort_order: courier?.sort_order?.toString() ?? '0',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.couriers.update', courier.id), { onSuccess: () => onClose() });
        } else {
            post(route('admin.couriers.store'), { onSuccess: () => onClose() });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required placeholder="e.g. Pathao Courier" />
                {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
            </div>
            <div className="grid gap-2">
                <Label htmlFor="code">Code</Label>
                <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} required placeholder="e.g. pathao" />
                {errors.code && <p className="text-sm text-red-500">{errors.code}</p>}
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="sort_order">Sort Order</Label>
                    <Input id="sort_order" type="number" min="0" value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} />
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

function SettingsForm({ courier, onClose }: { courier: Courier; onClose: () => void }) {
    const schema = courier.settings_schema ?? [];

    const initialSettings: Record<string, string | boolean> = { sandbox: true };
    schema.forEach((field) => {
        initialSettings[field.key] = (courier.settings?.[field.key] as string | boolean) ?? (field.type === 'toggle' ? true : '');
    });

    const { data, setData, put, errors, processing } = useForm({ settings: initialSettings });
    const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);
    const [testing, setTesting] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.couriers.settings.update', courier.id), { onSuccess: () => onClose() });
    };

    const testConnection = () => {
        setTesting(true);
        setTestResult(null);

        const csrf = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)?.[2] ?? '';

        void fetch(route('admin.couriers.test-connection', courier.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(csrf),
            },
        })
            .then((r) => r.json())
            .then((res) => setTestResult(res))
            .catch(() => setTestResult({ success: false, message: 'Request failed.' }))
            .finally(() => setTesting(false));
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <p className="text-sm text-neutral-500">API credentials for {courier.name}. Leave blank to use manual entry only.</p>

            {schema.map((field) => (
                <div key={field.key} className="grid gap-2">
                    <Label>{field.label}</Label>
                    {field.type === 'toggle' ? (
                        <div className="flex items-center gap-2">
                            <Switch
                                id={`settings-${field.key}`}
                                checked={!!data.settings[field.key]}
                                onCheckedChange={(checked) => setData('settings', { ...data.settings, [field.key]: checked })}
                            />
                            <Label htmlFor={`settings-${field.key}`}>{field.label}</Label>
                        </div>
                    ) : (
                        <Input
                            type={field.type === 'password' ? 'password' : 'text'}
                            value={(data.settings[field.key] as string) ?? ''}
                            onChange={(e) => setData('settings', { ...data.settings, [field.key]: e.target.value })}
                            placeholder={field.placeholder}
                        />
                    )}
                </div>
            ))}

            {!schema.some((field) => field.key === 'sandbox') && (
                <div className="flex items-center gap-2">
                    <Switch id="sandbox" checked={data.settings.sandbox as boolean} onCheckedChange={(checked) => setData('settings', { ...data.settings, sandbox: checked })} />
                    <Label htmlFor="sandbox">Sandbox mode</Label>
                </div>
            )}
            {errors.settings && <p className="text-sm text-red-500">{typeof errors.settings === 'string' ? errors.settings : 'Validation error'}</p>}
            {testResult && (
                <div className={`rounded-md px-3 py-2 text-sm ${testResult.success ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'}`}>
                    {testResult.message}
                </div>
            )}
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                <Button type="button" variant="outline" onClick={testConnection} disabled={testing}>
                    <Plug className="mr-1 h-4 w-4" /> {testing ? 'Testing...' : 'Test Connection'}
                </Button>
                <Button disabled={processing}>Save Settings</Button>
            </DialogFooter>
        </form>
    );
}

export default function CouriersIndex({ couriers }: { couriers: Courier[] }) {
    const [showForm, setShowForm] = useState(false);
    const [editCourier, setEditCourier] = useState<Courier | null>(null);
    const [settingsCourier, setSettingsCourier] = useState<Courier | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Couriers" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <Heading title="Courier Management" description="Manage courier companies and API settings" />

                <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Couriers</h2>
                        <Button size="sm" onClick={() => setShowForm(true)}>
                            <Plus className="mr-1 h-4 w-4" /> Add Courier
                        </Button>
                    </div>
                    {couriers.length === 0 ? (
                        <p className="text-sm text-neutral-500">No couriers configured.</p>
                    ) : (
                        <div className="space-y-2">
                            {couriers.map((courier) => (
                                <div key={courier.id} className="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-800">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{courier.name}</span>
                                            <Badge variant="outline" className="text-xs">{courier.code}</Badge>
                                            {courier.supports_api && <Badge variant="default" className="text-xs">API</Badge>}
                                            {!courier.is_active && <Badge variant="secondary">Inactive</Badge>}
                                        </div>
                                        <p className="mt-0.5 text-xs text-neutral-400">
                                            {courier.shipments_count} shipment{courier.shipments_count !== 1 ? 's' : ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        {courier.supports_api && (
                                            <button onClick={() => setSettingsCourier(courier)} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800" title="API Settings">
                                                <Plug className="h-4 w-4" />
                                            </button>
                                        )}
                                        <button onClick={() => setEditCourier(courier)} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                        <button onClick={() => { if (confirm(`Delete "${courier.name}"?`)) router.delete(route('admin.couriers.destroy', courier.id)); }} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            <Dialog open={showForm} onOpenChange={setShowForm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Courier</DialogTitle>
                        <DialogDescription>Add a new courier company.</DialogDescription>
                    </DialogHeader>
                    <CourierForm onClose={() => setShowForm(false)} />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editCourier} onOpenChange={(open) => !open && setEditCourier(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Courier</DialogTitle>
                    </DialogHeader>
                    {editCourier && <CourierForm courier={editCourier} onClose={() => setEditCourier(null)} />}
                </DialogContent>
            </Dialog>

            <Dialog open={!!settingsCourier} onOpenChange={(open) => !open && setSettingsCourier(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>API Settings — {settingsCourier?.name}</DialogTitle>
                    </DialogHeader>
                    {settingsCourier && <SettingsForm courier={settingsCourier} onClose={() => setSettingsCourier(null)} />}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
