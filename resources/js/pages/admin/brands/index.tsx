import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Brand {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo: string | null;
    is_active: boolean;
    sort_order: number;
    products_count: number;
}

interface PaginatedBrands {
    data: Brand[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Brands', href: '/admin/brands' },
];

function BrandForm({ brand, onClose }: { brand?: Brand | null; onClose: () => void }) {
    const isEdit = !!brand;

    const { data, setData, post, errors, processing, transform } = useForm({
        name: brand?.name ?? '',
        slug: brand?.slug ?? '',
        description: brand?.description ?? '',
        is_active: brand?.is_active ?? true,
        sort_order: brand?.sort_order ?? 0,
        logo_file: null as File | null,
        remove_logo: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            // POST with spoofed PUT: PHP discards multipart bodies on real
            // PUT requests, so the logo would never arrive otherwise.
            transform((formData) => ({ ...formData, _method: 'PUT' }));
            post(route('admin.brands.update', brand.id), {
                onSuccess: () => onClose(),
            });
        } else {
            post(route('admin.brands.store'), {
                onSuccess: () => onClose(),
            });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required placeholder="Brand name" />
                <InputError className="mt-2" message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="slug">Slug</Label>
                <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder="auto-generated-from-name" />
                <p className="text-xs text-neutral-500">Leave blank to auto-generate from name.</p>
                <InputError className="mt-2" message={errors.slug} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <textarea
                    id="description"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    rows={2}
                    placeholder="Optional description"
                    className="flex min-h-[60px] w-full rounded-md border border-neutral-200 bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-neutral-500 focus:border-neutral-950 focus:outline-none focus:ring-1 focus:ring-neutral-950 dark:border-neutral-800 dark:placeholder:text-neutral-400 dark:focus:border-neutral-100 dark:focus:ring-neutral-100"
                />
                <InputError className="mt-2" message={errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="logo">Logo</Label>
                <div className="flex items-center gap-3">
                    {brand?.logo && !data.logo_file && !data.remove_logo && (
                        <img
                            src={brand.logo.startsWith('/') || brand.logo.startsWith('http') ? brand.logo : `/storage/${brand.logo}`}
                            alt={brand.name}
                            className="h-12 w-12 rounded-lg border object-cover"
                        />
                    )}
                    <Input
                        id="logo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(e) => {
                            setData('logo_file', e.target.files?.[0] ?? null);
                            setData('remove_logo', false);
                        }}
                        className="cursor-pointer"
                    />
                    {isEdit && brand?.logo && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setData('logo_file', null);
                                setData('remove_logo', !data.remove_logo);
                            }}
                        >
                            {data.remove_logo ? 'Keep logo' : 'Remove'}
                        </Button>
                    )}
                </div>
                <InputError className="mt-2" message={errors.logo_file} />
            </div>

            <div className="flex items-center gap-4">
                <div className="flex items-center gap-2">
                    <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
                    <Label htmlFor="is_active">Active</Label>
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="sort_order">Sort Order</Label>
                <Input
                    id="sort_order"
                    type="number"
                    value={data.sort_order}
                    onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                    min={0}
                    className="w-32"
                />
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

export default function BrandsIndex({ brands }: { brands: PaginatedBrands }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editBrand, setEditBrand] = useState<Brand | null>(null);

    const handleDelete = (brand: Brand) => {
        if (confirm(`Delete "${brand.name}"?`)) {
            router.delete(route('admin.brands.destroy', brand.id));
        }
    };

    const handleToggle = (brand: Brand) => {
        router.post(route('admin.brands.toggle', brand.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Brands" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Brands" description="Manage your product brands" />
                    <Button onClick={() => setShowCreate(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Brand
                    </Button>
                </div>

                <div className="border-sidebar-border/70 relative overflow-hidden rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th className="px-4 py-3 font-medium">Brand</th>
                                <th className="px-4 py-3 font-medium">Products</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Sort</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {brands.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">
                                        No brands yet. Create your first brand to get started.
                                    </td>
                                </tr>
                            ) : (
                                brands.data.map((brand) => (
                                    <tr key={brand.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                {brand.logo ? (
                                                    <img
                                                        src={brand.logo.startsWith('/') || brand.logo.startsWith('http') ? brand.logo : `/storage/${brand.logo}`}
                                                        alt={brand.name}
                                                        className="h-9 w-9 shrink-0 rounded-lg border object-cover"
                                                    />
                                                ) : (
                                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-sm font-bold dark:bg-neutral-800">
                                                        {brand.name.charAt(0).toUpperCase()}
                                                    </span>
                                                )}
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">{brand.name}</p>
                                                    <p className="truncate text-xs text-neutral-500">{brand.slug}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="inline-flex min-w-8 justify-center rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-semibold tabular-nums dark:bg-neutral-800">
                                                {brand.products_count}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <Switch
                                                    checked={brand.is_active}
                                                    onCheckedChange={() => handleToggle(brand)}
                                                />
                                                <span className="text-xs text-neutral-500">
                                                    {brand.is_active ? 'Active' : 'Hidden'}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm tabular-nums text-neutral-500">{brand.sort_order}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <button
                                                    onClick={() => setEditBrand(brand)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(brand)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {brands.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2">
                        {Array.from({ length: brands.last_page }, (_, i) => i + 1).map((page) => (
                            <Link
                                key={page}
                                href={route('admin.brands.index', { page })}
                                className={`inline-flex h-8 w-8 items-center justify-center rounded-md text-sm ${
                                    page === brands.current_page
                                        ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
                                        : 'hover:bg-neutral-100 dark:hover:bg-neutral-800'
                                }`}
                            >
                                {page}
                            </Link>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={showCreate} onOpenChange={setShowCreate}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Brand</DialogTitle>
                        <DialogDescription>Add a new brand to your store.</DialogDescription>
                    </DialogHeader>
                    <BrandForm onClose={() => setShowCreate(false)} />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editBrand} onOpenChange={(open) => !open && setEditBrand(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Brand</DialogTitle>
                        <DialogDescription>Update brand details.</DialogDescription>
                    </DialogHeader>
                    {editBrand && <BrandForm brand={editBrand} onClose={() => setEditBrand(null)} />}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
