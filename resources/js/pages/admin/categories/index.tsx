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
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Category {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image: string | null;
    is_active: boolean;
    sort_order: number;
    parent_id: number | null;
    parent?: { id: number; name: string } | null;
    children_count?: number;
    products_count?: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Categories', href: '/admin/categories' },
];

function CategoryForm({
    category,
    parentCategories,
    parentId,
    onClose,
}: {
    category?: Category | null;
    parentCategories: { id: number; name: string; parent_id: number | null }[];
    parentId?: number | null;
    onClose: () => void;
}) {
    const isEdit = !!category;

    const { data, setData, post, errors, processing, transform } = useForm({
        parent_id: category?.parent_id ?? parentId ?? '',
        name: category?.name ?? '',
        slug: category?.slug ?? '',
        description: category?.description ?? '',
        is_active: category?.is_active ?? true,
        sort_order: category?.sort_order ?? 0,
        image_file: null as File | null,
        remove_image: false as boolean,
    });

    const availableParents = parentCategories.filter((pc) => pc.id !== category?.id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (isEdit) {
            // POST with spoofed PUT: PHP discards multipart bodies on real
            // PUT requests, so the image would never arrive otherwise.
            transform((formData) => ({ ...formData, _method: 'PUT' }));
            post(route('admin.categories.update', category.id), {
                onSuccess: () => onClose(),
            });
        } else {
            post(route('admin.categories.store'), {
                onSuccess: () => onClose(),
            });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor="parent_id">Parent Category</Label>
                <select
                    id="parent_id"
                    value={data.parent_id}
                    onChange={(e) => setData('parent_id', e.target.value || '')}
                    className="flex h-9 w-full rounded-md border border-neutral-200 bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-neutral-500 focus:border-neutral-950 focus:outline-none focus:ring-1 focus:ring-neutral-950 dark:border-neutral-800 dark:placeholder:text-neutral-400 dark:focus:border-neutral-100 dark:focus:ring-neutral-100"
                >
                    <option value="">None (Top Level)</option>
                    {availableParents.map((pc) => (
                        <option key={pc.id} value={pc.id}>
                            {pc.name}
                        </option>
                    ))}
                </select>
                <InputError className="mt-2" message={errors.parent_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    placeholder="Category name"
                />
                <InputError className="mt-2" message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="slug">Slug</Label>
                <Input
                    id="slug"
                    value={data.slug}
                    onChange={(e) => setData('slug', e.target.value)}
                    placeholder="auto-generated-from-name"
                />
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
                <Label htmlFor="image">Cover image</Label>
                <div className="flex items-center gap-3">
                    {category?.image && !data.image_file && !data.remove_image && (
                        <img
                            src={category.image.startsWith('/') || category.image.startsWith('http') ? category.image : `/storage/${category.image}`}
                            alt={category.name}
                            className="h-12 w-12 rounded-lg border object-cover"
                        />
                    )}
                    <Input
                        id="image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={(e) => {
                            setData('image_file', e.target.files?.[0] ?? null);
                            setData('remove_image', false);
                        }}
                        className="cursor-pointer"
                    />
                    {isEdit && category?.image && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setData('image_file', null);
                                setData('remove_image', !data.remove_image);
                            }}
                        >
                            {data.remove_image ? 'Keep image' : 'Remove'}
                        </Button>
                    )}
                </div>
                <InputError className="mt-2" message={errors.image_file} />
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

function CategoryRow({
    category,
    onEdit,
    onDelete,
    onToggle,
    onCreateChild,
}: {
    category: Category;
    onEdit: (c: Category) => void;
    onDelete: (c: Category) => void;
    onToggle: (c: Category) => void;
    onCreateChild: (id: number) => void;
}) {
    return (
        <tr className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    {category.image ? (
                                                        <img
                                                            src={category.image.startsWith('/') || category.image.startsWith('http') ? category.image : `/storage/${category.image}`}
                                                            alt={category.name}
                                                            className="h-9 w-9 shrink-0 rounded-lg border object-cover"
                                                        />
                                                    ) : (
                                                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-sm font-bold dark:bg-neutral-800">
                                                            {category.name.charAt(0).toUpperCase()}
                                                        </span>
                                                    )}
                                                    <div className="min-w-0">
                                                        <p className="truncate font-medium">{category.name}</p>
                                                        <p className="truncate text-xs text-neutral-500">{category.slug}</p>
                                                    </div>
                                                </div>
                                            </td>
            <td className="px-4 py-3 text-neutral-500">
                {category.parent ? category.parent.name : <span className="text-xs">-</span>}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-2">
                    <span className="inline-flex min-w-8 justify-center rounded-md bg-neutral-100 px-2 py-0.5 text-xs font-semibold tabular-nums dark:bg-neutral-800">
                        {category.products_count ?? 0}
                    </span>
                    <span className="text-xs text-neutral-500">
                        {category.children_count ?? 0} {category.children_count === 1 ? 'child' : 'children'}
                    </span>
                </div>
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-2">
                    <Switch
                        checked={category.is_active}
                        onCheckedChange={() => onToggle(category)}
                    />
                    <span className="text-xs text-neutral-500">
                        {category.is_active ? 'Active' : 'Hidden'}
                    </span>
                </div>
            </td>
            <td className="px-4 py-3 text-sm tabular-nums text-neutral-500">{category.sort_order}</td>
            <td className="px-4 py-3">
                <div className="flex items-center justify-end gap-1">
                    <button
                        onClick={() => onCreateChild(category.id)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        title="Add child category"
                    >
                        <Plus className="h-4 w-4" />
                    </button>
                    <button
                        onClick={() => onEdit(category)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                    >
                        <Pencil className="h-4 w-4" />
                    </button>
                    <button
                        onClick={() => onDelete(category)}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"
                    >
                        <Trash2 className="h-4 w-4" />
                    </button>
                </div>
            </td>
        </tr>
    );
}

export default function CategoriesIndex({
    categories,
    allCategories,
}: {
    categories: Category[];
    allCategories: { id: number; name: string; parent_id: number | null }[];
}) {
    const [showCreate, setShowCreate] = useState(false);
    const [editCategory, setEditCategory] = useState<Category | null>(null);
    const [createParentId, setCreateParentId] = useState<number | null>(null);

    const handleCreateChild = (parentId: number) => {
        setCreateParentId(parentId);
        setShowCreate(true);
    };

    const handleEdit = (category: Category) => {
        setEditCategory(category);
    };

    const handleDelete = (category: Category) => {
        if (confirm(`Delete "${category.name}"? Children will be moved to the top level.`)) {
            router.delete(route('admin.categories.destroy', category.id));
        }
    };

    const handleToggle = (category: Category) => {
        router.post(route('admin.categories.toggle', category.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Categories" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Categories" description="Manage your product categories" />
                    <Button
                        onClick={() => {
                            setCreateParentId(null);
                            setShowCreate(true);
                        }}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        Create Category
                    </Button>
                </div>

                <div className="border-sidebar-border/70 relative overflow-hidden rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th className="px-4 py-3 font-medium">Category</th>
                                <th className="px-4 py-3 font-medium">Parent</th>
                                <th className="px-4 py-3 font-medium">Contents</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Sort</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {categories.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                        No categories yet. Create your first category to get started.
                                    </td>
                                </tr>
                            ) : (
                                categories.map((category) => (
                                    <CategoryRow
                                        key={category.id}
                                        category={category}
                                        onEdit={handleEdit}
                                        onDelete={handleDelete}
                                        onToggle={handleToggle}
                                        onCreateChild={handleCreateChild}
                                    />
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={showCreate} onOpenChange={setShowCreate}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Category</DialogTitle>
                        <DialogDescription>Add a new category to your store.</DialogDescription>
                    </DialogHeader>
                    <CategoryForm
                        parentCategories={allCategories}
                        parentId={createParentId}
                        onClose={() => setShowCreate(false)}
                    />
                </DialogContent>
            </Dialog>

            <Dialog open={!!editCategory} onOpenChange={(open) => !open && setEditCategory(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Category</DialogTitle>
                        <DialogDescription>Update category details.</DialogDescription>
                    </DialogHeader>
                    {editCategory && (
                        <CategoryForm category={editCategory} parentCategories={allCategories} onClose={() => setEditCategory(null)} />
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
