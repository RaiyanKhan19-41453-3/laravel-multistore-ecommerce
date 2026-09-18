import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit, Image, Layers, Star } from 'lucide-react';

interface ProductImage {
    id: number;
    path: string;
    alt_text: string | null;
    is_primary: boolean;
    sort_order: number;
    url: string;
}

interface ProductVariant {
    id: number;
    name: string;
    sku: string;
    price: number;
    compare_at_price: number | null;
    cost_price: number | null;
    is_active: boolean;
    values: { id: number; value: string; attribute: { id: number; name: string } }[];
}

interface Discount {
    id: number;
    name: string;
    type: string;
    value: number;
}

interface ProductShowData {
    id: number;
    name: string;
    sku: string;
    type: string;
    price: string;
    compare_at_price: string | null;
    cost_price: string | null;
    barcode: string | null;
    description: string | null;
    short_description: string | null;
    is_active: boolean;
    is_featured: boolean;
    brand: { id: number; name: string } | null;
    inventory: { quantity: number; reserved_quantity: number } | null;
    variants: ProductVariant[];
    categories: Category[];
    discounts: Discount[];
    images: ProductImage[];
}

interface Category {
    id: number;
    name: string;
    parent: { id: number; name: string } | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Products', href: '/admin/products' },
    { title: 'Details', href: '#' },
];

export default function ProductShow({ product }: { product: ProductShowData }) {
    const available = (product.inventory?.quantity ?? 0) - (product.inventory?.reserved_quantity ?? 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={product.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link href={route('admin.products.index')} className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                        <Heading title={product.name} description={product.sku} />
                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                            product.type === 'variable'
                                ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400'
                        }`}>
                            {product.type === 'variable' ? `${product.variants?.length ?? 0} variants` : 'Simple'}
                        </span>
                        {product.is_featured && (
                            <Badge className="bg-amber-500 text-white"><Star className="mr-1 h-3 w-3" /> Featured</Badge>
                        )}
                    </div>
                    <Link href={route('admin.products.edit', product.id)}>
                        <Button variant="outline" size="sm">
                            <Edit className="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-2 space-y-6">
                        <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                            <h3 className="mb-4 text-lg font-semibold">Details</h3>
                            <dl className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt className="text-neutral-500">Price</dt>
                                    <dd className="text-lg font-bold">${product.price}</dd>
                                </div>
                                {product.compare_at_price && (
                                    <div>
                                        <dt className="text-neutral-500">Compare At</dt>
                                        <dd className="text-lg font-bold text-neutral-400 line-through">${product.compare_at_price}</dd>
                                    </div>
                                )}
                                {product.cost_price && (
                                    <div>
                                        <dt className="text-neutral-500">Cost Price</dt>
                                        <dd className="text-lg font-bold">${product.cost_price}</dd>
                                    </div>
                                )}
                                <div>
                                    <dt className="text-neutral-500">Status</dt>
                                    <dd>
                                        <Badge variant={product.is_active ? 'default' : 'secondary'}>
                                            {product.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </dd>
                                </div>
                                {product.brand && (
                                    <div>
                                        <dt className="text-neutral-500">Brand</dt>
                                        <dd>{product.brand.name}</dd>
                                    </div>
                                )}
                                {product.barcode && (
                                    <div>
                                        <dt className="text-neutral-500">Barcode</dt>
                                        <dd className="font-mono">{product.barcode}</dd>
                                    </div>
                                )}
                            </dl>
                        </div>

                        {product.description && (
                            <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                                <h3 className="mb-4 text-lg font-semibold">Description</h3>
                                <div className="prose prose-sm dark:prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: product.description }} />
                            </div>
                        )}

                        {product.short_description && (
                            <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                                <h3 className="mb-4 text-lg font-semibold">Short Description</h3>
                                <p className="text-sm text-neutral-600 dark:text-neutral-400">{product.short_description}</p>
                            </div>
                        )}

                        {product.variants?.length > 0 && (
                            <div className="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                                <div className="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-800">
                                    <h3 className="text-lg font-semibold">Variants</h3>
                                    <Link href={route('admin.products.variants.index', product.id)}>
                                        <Button variant="outline" size="sm">
                                            <Layers className="mr-2 h-4 w-4" />
                                            Manage
                                        </Button>
                                    </Link>
                                </div>
                                <table className="w-full text-sm">
                                    <thead className="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-800/50">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-medium">Name</th>
                                            <th className="px-4 py-3 text-left font-medium">SKU</th>
                                            <th className="px-4 py-3 text-left font-medium">Options</th>
                                            <th className="px-4 py-3 text-right font-medium">Price</th>
                                            <th className="px-4 py-3 text-left font-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                        {product.variants.map((v: ProductVariant) => (
                                            <tr key={v.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/30">
                                                <td className="px-4 py-3 font-medium">{v.name}</td>
                                                <td className="px-4 py-3 font-mono text-neutral-500">{v.sku}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-wrap gap-1">
                                                        {v.values?.map((av) => (
                                                            <Badge key={av.id} variant="secondary">{av.attribute.name}: {av.value}</Badge>
                                                        ))}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium">${v.price}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={v.is_active ? 'default' : 'secondary'}>
                                                        {v.is_active ? 'Active' : 'Inactive'}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                            <h3 className="mb-4 text-lg font-semibold">Inventory</h3>
                            <dl className="space-y-3 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">In Stock</dt>
                                    <dd className="font-medium">{product.inventory?.quantity ?? 0}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">Reserved</dt>
                                    <dd className="font-medium">{product.inventory?.reserved_quantity ?? 0}</dd>
                                </div>
                                <div className="flex justify-between border-t border-neutral-200 pt-3 dark:border-neutral-800">
                                    <dt className="font-medium">Available</dt>
                                    <dd className={`font-bold ${available <= 0 ? 'text-red-600' : available <= 5 ? 'text-amber-600' : ''}`}>
                                        {available}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        {product.categories?.length > 0 && (
                            <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                                <h3 className="mb-4 text-lg font-semibold">Categories</h3>
                                <div className="flex flex-wrap gap-1">
                                    {product.categories.map((c) => (
                                        <Badge key={c.id} variant="secondary">{c.parent ? `${c.parent.name} → ` : ''}{c.name}</Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        {product.discounts?.length > 0 && (
                            <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                                <h3 className="mb-4 text-lg font-semibold">Active Discounts</h3>
                                <div className="flex flex-wrap gap-1">
                                    {product.discounts.map((d: Discount) => (
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
                            </div>
                        )}

                        {product.images?.length > 0 && (
                            <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-lg font-semibold">Images</h3>
                                    <Link href={route('admin.products.images.index', product.id)}>
                                        <Button variant="outline" size="sm">
                                            <Image className="mr-2 h-4 w-4" />
                                            Manage
                                        </Button>
                                    </Link>
                                </div>
                                <div className="grid grid-cols-3 gap-2">
                                    {product.images.slice(0, 6).map((img: ProductImage) => (
                                        <div key={img.id} className="relative aspect-square overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800">
                                            <img src={img.url} alt={img.alt_text ?? product.name} className="h-full w-full object-cover" />
                                            {img.is_primary && (
                                                <span className="absolute top-1 right-1 rounded bg-blue-600 px-1 py-0.5 text-[10px] font-medium text-white">Primary</span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                {product.images.length > 6 && (
                                    <p className="mt-2 text-center text-xs text-neutral-500">+{product.images.length - 6} more images</p>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
