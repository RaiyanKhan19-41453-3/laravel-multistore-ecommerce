import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatPrice } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Minus, Plus, Printer, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

interface LabelVariant {
    id: number;
    name: string;
    sku: string | null;
    barcode: string | null;
    price: number;
    printable: boolean;
}

interface LabelProduct {
    id: number;
    name: string;
    sku: string | null;
    barcode: string | null;
    price: number;
    type: string;
    printable: boolean;
    variants: LabelVariant[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Products', href: '/admin/products' },
    { title: 'Print Labels', href: '#' },
];

function QtyStepper({ value, onChange, disabled }: { value: number; onChange: (v: number) => void; disabled?: boolean }) {
    return (
        <div className="flex items-center gap-1">
            <button
                type="button"
                disabled={disabled || value <= 0}
                onClick={() => onChange(Math.max(0, value - 1))}
                className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-neutral-300 text-neutral-600 hover:bg-neutral-100 disabled:opacity-40 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
            >
                <Minus className="h-3 w-3" />
            </button>
            <span className="w-8 text-center text-sm font-medium tabular-nums">{value}</span>
            <button
                type="button"
                disabled={disabled}
                onClick={() => onChange(Math.min(100, value + 1))}
                className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-neutral-300 text-neutral-600 hover:bg-neutral-100 disabled:opacity-40 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
            >
                <Plus className="h-3 w-3" />
            </button>
        </div>
    );
}

export default function ProductLabels({
    products,
    layouts,
    defaultLayout,
}: {
    products: LabelProduct[];
    layouts: Record<string, { label: string; columns: number; kind: string }>;
    defaultLayout: string;
}) {
    const [search, setSearch] = useState('');
    const [quantities, setQuantities] = useState<Record<string, number>>({});
    const [layout, setLayout] = useState(defaultLayout);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return products;
        return products.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                (p.sku ?? '').toLowerCase().includes(q) ||
                p.variants.some((v) => v.name.toLowerCase().includes(q) || (v.sku ?? '').toLowerCase().includes(q)),
        );
    }, [products, search]);

    const setQty = (key: string, value: number) => {
        setQuantities((prev) => {
            if (value <= 0) {
                const next = { ...prev };
                delete next[key];
                return next;
            }
            return { ...prev, [key]: value };
        });
    };

    const totalLabels = Object.values(quantities).reduce((sum, q) => sum + q, 0);

    const selectAllPrintable = () => {
        const next: Record<string, number> = {};
        for (const p of products) {
            if (p.type === 'variable') {
                for (const v of p.variants) {
                    if (v.printable) next[`v:${v.id}`] = 1;
                }
            } else if (p.printable) {
                next[`p:${p.id}`] = 1;
            }
        }
        setQuantities(next);
    };

    const handlePrint = () => {
        const items = Object.entries(quantities)
            .map(([key, qty]) => `${key}:${qty}`)
            .join(',');

        router.get(
            route('admin.products.labels.print'),
            { items, layout },
            { preserveState: false },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Print Barcode Labels" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <Heading title="Print Barcode Labels" description="Pick products and variants, set quantities, choose a label layout." />
                    <Link href={route('admin.products.index')}>
                        <Button variant="outline">Back to Products</Button>
                    </Link>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <Label htmlFor="label-search">Search products</Label>
                        <div className="relative mt-1">
                            <Search className="absolute top-2.5 left-3 h-4 w-4 text-neutral-400" />
                            <Input
                                id="label-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Name or SKU..."
                                className="pl-9"
                            />
                        </div>
                    </div>

                    <div className="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm lg:col-span-2 dark:border-neutral-800 dark:bg-neutral-900">
                        <Label>Label layout</Label>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {Object.entries(layouts).map(([key, cfg]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setLayout(key)}
                                    className={`rounded-lg border px-3 py-2 text-sm transition ${
                                        layout === key
                                            ? 'border-blue-600 bg-blue-50 font-medium text-blue-700 dark:border-blue-500 dark:bg-blue-900/20 dark:text-blue-300'
                                            : 'border-neutral-300 text-neutral-600 hover:bg-neutral-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800'
                                    }`}
                                >
                                    {cfg.label}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-800">
                        <h2 className="font-semibold">Items {totalLabels > 0 && <span className="text-sm font-normal text-neutral-500">({totalLabels} labels)</span>}</h2>
                        <div className="flex gap-2">
                            <Button size="sm" variant="outline" onClick={selectAllPrintable}>
                                Select all printable
                            </Button>
                            <Button size="sm" variant="outline" onClick={() => setQuantities({})}>
                                Clear
                            </Button>
                        </div>
                    </div>

                    <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {filtered.length === 0 && <p className="p-6 text-sm text-neutral-500">No products match your search.</p>}
                        {filtered.map((p) => (
                            <div key={p.id} className="p-4">
                                <div className="flex items-center justify-between gap-4">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">{p.name}</span>
                                            {p.type === 'variable' ? (
                                                <Badge variant="secondary">{p.variants.length} variants</Badge>
                                            ) : p.printable ? (
                                                <Badge variant="outline" className="font-mono text-xs">{p.barcode ?? p.sku}</Badge>
                                            ) : (
                                                <Badge variant="destructive">No SKU/barcode</Badge>
                                            )}
                                        </div>
                                        <p className="mt-0.5 text-xs text-neutral-500">
                                            {p.sku && <span className="font-mono">SKU: {p.sku} · </span>}
                                            {formatPrice(p.price)}
                                        </p>
                                    </div>
                                    {p.type !== 'variable' && (
                                        <QtyStepper value={quantities[`p:${p.id}`] ?? 0} onChange={(v) => setQty(`p:${p.id}`, v)} disabled={!p.printable} />
                                    )}
                                </div>

                                {p.variants.length > 0 && (
                                    <div className="mt-3 space-y-2 pl-4">
                                        {p.variants.map((v) => (
                                            <div key={v.id} className="flex items-center justify-between gap-4 rounded-lg bg-neutral-50 px-3 py-2 dark:bg-neutral-800/50">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="text-sm">{v.name}</span>
                                                        {v.printable ? (
                                                            <Badge variant="outline" className="font-mono text-xs">{v.barcode ?? v.sku}</Badge>
                                                        ) : (
                                                            <Badge variant="destructive">No SKU/barcode</Badge>
                                                        )}
                                                    </div>
                                                    <p className="mt-0.5 text-xs text-neutral-500">
                                                        {v.sku && <span className="font-mono">SKU: {v.sku} · </span>}
                                                        {formatPrice(v.price)}
                                                    </p>
                                                </div>
                                                <QtyStepper value={quantities[`v:${v.id}`] ?? 0} onChange={(qty) => setQty(`v:${v.id}`, qty)} disabled={!v.printable} />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="sticky bottom-4 flex items-center justify-between gap-4 rounded-xl border border-neutral-200 bg-white p-4 shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                    <p className="text-sm text-neutral-600 dark:text-neutral-300">
                        {totalLabels === 0 ? (
                            'Set a quantity on the items you want to print.'
                        ) : (
                            <>
                                <span className="font-semibold text-neutral-900 dark:text-white">{totalLabels} labels</span> · {layouts[layout]?.label}
                            </>
                        )}
                    </p>
                    <Button onClick={handlePrint} disabled={totalLabels === 0}>
                        <Printer className="mr-2 h-4 w-4" /> Preview & Print
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
