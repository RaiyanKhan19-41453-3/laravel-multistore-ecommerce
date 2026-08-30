import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface AttributeValue {
    id: number;
    value: string;
    slug: string;
    is_active: boolean;
    sort_order: number;
}

interface Attribute {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    sort_order: number;
    values: AttributeValue[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Attributes', href: '/admin/attributes' },
];

function AttributeForm({ attribute, onClose }: { attribute?: Attribute | null; onClose: () => void }) {
    const isEdit = !!attribute;

    const { data, setData, post, put, errors, processing } = useForm({
        name: attribute?.name ?? '',
        slug: attribute?.slug ?? '',
        is_active: attribute?.is_active ?? true,
        sort_order: attribute?.sort_order ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.attributes.update', attribute.id), {
                onSuccess: () => onClose(),
            });
        } else {
            post(route('admin.attributes.store'), {
                onSuccess: () => onClose(),
            });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required placeholder="e.g. Color, Size, Material" />
                <InputError className="mt-2" message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="slug">Slug</Label>
                <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder="auto-generated-from-name" />
                <p className="text-xs text-neutral-500">Leave blank to auto-generate from name.</p>
                <InputError className="mt-2" message={errors.slug} />
            </div>

            <div className="flex items-center gap-4">
                <div className="flex items-center gap-2">
                    <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                    <Label htmlFor="is_active">Active</Label>
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="sort_order">Sort Order</Label>
                <Input id="sort_order" type="number" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)} min={0} className="w-32" />
                <InputError className="mt-2" message={errors.sort_order} />
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>
                    Cancel
                </Button>
                <Button disabled={processing}>{isEdit ? 'Update' : 'Create'}</Button>
            </DialogFooter>
        </form>
    );
}

function ValueForm({
    attribute,
    value,
    onClose,
}: {
    attribute: Attribute;
    value?: AttributeValue | null;
    onClose: () => void;
}) {
    const isEdit = !!value;

    const { data, setData, post, put, errors, processing } = useForm({
        value: value?.value ?? '',
        slug: value?.slug ?? '',
        is_active: value?.is_active ?? true,
        sort_order: value?.sort_order ?? 0,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.attributes.values.update', [attribute.id, value.id]), {
                onSuccess: () => onClose(),
            });
        } else {
            post(route('admin.attributes.values.store', attribute.id), {
                onSuccess: () => onClose(),
            });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="value">Value</Label>
                <Input id="value" value={data.value} onChange={(e) => setData('value', e.target.value)} required placeholder="e.g. Red, Large, Cotton" />
                <InputError className="mt-2" message={errors.value} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="slug">Slug</Label>
                <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder="auto-generated-from-value" />
                <p className="text-xs text-neutral-500">Leave blank to auto-generate from value.</p>
                <InputError className="mt-2" message={errors.slug} />
            </div>

            <div className="flex items-center gap-4">
                <div className="flex items-center gap-2">
                    <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                    <Label htmlFor="is_active">Active</Label>
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="sort_order">Sort Order</Label>
                <Input id="sort_order" type="number" value={data.sort_order} onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)} min={0} className="w-32" />
                <InputError className="mt-2" message={errors.sort_order} />
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>
                    Cancel
                </Button>
                <Button disabled={processing}>{isEdit ? 'Update' : 'Create'}</Button>
            </DialogFooter>
        </form>
    );
}

export default function AttributesIndex({ attributes }: { attributes: Attribute[] }) {
    const [expandedIds, setExpandedIds] = useState<number[]>([]);
    const [showCreateAttr, setShowCreateAttr] = useState(false);
    const [editAttribute, setEditAttribute] = useState<Attribute | null>(null);
    const [showCreateValue, setShowCreateValue] = useState<Attribute | null>(null);
    const [editValue, setEditValue] = useState<{ attribute: Attribute; value: AttributeValue } | null>(null);

    const toggleExpand = (id: number) => {
        setExpandedIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    };

    const handleDeleteAttr = (attribute: Attribute) => {
        if (confirm(`Delete "${attribute.name}" and all its values?`)) {
            router.delete(route('admin.attributes.destroy', attribute.id));
        }
    };

    const handleToggleAttr = (attribute: Attribute) => {
        router.post(route('admin.attributes.toggle', attribute.id));
    };

    const handleDeleteValue = (attribute: Attribute, value: AttributeValue) => {
        if (confirm(`Delete "${value.value}"?`)) {
            router.delete(route('admin.attributes.values.destroy', [attribute.id, value.id]));
        }
    };

    const handleToggleValue = (attribute: Attribute, value: AttributeValue) => {
        router.post(route('admin.attributes.values.toggle', [attribute.id, value.id]));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attributes" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Attributes" description="Manage product attributes like Color, Size, Material" />
                    <Button onClick={() => setShowCreateAttr(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Attribute
                    </Button>
                </div>

                <div className="border-sidebar-border/70 relative overflow-hidden rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th className="w-8 px-4 py-3"></th>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Slug</th>
                                <th className="px-4 py-3 font-medium">Values</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {attributes.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                        No attributes yet. Create your first attribute to get started.
                                    </td>
                                </tr>
                            ) : (
                                attributes.map((attribute) => (
                                    <>
                                        <tr key={attribute.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                            <td className="px-4 py-3">
                                                <button onClick={() => toggleExpand(attribute.id)} className="text-neutral-400 hover:text-neutral-600">
                                                    {expandedIds.includes(attribute.id) ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                                                </button>
                                            </td>
                                            <td className="px-4 py-3 font-medium">{attribute.name}</td>
                                            <td className="px-4 py-3 text-neutral-500">{attribute.slug}</td>
                                            <td className="px-4 py-3 text-neutral-500">{attribute.values.length}</td>
                                            <td className="px-4 py-3">
                                                <Switch checked={attribute.is_active} onCheckedChange={() => handleToggleAttr(attribute)} />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-1">
                                                    <button
                                                        onClick={() => {
                                                            setEditAttribute(attribute);
                                                        }}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteAttr(attribute)}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        {expandedIds.includes(attribute.id) && (
                                            <tr key={`${attribute.id}-values`}>
                                                <td colSpan={6} className="bg-neutral-50/50 px-4 py-3 dark:bg-neutral-800/30">
                                                    <div className="ml-8">
                                                        <div className="mb-2 flex items-center justify-between">
                                                            <h4 className="text-sm font-medium text-neutral-600 dark:text-neutral-400">Values</h4>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => setShowCreateValue(attribute)}
                                                            >
                                                                <Plus className="mr-1 h-3 w-3" />
                                                                Add Value
                                                            </Button>
                                                        </div>
                                                        {attribute.values.length === 0 ? (
                                                            <p className="text-xs text-neutral-500">No values yet.</p>
                                                        ) : (
                                                            <div className="space-y-1">
                                                                {attribute.values.map((value) => (
                                                                    <div key={value.id} className="flex items-center justify-between rounded-md bg-white px-3 py-2 dark:bg-neutral-900">
                                                                        <div className="flex items-center gap-3">
                                                                            <Switch
                                                                                checked={value.is_active}
                                                                                onCheckedChange={() => handleToggleValue(attribute, value)}
                                                                            />
                                                                            <span className="text-sm">{value.value}</span>
                                                                            <span className="text-xs text-neutral-500">{value.slug}</span>
                                                                        </div>
                                                                        <div className="flex items-center gap-1">
                                                                            <button
                                                                                onClick={() => setEditValue({ attribute, value })}
                                                                                className="inline-flex h-6 w-6 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                                            >
                                                                                <Pencil className="h-3 w-3" />
                                                                            </button>
                                                                            <button
                                                                                onClick={() => handleDeleteValue(attribute, value)}
                                                                                className="inline-flex h-6 w-6 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                                            >
                                                                                <Trash2 className="h-3 w-3" />
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={showCreateAttr} onOpenChange={setShowCreateAttr}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Attribute</DialogTitle>
                        <DialogDescription>Add a new attribute like Color, Size, or Material.</DialogDescription>
                    </DialogHeader>
                    <AttributeForm onClose={() => setShowCreateAttr(false)} />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editAttribute} onOpenChange={(open) => !open && setEditAttribute(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Attribute</DialogTitle>
                        <DialogDescription>Update attribute details.</DialogDescription>
                    </DialogHeader>
                    {editAttribute && <AttributeForm attribute={editAttribute} onClose={() => setEditAttribute(null)} />}
                </DialogContent>
            </Dialog>

            <Dialog open={!!showCreateValue} onOpenChange={(open) => !open && setShowCreateValue(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Value to {showCreateValue?.name}</DialogTitle>
                        <DialogDescription>Add a new option for this attribute.</DialogDescription>
                    </DialogHeader>
                    {showCreateValue && <ValueForm attribute={showCreateValue} onClose={() => setShowCreateValue(null)} />}
                </DialogContent>
            </Dialog>

            <Dialog open={!!editValue} onOpenChange={(open) => !open && setEditValue(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Value</DialogTitle>
                        <DialogDescription>Update attribute value details.</DialogDescription>
                    </DialogHeader>
                    {editValue && <ValueForm attribute={editValue.attribute} value={editValue.value} onClose={() => setEditValue(null)} />}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
