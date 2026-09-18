import { Button } from '@/components/ui/button';
import ImageUpload from '@/components/ui/image-upload';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Image } from 'lucide-react';

interface ProductImageData {
    id: number;
    filename: string;
    mime_type: string;
    size: number;
    width: number | null;
    height: number | null;
    alt_text: string | null;
    sort_order: number;
    is_primary: boolean;
    paths: Record<string, string>;
    urls: Record<string, string>;
}

interface Variant {
    id: number;
    name: string;
    sku: string;
    images: ProductImageData[];
}

interface Product {
    id: number;
    name: string;
    sku: string;
    type: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Products', href: '/admin/products' },
    { title: 'Images', href: '#' },
];

export default function ProductImages({
    product,
    images,
    variants,
}: {
    product: Product;
    images: ProductImageData[];
    variants: Variant[];
}) {
    const isVariable = product.type === 'variable';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Images — ${product.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <Link
                                href={route('admin.products.index')}
                                className="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800"
                            >
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight">{product.name}</h1>
                                <p className="text-sm text-neutral-500">
                                    SKU: {product.sku} &middot; Manage product images
                                </p>
                            </div>
                        </div>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={route('admin.products.edit', product.id)}>
                            Edit Product
                        </Link>
                    </Button>
                </div>

                <div className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="mb-6 flex items-center gap-2">
                        <Image className="h-5 w-5 text-neutral-500" />
                        <h2 className="text-base font-semibold">Product Images</h2>
                        <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                            {images.length}
                        </span>
                    </div>

                    <ImageUpload
                        productId={product.id}
                        images={images}
                    />
                </div>

                {isVariable && variants.length > 0 && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Variant Images</h2>
                        {variants.map((variant) => (
                            <div
                                key={variant.id}
                                className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
                            >
                                <div className="mb-4 flex items-center justify-between">
                                    <div>
                                        <h3 className="text-sm font-semibold">{variant.name}</h3>
                                        <p className="text-xs text-neutral-500">SKU: {variant.sku}</p>
                                    </div>
                                    <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400">
                                        {variant.images.length} images
                                    </span>
                                </div>
                                                                <ImageUpload
                                    productId={product.id}
                                    variantId={variant.id}
                                    images={variant.images}
                                />
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
