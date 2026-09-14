import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Landmark, RotateCcw } from 'lucide-react';

interface ZatcaRow {
    id: number;
    order_number: string | null;
    type: string;
    uuid: string;
    icv: number;
    status: string;
    submit_attempts: number;
    submitted_at: string | null;
    created_at: string;
}

interface PaginatedRows {
    data: ZatcaRow[];
    links: { url: string | null; label: string; active: boolean }[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'ZATCA E-Invoicing', href: '/admin/zatca' },
];

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'cleared':
        case 'reported':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'signed':
            return 'secondary';
        default:
            return 'outline';
    }
}

export default function ZatcaIndex({
    documents,
    counts,
    device,
    enabled,
    sandbox,
}: {
    documents: PaginatedRows;
    counts: Record<string, number>;
    device: { serial: string; onboarded: boolean; onboarded_at: string | null; has_private_key: boolean } | null;
    enabled: boolean;
    sandbox: boolean;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ZATCA E-Invoicing" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <Heading title="ZATCA E-Invoicing" description="Fatoora clearance and reporting ledger" />

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="flex items-center gap-2">
                            <Landmark className="h-4 w-4 text-neutral-500" />
                            <h2 className="font-semibold">Integration</h2>
                        </div>
                        <div className="mt-3 space-y-1 text-sm">
                            <p>
                                Status:{' '}
                                <Badge variant={enabled ? 'default' : 'secondary'}>{enabled ? 'Enabled' : 'Disabled'}</Badge>
                            </p>
                            <p>
                                Environment: <span className="font-medium">{sandbox ? 'Sandbox' : 'Production'}</span>
                            </p>
                            <p>
                                Device:{' '}
                                {device ? (
                                    <span className="font-mono">{device.serial}</span>
                                ) : (
                                    <span className="text-neutral-500">Not onboarded — run <span className="font-mono">php artisan zatca:onboard</span></span>
                                )}
                            </p>
                            {device && (
                                <p>
                                    Onboarded:{' '}
                                    <Badge variant={device.onboarded ? 'default' : 'outline'}>
                                        {device.onboarded ? 'Yes' : 'Pending'}
                                    </Badge>
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm md:col-span-2 dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="font-semibold">Documents by status</h2>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {Object.keys(counts).length === 0 && <p className="text-sm text-neutral-500">No documents yet.</p>}
                            {Object.entries(counts).map(([status, total]) => (
                                <span key={status} className="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-1.5 text-sm dark:border-neutral-700">
                                    <Badge variant={statusVariant(status)}>{status}</Badge>
                                    <span className="font-semibold tabular-nums">{total}</span>
                                </span>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                                <tr>
                                    <th className="px-4 py-2 font-medium">Invoice</th>
                                    <th className="px-4 py-2 font-medium">Type</th>
                                    <th className="px-4 py-2 font-medium">UUID</th>
                                    <th className="px-4 py-2 font-medium">ICV</th>
                                    <th className="px-4 py-2 font-medium">Status</th>
                                    <th className="px-4 py-2 font-medium">Attempts</th>
                                    <th className="px-4 py-2 font-medium">Submitted</th>
                                    <th className="px-4 py-2 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {documents.data.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-8 text-center text-neutral-500">
                                            No documents yet. They appear here when orders confirm with ZATCA enabled.
                                        </td>
                                    </tr>
                                )}
                                {documents.data.map((doc) => (
                                    <tr key={doc.id}>
                                        <td className="px-4 py-2 font-mono">{doc.order_number ?? `#${doc.id}`}</td>
                                        <td className="px-4 py-2 capitalize">{doc.type}</td>
                                        <td className="max-w-48 truncate px-4 py-2 font-mono text-xs text-neutral-500">{doc.uuid}</td>
                                        <td className="px-4 py-2 tabular-nums">{doc.icv}</td>
                                        <td className="px-4 py-2">
                                            <Badge variant={statusVariant(doc.status)}>{doc.status}</Badge>
                                        </td>
                                        <td className="px-4 py-2 tabular-nums">{doc.submit_attempts}</td>
                                        <td className="px-4 py-2 text-neutral-500">
                                            {doc.submitted_at ? new Date(doc.submitted_at).toLocaleString() : '—'}
                                        </td>
                                        <td className="px-4 py-2">
                                            {!['reported', 'cleared'].includes(doc.status) && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => router.post(route('admin.zatca.retry', doc.id))}
                                                >
                                                    <RotateCcw className="mr-1 h-3 w-3" /> Retry
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
