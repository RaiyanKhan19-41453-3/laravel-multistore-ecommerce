import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface MenuNode {
    id: number;
    parent_id: number | null;
    title: string;
    title_ar: string | null;
    type: string;
    reference_id: number | null;
    url: string | null;
    click_behavior: string;
    display: string;
    promo_image: string | null;
    promo_title: string | null;
    promo_link: string | null;
    is_active: boolean;
    sort_order: number;
    children: MenuNode[];
}

interface TargetOption {
    id: number;
    name?: string;
    title?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Menus', href: '/admin/menus' },
];

const TYPES = [
    { value: 'category', label: 'Category' },
    { value: 'brand', label: 'Brand' },
    { value: 'product', label: 'Product' },
    { value: 'page', label: 'CMS Page' },
    { value: 'url', label: 'Custom URL' },
];

function targetLabel(type: string): string {
    return type === 'page' ? 'Page' : `${type.charAt(0).toUpperCase()}${type.slice(1)}`;
}

export default function MenusIndex({ items, targets }: { items: MenuNode[]; targets: Record<string, TargetOption[]> }) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<MenuNode | null>(null);
    const [presetParent, setPresetParent] = useState<number | null>(null);

    const openCreate = (parentId: number | null = null) => {
        setEditing(null);
        setPresetParent(parentId);
        setDialogOpen(true);
    };

    const openEdit = (item: MenuNode) => {
        setEditing(item);
        setPresetParent(null);
        setDialogOpen(true);
    };

    const toggle = (id: number) => {
        router.post(`/admin/menus/${id}/toggle`, {}, { preserveState: true });
    };

    const destroy = (item: MenuNode) => {
        const kids = item.children ?? [];
        const warning = kids.length > 0 ? ` Delete "${item.title}" and its ${kids.length} submenu item(s)?` : `Delete "${item.title}"?`;
        if (confirm(warning)) {
            router.delete(`/admin/menus/${item.id}`, { preserveState: true });
        }
    };

    const rows: { node: MenuNode; depth: number }[] = [];
    for (const root of items) {
        rows.push({ node: root, depth: 0 });
        for (const child of root.children ?? []) {
            rows.push({ node: child, depth: 1 });
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Menus" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Menus" description="Manage the storefront header navigation: dropdowns, mega menus, and click behavior." />
                    <Button onClick={() => openCreate()}>
                        <Plus className="mr-1 h-4 w-4" /> Add Menu Item
                    </Button>
                </div>

                <div className="bg-card rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted">
                                <tr>
                                    <th className="px-3 py-2 text-left">Title</th>
                                    <th className="px-3 py-2 text-left">Target</th>
                                    <th className="px-3 py-2 text-left">Click</th>
                                    <th className="px-3 py-2 text-left">Display</th>
                                    <th className="px-3 py-2 text-left">Status</th>
                                    <th className="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map(({ node, depth }) => (
                                    <tr key={node.id} className="border-t">
                                        <td className="px-3 py-2 font-medium">
                                            <span className="inline-flex items-center gap-2" style={{ paddingLeft: depth * 24 }}>
                                                {depth > 0 && <span className="text-muted-foreground">↳</span>}
                                                {node.title}
                                                {node.title_ar && <span className="text-muted-foreground font-normal">· {node.title_ar}</span>}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            <span className="bg-muted mr-1.5 rounded px-1.5 py-0.5 font-medium">{node.type}</span>
                                            <span className="font-mono">{node.type === 'url' ? node.url : `#${node.reference_id}`}</span>
                                        </td>
                                        <td className="px-3 py-2 text-xs">{(node.children ?? []).length > 0 ? node.click_behavior : '-'}</td>
                                        <td className="px-3 py-2 text-xs">{(node.children ?? []).length > 0 ? node.display : '-'}</td>
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                onClick={() => toggle(node.id)}
                                                className={`rounded-full px-2 py-0.5 text-xs ${node.is_active ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}
                                            >
                                                {node.is_active ? 'Active' : 'Hidden'}
                                            </button>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="flex justify-end gap-1">
                                                {depth === 0 && (
                                                    <Button size="sm" variant="outline" title="Add submenu item" onClick={() => openCreate(node.id)}>
                                                        <Plus className="h-3 w-3" />
                                                    </Button>
                                                )}
                                                <Button size="sm" variant="outline" onClick={() => openEdit(node)}>
                                                    <Pencil className="h-3 w-3" />
                                                </Button>
                                                <Button size="sm" variant="destructive" onClick={() => destroy(node)}>
                                                    <Trash2 className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground px-3 py-8 text-center">
                                            No menu items yet. The storefront header falls back to Home / Products links.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {dialogOpen && (
                <MenuDialog
                    key={editing ? `edit-${editing.id}` : `create-${presetParent ?? 'root'}`}
                    editing={editing}
                    presetParent={presetParent}
                    roots={items}
                    targets={targets}
                    onClose={() => setDialogOpen(false)}
                />
            )}
        </AppLayout>
    );
}

function MenuDialog({
    editing,
    presetParent,
    roots,
    targets,
    onClose,
}: {
    editing: MenuNode | null;
    presetParent: number | null;
    roots: MenuNode[];
    targets: Record<string, TargetOption[]>;
    onClose: () => void;
}) {
    const { data, setData, errors } = useForm({
        title: editing?.title ?? '',
        title_ar: editing?.title_ar ?? '',
        type: editing?.type ?? 'category',
        reference_id: editing?.reference_id != null ? String(editing.reference_id) : '',
        url: editing?.url ?? '',
        click_behavior: editing?.click_behavior ?? 'navigate',
        display: editing?.display ?? 'auto',
        parent_id: editing?.parent_id != null ? String(editing.parent_id) : (presetParent != null ? String(presetParent) : ''),
        promo_image: editing?.promo_image ?? '',
        promo_title: editing?.promo_title ?? '',
        promo_link: editing?.promo_link ?? '',
        is_active: editing?.is_active ?? true,
        sort_order: editing?.sort_order ?? 0,
    });

    const targetKey = data.type === 'page' ? 'pages' : `${data.type}s`;
    const options: TargetOption[] = data.type === 'url' ? [] : (targets[targetKey] ?? []);
    const [busy, setBusy] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...data,
            reference_id: data.reference_id === '' ? null : Number(data.reference_id),
            parent_id: data.parent_id === '' ? null : Number(data.parent_id),
        };
        const callbacks = {
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onSuccess: onClose,
        };
        if (editing) {
            router.put(`/admin/menus/${editing.id}`, payload, callbacks);
        } else {
            router.post('/admin/menus', payload, callbacks);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{editing ? `Edit: ${editing.title}` : presetParent ? 'Add Submenu Item' : 'Add Menu Item'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="menu-title">Title *</Label>
                        <Input id="menu-title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                        {errors.title && <p className="text-destructive text-xs">{errors.title}</p>}
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="menu-title-ar">Title (Arabic)</Label>
                        <Input id="menu-title-ar" value={data.title_ar} onChange={(e) => setData('title_ar', e.target.value)} dir="rtl" />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label>Links to</Label>
                            <Select
                                value={data.type}
                                onValueChange={(v) => {
                                    setData('type', v);
                                    setData('reference_id', '');
                                    setData('url', '');
                                }}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {TYPES.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.type && <p className="text-destructive text-xs">{errors.type}</p>}
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Parent (empty = top level)</Label>
                            <Select value={data.parent_id === '' ? '__root' : data.parent_id} onValueChange={(v) => setData('parent_id', v === '__root' ? '' : v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Top level" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__root">Top level</SelectItem>
                                    {roots
                                        .filter((r) => !editing || r.id !== editing.id)
                                        .map((r) => (
                                            <SelectItem key={r.id} value={String(r.id)}>
                                                {r.title}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            {errors.parent_id && <p className="text-destructive text-xs">{errors.parent_id}</p>}
                        </div>
                    </div>
                    {data.type === 'url' ? (
                        <div className="grid gap-1.5">
                            <Label htmlFor="menu-url">URL *</Label>
                            <Input id="menu-url" value={data.url} onChange={(e) => setData('url', e.target.value)} placeholder="/products or https://…" dir="ltr" />
                            {errors.url && <p className="text-destructive text-xs">{errors.url}</p>}
                        </div>
                    ) : (
                        <div className="grid gap-1.5">
                            <Label>{targetLabel(data.type)} *</Label>
                            <Select value={data.reference_id} onValueChange={(v) => setData('reference_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder={`Select ${targetLabel(data.type).toLowerCase()}`} />
                                </SelectTrigger>
                                <SelectContent>
                                    {options.map((o) => (
                                        <SelectItem key={o.id} value={String(o.id)}>
                                            {o.name ?? o.title ?? `#${o.id}`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.reference_id && <p className="text-destructive text-xs">{errors.reference_id}</p>}
                        </div>
                    )}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label>Click behavior</Label>
                            <Select value={data.click_behavior} onValueChange={(v) => setData('click_behavior', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="navigate">Navigate to link</SelectItem>
                                    <SelectItem value="expand">Expand submenu only</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Dropdown style</Label>
                            <Select value={data.display} onValueChange={(v) => setData('display', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="auto">Auto (mega when it has items)</SelectItem>
                                    <SelectItem value="mega">Mega panel</SelectItem>
                                    <SelectItem value="dropdown">Small dropdown</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="rounded-lg border p-3">
                        <p className="mb-2 text-sm font-medium">Mega promo card (optional)</p>
                        <div className="space-y-3">
                            <div className="grid gap-1.5">
                                <Label htmlFor="menu-promo-title">Promo title</Label>
                                <Input id="menu-promo-title" value={data.promo_title} onChange={(e) => setData('promo_title', e.target.value)} />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="menu-promo-image">Promo image URL</Label>
                                <Input id="menu-promo-image" value={data.promo_image} onChange={(e) => setData('promo_image', e.target.value)} placeholder="/storage/…" dir="ltr" />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="menu-promo-link">Promo link</Label>
                                <Input id="menu-promo-link" value={data.promo_link} onChange={(e) => setData('promo_link', e.target.value)} placeholder="/products…" dir="ltr" />
                                {errors.promo_link && <p className="text-destructive text-xs">{errors.promo_link}</p>}
                            </div>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="menu-sort">Sort order</Label>
                            <Input id="menu-sort" type="number" min={0} value={data.sort_order} onChange={(e) => setData('sort_order', Number(e.target.value))} />
                        </div>
                        <div className="flex items-end gap-2 pb-2">
                            <Switch checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
                            <Label>Active</Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={busy}>
                            {editing ? 'Save' : 'Create'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
