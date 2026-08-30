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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Products', href: '/admin/products' },
    { title: 'Create', href: '/admin/products/create' },
];

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

export default function ProductCreate({
    brands,
    categories,
    attributes,
}: {
    brands: Brand[];
    categories: Category[];
    attributes: Attribute[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Product" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Create Product</h1>
                    <p className="text-sm text-neutral-500">Add a new product to your store</p>
                </div>
                <ProductForm brands={brands} categories={categories} attributes={attributes} />
            </div>
        </AppLayout>
    );
}
