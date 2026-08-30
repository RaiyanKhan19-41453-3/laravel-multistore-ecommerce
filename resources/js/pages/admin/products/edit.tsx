import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import ProductForm from './product-form';

interface Category {
    id: number;
    name: string;
    parent_id: number | null;
}

interface Brand {
    id: number;
    name: string;
}

interface AttributeValue {
    id: number;
    value: string;
    slug: string;
}

interface Attribute {
    id: number;
    name: string;
    slug: string;
    values: AttributeValue[];
}

interface Variant {
    id: number;
    name: string;
    sku: string;
    price: string;
    compare_at_price: string | null;
    cost_price: string | null;
    quantity: string;
    is_active: boolean;
    values: { id: number }[];
}

interface Product {
    id: number;
    name: string;
    slug: string;
    brand_id: string;
    type: string;
    category_ids: number[];
    attribute_ids: number[];
    description: string;
    short_description: string;
    sku: string;
    barcode: string;
    price: string;
    compare_at_price: string;
    cost_price: string;
    quantity: string;
    is_active: boolean;
    is_featured: boolean;
    sort_order: string;
    variants: Variant[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Products', href: '/admin/products' },
    { title: 'Edit', href: '/admin/products/edit' },
];

export default function ProductEdit({
    product,
    brands,
    categories,
    attributes,
}: {
    product: Product;
    brands: Brand[];
    categories: Category[];
    attributes: Attribute[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${product.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Edit {product.name}</h1>
                    <p className="text-sm text-neutral-500">Update product details</p>
                </div>
                <ProductForm product={product} brands={brands} categories={categories} attributes={attributes} />
            </div>
        </AppLayout>
    );
}
