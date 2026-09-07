import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowUpDown, Box, ChevronDown, ChevronRight, History, MinusCircle, Package, PackageX, PlusCircle, Search, Warehouse } from 'lucide-react';
import { useMemo, useState } from 'react';

interface InventoryItem {
    id: number;
    product_id: number;
    product_variant_id: number | null;
    product_name: string;
    variant_name: string | null;
    variant_sku: string | null;
    product_sku: string;
    quantity: number;
    reserved_quantity: number;
    available_quantity: number;
    is_in_stock: boolean;
    is_low_stock: boolean;
}

interface ProductGroup {
    product_id: number;
    product_name: string;
    product_sku: string;
    total_quantity: number;
    total_reserved: number;
    total_available: number;
    is_in_stock: boolean;
    is_low_stock: boolean;
    has_variants: boolean;
    items: InventoryItem[];
}

interface Movement {
    id: number;
    type: string;
    quantity: number;
    note: string | null;
    user_name: string | null;
    created_at: string;
}

interface Stats {
    total_products: number;
    total_stock: number;
    low_stock: number;
    out_of_stock: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Inventory', href: '/admin/inventory' },
];

const ADJUST_TYPES = [
    { value: 'purchase', label: 'Purchase', icon: PlusCircle, negative: false },
    { value: 'adjustment', label: 'Manual Adjustment', icon: Box, negative: false },
    { value: 'return', label: 'Return', icon: History, negative: false },
    { value: 'damage', label: 'Damaged', icon: AlertTriangle, negative: true },
    { value: 'sale', label: 'Sale', icon: MinusCircle, negative: true },
];

function getStockBadge(item: { is_in_stock: boolean; is_low_stock: boolean }) {
    if (!item.is_in_stock) {
        return <Badge variant="destructive">Out of Stock</Badge>;
    }
    if (item.is_low_stock) {
        return <Badge className="bg-amber-500 text-white hover:bg-amber-600">Low Stock</Badge>;
    }
    return <Badge className="bg-emerald-500 text-white hover:bg-emerald-600">In Stock</Badge>;
}

function groupByProduct(items: InventoryItem[]): ProductGroup[] {
    const map = new Map<number, ProductGroup>();

    for (const item of items) {
        let group = map.get(item.product_id);
        if (!group) {
            group = {
                product_id: item.product_id,
                product_name: item.product_name,
                product_sku: item.product_sku,
                total_quantity: 0,
                total_reserved: 0,
                total_available: 0,
                is_in_stock: false,
                is_low_stock: false,
                has_variants: false,
                items: [],
            };
            map.set(item.product_id, group);
        }

        group.items.push(item);
        group.total_quantity += item.quantity;
        group.total_reserved += item.reserved_quantity;
        group.total_available += item.available_quantity;
    }

    for (const group of map.values()) {
        group.has_variants = group.items.some((i) => i.product_variant_id !== null);
        group.is_in_stock = group.total_quantity > 0;
        group.is_low_stock = group.total_quantity > 0 && group.total_quantity <= 5;
    }

    return Array.from(map.values()).sort((a, b) => a.product_name.localeCompare(b.product_name));
}

export default function InventoryIndex({
    inventories,
    stats,
}: {
    inventories: InventoryItem[];
    stats: Stats;
}) {
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState('all');
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    const [adjustDialogOpen, setAdjustDialogOpen] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [selectedInventory, setSelectedInventory] = useState<InventoryItem | null>(null);
    const [movements, setMovements] = useState<Movement[]>([]);
    const [loadingMovements, setLoadingMovements] = useState(false);
    const [clientError, setClientError] = useState('');

    const { data, setData, processing, errors, reset } = useForm({
        type: 'adjustment',
        quantity: '',
        note: '',
    });

    const groups = useMemo(() => {
        const filtered = inventories.filter((item) => {
            const matchesSearch =
                item.product_name.toLowerCase().includes(search.toLowerCase()) ||
                item.product_sku.toLowerCase().includes(search.toLowerCase()) ||
                (item.variant_name && item.variant_name.toLowerCase().includes(search.toLowerCase())) ||
                (item.variant_sku && item.variant_sku.toLowerCase().includes(search.toLowerCase()));
            return matchesSearch;
        });

        const grouped = groupByProduct(filtered);

        if (filter === 'all') return grouped;
        if (filter === 'in_stock') return grouped.filter((g) => g.is_in_stock);
        if (filter === 'low_stock') return grouped.filter((g) => g.is_low_stock);
        if (filter === 'out_of_stock') return grouped.filter((g) => !g.is_in_stock);

        return grouped;
    }, [inventories, search, filter]);

    const toggleExpand = (productId: number) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(productId)) {
                next.delete(productId);
            } else {
                next.add(productId);
            }
            return next;
        });
    };

    const openAdjust = (item: InventoryItem) => {
        setSelectedInventory(item);
        reset();
        setClientError('');
        setAdjustDialogOpen(true);
    };

    const submitAdjust = () => {
        if (!selectedInventory) return;

        const qty = Math.abs(Number(data.quantity));
        const isNegative = data.type === 'sale' || data.type === 'damage';
        const signedQty = isNegative ? -qty : qty;

        if (isNegative && qty > selectedInventory.available_quantity) {
            setClientError(`Cannot ${data.type} ${qty} units. Only ${selectedInventory.available_quantity} available.`);
            return;
        }

        setClientError('');

        router.post(
            route('admin.inventory.adjust', selectedInventory.id),
            {
                type: data.type,
                quantity: signedQty,
                note: data.note || null,
            },
            {
                preserveState: true,
                onSuccess: () => {
                    setAdjustDialogOpen(false);
                    reset();
                },
            },
        );
    };

    const openHistory = async (item: InventoryItem) => {
        setSelectedInventory(item);
        setHistoryOpen(true);
        setLoadingMovements(true);

        try {
            const response = await fetch(route('admin.inventory.movements', item.id));
            const json = await response.json();
            setMovements(json.movements);
        } catch {
            setMovements([]);
        } finally {
            setLoadingMovements(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventory" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Inventory Management</h1>
                    <p className="text-sm text-neutral-500">Track and manage stock levels across all products</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                                <Warehouse className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div>
                                <p className="text-sm text-neutral-500">Total Products</p>
                                <p className="text-2xl font-bold">{stats.total_products}</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-emerald-100 p-2 dark:bg-emerald-900/30">
                                <Package className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div>
                                <p className="text-sm text-neutral-500">Total Stock</p>
                                <p className="text-2xl font-bold">{stats.total_stock}</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30">
                                <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <p className="text-sm text-neutral-500">Low Stock</p>
                                <p className="text-2xl font-bold">{stats.low_stock}</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-red-100 p-2 dark:bg-red-900/30">
                                <PackageX className="h-5 w-5 text-red-600 dark:text-red-400" />
                            </div>
                            <div>
                                <p className="text-sm text-neutral-500">Out of Stock</p>
                                <p className="text-2xl font-bold">{stats.out_of_stock}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="flex flex-col gap-4 border-b border-neutral-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800">
                        <div className="relative flex-1 sm:max-w-sm">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                            <Input
                                placeholder="Search by name or SKU..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-9"
                            />
                        </div>
                        <div className="flex gap-2">
                            {(['all', 'in_stock', 'low_stock', 'out_of_stock'] as const).map((f) => (
                                <Button
                                    key={f}
                                    variant={filter === f ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setFilter(f)}
                                >
                                    {f === 'all' && 'All'}
                                    {f === 'in_stock' && 'In Stock'}
                                    {f === 'low_stock' && 'Low Stock'}
                                    {f === 'out_of_stock' && 'Out of Stock'}
                                </Button>
                            ))}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-800/50">
                                <tr>
                                    <th className="w-8 px-4 py-3" />
                                    <th className="px-4 py-3 text-left font-medium text-neutral-500">Product</th>
                                    <th className="px-4 py-3 text-right font-medium text-neutral-500">Stock</th>
                                    <th className="px-4 py-3 text-right font-medium text-neutral-500">Reserved</th>
                                    <th className="px-4 py-3 text-right font-medium text-neutral-500">Available</th>
                                    <th className="px-4 py-3 text-left font-medium text-neutral-500">Status</th>
                                    <th className="px-4 py-3 text-right font-medium text-neutral-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                {groups.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-12 text-center text-neutral-500">
                                            No inventory items found.
                                        </td>
                                    </tr>
                                ) : (
                                    groups.map((group) => {
                                        const isExpanded = expanded.has(group.product_id);

                                        return (
                                            <GroupRow
                                                key={group.product_id}
                                                group={group}
                                                isExpanded={isExpanded}
                                                onToggle={() => toggleExpand(group.product_id)}
                                                onAdjust={openAdjust}
                                                onHistory={openHistory}
                                            />
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Dialog open={adjustDialogOpen} onOpenChange={setAdjustDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Adjust Stock</DialogTitle>
                        <DialogDescription>
                            {selectedInventory?.product_name}
                            {selectedInventory?.variant_name && ` — ${selectedInventory.variant_name}`}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-2">
                        <div className="rounded-lg bg-neutral-50 p-3 dark:bg-neutral-800">
                            <div className="grid grid-cols-3 gap-4 text-center text-sm">
                                <div>
                                    <p className="text-neutral-500">Current</p>
                                    <p className="text-lg font-bold">{selectedInventory?.quantity}</p>
                                </div>
                                <div>
                                    <p className="text-neutral-500">Reserved</p>
                                    <p className="text-lg font-bold">{selectedInventory?.reserved_quantity}</p>
                                </div>
                                <div>
                                    <p className="text-neutral-500">Available</p>
                                    <p className="text-lg font-bold">{selectedInventory?.available_quantity}</p>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Type</Label>
                            <Select
                                value={data.type}
                                onValueChange={(value) => {
                                    setData('type', value);
                                    setClientError('');
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ADJUST_TYPES.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            <div className="flex items-center gap-2">
                                                <t.icon className="h-4 w-4" />
                                                {t.label}
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label>Quantity</Label>
                            <Input
                                type="number"
                                min="1"
                                value={data.quantity}
                                onChange={(e) => {
                                    setData('quantity', e.target.value);
                                    setClientError('');
                                }}
                                placeholder={data.type === 'sale' || data.type === 'damage' ? 'Amount to remove' : 'Amount to add'}
                            />
                            {clientError && <p className="text-sm text-red-500">{clientError}</p>}
                            {errors.quantity && <p className="text-sm text-red-500">{errors.quantity}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label>Note (optional)</Label>
                            <Textarea
                                value={data.note}
                                onChange={(e) => setData('note', e.target.value)}
                                placeholder="Reason for adjustment..."
                                rows={2}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAdjustDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={submitAdjust} disabled={processing || !data.quantity}>
                            {processing ? 'Saving...' : 'Save Adjustment'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Sheet open={historyOpen} onOpenChange={setHistoryOpen}>
                <SheetContent className="w-full sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Movement History</SheetTitle>
                        <SheetDescription>
                            {selectedInventory?.product_name}
                            {selectedInventory?.variant_name && ` — ${selectedInventory.variant_name}`}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="mt-6 space-y-3">
                        {loadingMovements ? (
                            <div className="py-8 text-center text-sm text-neutral-500">Loading...</div>
                        ) : movements.length === 0 ? (
                            <div className="py-8 text-center text-sm text-neutral-500">No movements recorded yet.</div>
                        ) : (
                            movements.map((m) => (
                                <div key={m.id} className="flex items-center justify-between rounded-lg border border-neutral-200 p-3 dark:border-neutral-800">
                                    <div className="flex items-center gap-3">
                                        <div className={`rounded-full p-1.5 ${m.quantity > 0 ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-red-100 dark:bg-red-900/30'}`}>
                                            {m.quantity > 0 ? (
                                                <PlusCircle className="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                            ) : (
                                                <MinusCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium capitalize">{m.type}</p>
                                            <p className="text-xs text-neutral-500">{m.created_at}</p>
                                            {m.user_name && <p className="text-xs text-neutral-400">by {m.user_name}</p>}
                                            {m.note && <p className="mt-0.5 text-xs text-neutral-400">{m.note}</p>}
                                        </div>
                                    </div>
                                    <span className={`text-sm font-bold ${m.quantity > 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                                        {m.quantity > 0 ? '+' : ''}{m.quantity}
                                    </span>
                                </div>
                            ))
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}

function GroupRow({
    group,
    isExpanded,
    onToggle,
    onAdjust,
    onHistory,
}: {
    group: ProductGroup;
    isExpanded: boolean;
    onToggle: () => void;
    onAdjust: (item: InventoryItem) => void;
    onHistory: (item: InventoryItem) => void;
}) {
    const canExpand = group.has_variants;

    if (!canExpand) {
        const item = group.items[0];

        return (
            <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                <td className="px-4 py-3" />
                <td className="px-4 py-3">
                    <div>
                        <p className="font-medium">{group.product_name}</p>
                        <p className="text-xs text-neutral-500">{group.product_sku}</p>
                    </div>
                </td>
                <td className="px-4 py-3 text-right font-medium">{item.quantity}</td>
                <td className="px-4 py-3 text-right text-neutral-500">{item.reserved_quantity}</td>
                <td className="px-4 py-3 text-right font-medium">{item.available_quantity}</td>
                <td className="px-4 py-3">{getStockBadge(item)}</td>
                <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-1">
                        <Button variant="ghost" size="sm" onClick={() => onAdjust(item)}>
                            Adjust
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => onHistory(item)}>
                            History
                        </Button>
                    </div>
                </td>
            </tr>
        );
    }

    return (
        <>
            <tr className="cursor-pointer bg-neutral-50/50 hover:bg-neutral-100 dark:bg-neutral-800/20 dark:hover:bg-neutral-800/40" onClick={onToggle}>
                <td className="px-4 py-3">
                    {isExpanded ? (
                        <ChevronDown className="h-4 w-4 text-neutral-500" />
                    ) : (
                        <ChevronRight className="h-4 w-4 text-neutral-500" />
                    )}
                </td>
                <td className="px-4 py-3">
                    <div>
                        <p className="font-semibold">{group.product_name}</p>
                        <p className="text-xs text-neutral-500">{group.product_sku} · {group.items.length} variants</p>
                    </div>
                </td>
                <td className="px-4 py-3 text-right font-medium">{group.total_quantity}</td>
                <td className="px-4 py-3 text-right text-neutral-500">{group.total_reserved}</td>
                <td className="px-4 py-3 text-right font-medium">{group.total_available}</td>
                <td className="px-4 py-3">{getStockBadge(group)}</td>
                <td className="px-4 py-3" />
            </tr>
            {isExpanded &&
                group.items.map((item) => (
                    <tr key={item.id} className="bg-neutral-50/30 hover:bg-neutral-50 dark:bg-neutral-800/10 dark:hover:bg-neutral-800/20">
                        <td className="px-4 py-2.5" />
                        <td className="px-4 py-2.5 pl-12">
                            <div>
                                <p className="text-sm">{item.variant_name}</p>
                                <p className="text-xs text-neutral-500">{item.variant_sku}</p>
                            </div>
                        </td>
                        <td className="px-4 py-2.5 text-right text-sm font-medium">{item.quantity}</td>
                        <td className="px-4 py-2.5 text-right text-sm text-neutral-500">{item.reserved_quantity}</td>
                        <td className="px-4 py-2.5 text-right text-sm font-medium">{item.available_quantity}</td>
                        <td className="px-4 py-2.5">{getStockBadge(item)}</td>
                        <td className="px-4 py-2.5 text-right">
                            <div className="flex items-center justify-end gap-1">
                                <Button variant="ghost" size="sm" onClick={(e) => { e.stopPropagation(); onAdjust(item); }}>
                                    Adjust
                                </Button>
                                <Button variant="ghost" size="sm" onClick={(e) => { e.stopPropagation(); onHistory(item); }}>
                                    History
                                </Button>
                            </div>
                        </td>
                    </tr>
                ))}
        </>
    );
}
