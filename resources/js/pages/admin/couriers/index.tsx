import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Plug, ShieldCheck, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

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
    { title: 'Couriers', href: '/admin/couriers' },
];

export default function CouriersIndex({ couriers }: { couriers: Courier[] }) {
    const [testing, setTesting] = useState<string | null>(null);
    const [results, setResults] = useState<Record<string, { success: boolean; message: string }>>({});

    const testConnection = (code: string) => {
        setTesting(code);
        setResults((prev) => ({ ...prev, [code]: { success: false, message: 'Testing...' } }));

        const csrf = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/)?.[2] ?? '';

        void fetch(route('admin.couriers.test-connection', code), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(csrf),
            },
        })
            .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
            .then(({ ok, body }) =>
                setResults((prev) => ({
                    ...prev,
                    [code]: { success: ok && body.success, message: body.message ?? 'Request failed.' },
                })),
            )
            .catch(() => setResults((prev) => ({ ...prev, [code]: { success: false, message: 'Request failed.' } })))
            .finally(() => setTesting(null));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Couriers" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <Heading
                    title="Couriers"
                    description="Courier list is defined in config/couriers.php with credentials in .env. This page shows live status only."
                />

                <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Couriers</h2>
                        <span className="text-xs text-neutral-500">{couriers.length} configured in code</span>
                    </div>
                    {couriers.length === 0 ? (
                        <p className="text-sm text-neutral-500">No couriers defined in config/couriers.php.</p>
                    ) : (
                        <div className="space-y-2">
                            {couriers.map((courier) => {
                                const result = results[courier.code];

                                return (
                                    <div key={courier.code} className="flex items-center justify-between gap-3 rounded-lg border border-neutral-100 p-3 dark:border-neutral-800">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">{courier.name}</span>
                                                <Badge variant="outline" className="text-xs">{courier.code}</Badge>
                                                {courier.supports_api && <Badge variant="default" className="text-xs">API</Badge>}
                                                {!courier.enabled && <Badge variant="secondary">Disabled</Badge>}
                                                {courier.supports_api && courier.configured ? (
                                                    <Badge variant="outline" className="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                                        <ShieldCheck className="h-3 w-3" /> Keys set
                                                    </Badge>
                                                ) : courier.supports_api ? (
                                                    <Badge variant="outline" className="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                                                        <ShieldAlert className="h-3 w-3" /> Keys missing
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <p className="mt-0.5 text-xs text-neutral-400">
                                                {courier.shipments_count} shipment{courier.shipments_count !== 1 ? 's' : ''}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {result && (
                                                <span className={`text-xs ${result.success ? 'text-green-600 dark:text-green-400' : 'text-red-500'}`}>{result.message}</span>
                                            )}
                                            {courier.supports_api && (
                                                <Button size="sm" variant="outline" onClick={() => testConnection(courier.code)} disabled={testing === courier.code}>
                                                    <Plug className="mr-1 h-4 w-4" />
                                                    {testing === courier.code ? 'Testing...' : 'Test Connection'}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
