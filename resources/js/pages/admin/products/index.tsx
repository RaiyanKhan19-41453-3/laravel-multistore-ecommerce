import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, Image, Layers, Pencil, Plus, SlidersHorizontal, Star, Trash2, X } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Product {
    id: number;
    name: string;
    slug: string;
    type: string;
    sku: string;
    price: string;
    quantity: number;
    is_active: boolean;
    is_featured: boolean;
    brand: { id: number; name: string } | null;
    variants_count: number;
    images_count: number;
    discounts: { id: number; name: string; type: string; value: number }[];
    categories: { id: number; name: string }[];
}

interface PaginatedProducts {
    data: Product[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Filters {
    search?: string;
    brand_id?: string;
    category_id?: string;
    is_active?: string;
    is_featured?: string;
    discount_id?: string;
    sort?: string;
    direction?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Products', href: '/admin/products' },
];

function SortableHeader({ label, sortField, currentSort, currentDirection }: { label: string; sortField: string; currentSort?: string; currentDirection?: string }) {
    const isActive = currentSort === sortField;
    const newDirection = isActive && currentDirection === 'asc' ? 'desc' : 'asc';

    return (
        <Link
            href={route('admin.products.index', {
                ...usePage<SharedData>().props.filters,
                sort: sortField,
                direction: newDirection,
            })}
            className="inline-flex items-center gap-1 hover:underline"
        >
            {label}
            {isActive ? (
                currentDirection === 'asc' ? (
                    <ArrowUp className="h-3 w-3" />
                ) : (
                    <ArrowDown className="h-3 w-3" />
                )
            ) : (
                <span className="text-neutral-300 dark:text-neutral-600">
                    <ArrowUp className="h-3 w-3" />
                </span>
            )}
        </Link>
    );
}

interface SharedData {
    filters: Filters;
    [key: string]: unknown;
}

export default function ProductsIndex({
    products,
    brands,
    categories,
    discounts,
    filters,
}: {
    products: PaginatedProducts;
    brands: { id: number; name: string }[];
    categories: { id: number; name: string; parent_id: number | null }[];
    discounts: { id: number; name: string }[];
    filters: Filters;
}) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
        brand_id: filters.brand_id ?? '',
        category_id: filters.category_id ?? '',
        is_active: filters.is_active ?? '',
        is_featured: filters.is_featured ?? '',
        discount_id: filters.discount_id ?? '',
    });

    const hasActiveFilters = filters.search || filters.brand_id || filters.category_id || filters.is_active || filters.is_featured || filters.discount_id;

    const applyFilters: FormEventHandler = (e) => {
        e.preventDefault();
        get(route('admin.products.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        setData({
            search: '',
            brand_id: '',
            category_id: '',
            is_active: '',
            is_featured: '',
            discount_id: '',
        });
        router.get(
            route('admin.products.index'),
            {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const handleDelete = (product: Product) => {
        if (confirm(`Delete "${product.name}"?`)) {
            router.delete(route('admin.products.destroy', product.id));
        }
    };

    const handleToggle = (product: Product) => {
        router.post(route('admin.products.toggle', product.id));
    };

    const handleToggleFeatured = (product: Product) => {
        router.post(route('admin.products.toggle-featured', product.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Products" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Products" description="Manage your products" />
                    <Button asChild>
                        <Link href={route('admin.products.create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Product
                        </Link>
                    </Button>
                </div>

                <form onSubmit={applyFilters} className="flex flex-wrap items-end gap-3 rounded-xl border bg-neutral-50/50 p-4 dark:bg-neutral-800/30">
                    <div className="grid gap-1">
                        <label className="text-xs font-medium text-neutral-500">Search</label>
                        <Input
                            value={data.search}
                            onChange={(e) => setData('search', e.target.value)}
                            placeholder="Name or SKU..."
                            className="h-9 w-48"
                        />
                    </div>
                    <div className="grid gap-1">
                        <label className="text-xs font-medium text-neutral-500">Brand</label>
                        <Combobox
                            options={brands.map((b) => ({ value: String(b.id), label: b.name }))}
                            value={data.brand_id}
                            onChange={(v) => setData('brand_id', v)}
                            placeholder="All Brands"
                        />
                    </div>
                    <div className="grid gap-1">
                        <label className="text-xs font-medium text-neutral-500">Category</label>
                        <Combobox
                            options={categories.map((c) => ({
                                value: String(c.id),
                                label: c.parent_id ? `\u00A0\u00A0${c.name}` : c.name,
                            }))}
                            value={data.category_id}
                            onChange={(v) => setData('category_id', v)}
                            placeholder="All Categories"
                        />
                    </div>
                    <div className="grid gap-1">
                        <label className="text-xs font-medium text-neutral-500">Status</label>
                        <Combobox
                            options={[
                                { value: '1', label: 'Active' },
                                { value: '0', label: 'Inactive' },
                            ]}
                            value={data.is_active}
                            onChange={(v) => setData('is_active', v)}
                            placeholder="All"
                        />
                    </div>
                    <div className="grid gap-1">
                        <label className="text-xs font-medium text-neutral-500">Featured</label>
                        <Combobox
                            options={[
                                { value: '1', label: 'Featured' },
                                { value: '0', label: 'Not Featured' },
                            ]}
                            value={data.is_featured}
                            onChange={(v) => setData('is_featured', v)}
                            placeholder="All"
                        />
                    </div>
                    <div className="grid gap-1">
                        <label className="text-xs font-medium text-neutral-500">Discount</label>
                        <Combobox
                            options={discounts.map((d) => ({ value: String(d.id), label: d.name }))}
                            value={data.discount_id}
                            onChange={(v) => setData('discount_id', v)}
                            placeholder="All Discounts"
                        />
                    </div>
                    <Button type="submit" size="sm">
                        <SlidersHorizontal className="mr-1 h-3 w-3" />
                        Filter
                    </Button>
                    {hasActiveFilters && (
                        <Button type="button" size="sm" variant="ghost" onClick={clearFilters}>
                            <X className="mr-1 h-3 w-3" />
                            Clear
                        </Button>
                    )}
                </form>

                <div className="border-sidebar-border/70 relative overflow-hidden rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-neutral-50 dark:bg-neutral-800">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    <SortableHeader label="Name" sortField="name" currentSort={filters.sort} currentDirection={filters.direction} />
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    <SortableHeader label="SKU" sortField="sku" currentSort={filters.sort} currentDirection={filters.direction} />
                                </th>
                                <th className="px-4 py-3 font-medium">Brand</th>
                                <th className="px-4 py-3 font-medium">Categories</th>
                                <th className="px-4 py-3 font-medium">Discounts</th>
                                <th className="px-4 py-3 font-medium">
                                    <SortableHeader label="Price" sortField="price" currentSort={filters.sort} currentDirection={filters.direction} />
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    <SortableHeader label="Stock" sortField="quantity" currentSort={filters.sort} currentDirection={filters.direction} />
                                </th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Featured</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {products.data.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="px-4 py-8 text-center text-neutral-500">
                                        No products found.
                                    </td>
                                </tr>
                            ) : (
                                products.data.map((product) => (
                                    <tr key={product.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                        <td className="px-4 py-3 font-medium">
                                            <div className="flex items-center gap-2">
                                                {product.name}
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                        product.type === 'variable'
                                                            ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                                            : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'
                                                    }`}
                                                >
                                                    {product.type === 'variable' ? `${product.variants_count} variants` : 'Simple'}
                                                </span>
                                                <span className="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                                    <Image className="h-3 w-3" />
                                                    {product.images_count}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-neutral-500">{product.sku}</td>
                                        <td className="px-4 py-3 text-neutral-500">{product.brand?.name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            {product.categories.length > 0 ? (
                                                <div className="flex flex-wrap gap-1">
                                                    {product.categories.map((c) => (
                                                        <span key={c.id} className="inline-flex items-center rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800">
                                                            {c.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            ) : (
                                                <span className="text-neutral-400">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {product.discounts.length > 0 ? (
                                                <div className="flex flex-wrap gap-1">
                                                    {product.discounts.map((d) => (
                                                        <Link
                                                            key={d.id}
                                                            href={route('admin.discounts.show', d.id)}
                                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium hover:opacity-80 ${
                                                                d.type === 'percentage'
                                                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                            }`}
                                                        >
                                                            {d.name} {d.type === 'percentage' ? `${d.value}%` : `$${d.value}`}
                                                        </Link>
                                                    ))}
                                                </div>
                                            ) : (
                                                <span className="text-neutral-400">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">${product.price}</td>
                                        <td className="px-4 py-3">
                                            <span className={product.quantity <= 0 ? 'font-medium text-red-600' : ''}>{product.quantity}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Switch checked={product.is_active} onCheckedChange={() => handleToggle(product)} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <button
                                                onClick={() => handleToggleFeatured(product)}
                                                className="inline-flex items-center justify-center rounded-md p-1.5 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                            >
                                                <Star
                                                    className={`h-5 w-5 ${
                                                        product.is_featured
                                                            ? 'fill-amber-400 text-amber-400'
                                                            : 'text-neutral-300 dark:text-neutral-600'
                                                    }`}
                                                />
                                            </button>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1">
                                                <Link
                                                    href={route('admin.products.show', product.id)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                    title="View Product"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Link>
                                                <Link
                                                    href={route('admin.products.images.index', product.id)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                    title="Manage Images"
                                                >
                                                    <Image className="h-4 w-4" />
                                                </Link>
                                                {product.type === 'variable' && (
                                                    <Link
                                                        href={route('admin.products.variants.index', product.id)}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                        title="Manage Variants"
                                                    >
                                                        <Layers className="h-4 w-4" />
                                                    </Link>
                                                )}
                                                <Link
                                                    href={route('admin.products.edit', product.id)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                                <button
                                                    onClick={() => handleDelete(product)}
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

                {products.last_page > 1 && (
                    <div className="flex items-center justify-center gap-2">
                        {Array.from({ length: products.last_page }, (_, i) => i + 1).map((page) => (
                            <Link
                                key={page}
                                href={route('admin.products.index', { ...filters, page })}
                                className={`inline-flex h-8 w-8 items-center justify-center rounded-md text-sm ${
                                    page === products.current_page
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
        </AppLayout>
    );
}
