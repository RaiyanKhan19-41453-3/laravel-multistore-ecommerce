import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface AuditLogEntry {
    id: number;
    action: string;
    method: string;
    path: string;
    ip: string | null;
    status: number | null;
    changes: Record<string, unknown> | null;
    created_at: string;
    user: { id: number; name: string } | null;
}

interface PaginatedLogs {
    data: AuditLogEntry[];
    current_page: number;
    last_page: number;
    total: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Audit Log', href: '/admin/audit-logs' },
];

const STATUS_STYLES: Record<string, string> = {
    '2': 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    '3': 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    '4': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    '5': 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
};

export default function AuditLogsIndex({ logs, filters }: { logs: PaginatedLogs; filters: { action?: string } }) {
    const [action, setAction] = useState(filters.action ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);

    const apply = () => router.get('/admin/audit-logs', action ? { action } : {}, { preserveState: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Log" />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="Audit Log" description="Who changed what in the admin panel. Passwords, tokens, and secrets are never stored." />

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply();
                    }}
                    className="flex items-end gap-2"
                >
                    <Input
                        value={action}
                        onChange={(e) => setAction(e.target.value)}
                        placeholder="Filter by action, e.g. admin.products"
                        className="max-w-xs"
                    />
                    <Button type="submit">Filter</Button>
                    {filters.action && (
                        <Button type="button" variant="outline" onClick={() => router.get('/admin/audit-logs', {}, { preserveState: true })}>
                            Clear
                        </Button>
                    )}
                </form>

                <div className="bg-card rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted">
                                <tr>
                                    <th className="px-3 py-2 text-left">Time</th>
                                    <th className="px-3 py-2 text-left">User</th>
                                    <th className="px-3 py-2 text-left">Action</th>
                                    <th className="px-3 py-2 text-left">Request</th>
                                    <th className="px-3 py-2 text-right">Status</th>
                                    <th className="px-3 py-2 text-right">Changes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="border-t align-top">
                                        <td className="text-muted-foreground px-3 py-2 whitespace-nowrap">
                                            {new Date(log.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-3 py-2">{log.user?.name ?? '—'}</td>
                                        <td className="px-3 py-2 font-mono text-xs">{log.action}</td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {log.method} /{log.path}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {log.status !== null && (
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs ${STATUS_STYLES[String(log.status)[0]] ?? STATUS_STYLES['4']}`}
                                                >
                                                    {log.status}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {log.changes && Object.keys(log.changes).length > 0 ? (
                                                <Button size="sm" variant="outline" onClick={() => setExpanded(expanded === log.id ? null : log.id)}>
                                                    {expanded === log.id ? 'Hide' : 'View'}
                                                </Button>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {logs.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground px-3 py-8 text-center">
                                            No admin writes recorded yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {expanded !== null && logs.data.some((l) => l.id === expanded) && (
                        <pre className="bg-muted/50 overflow-x-auto border-t p-4 font-mono text-xs">
                            {JSON.stringify(logs.data.find((l) => l.id === expanded)?.changes, null, 2)}
                        </pre>
                    )}
                    {logs.last_page > 1 && (
                        <div className="flex justify-center gap-2 border-t p-3">
                            {Array.from({ length: logs.last_page }, (_, i) => i + 1).map((page) => (
                                <Button
                                    key={page}
                                    size="sm"
                                    variant={logs.current_page === page ? 'default' : 'outline'}
                                    onClick={() => router.get('/admin/audit-logs', { ...filters, page }, { preserveState: true })}
                                >
                                    {page}
                                </Button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
