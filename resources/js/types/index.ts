import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface ProductSummary {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    sku: string;
    type: 'simple' | 'variable';
    price: number;
    compare_at_price: number | null;
    is_featured: boolean;
    brand: { id: number; name: string; slug: string } | null;
    primary_image: string | null;
    variants_count: number;
}

export interface ProductVariantValue {
    id: number;
    value: string;
    attribute: { id: number; name: string };
}

export interface ProductVariant {
    id: number;
    name: string;
    sku: string;
    price: number;
    compare_at_price: number | null;
    is_active: boolean;
    values: ProductVariantValue[];
    image: string | null;
    inventory: { quantity: number; available: number; in_stock: boolean };
}

export interface ProductDetail {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    short_description: string | null;
    sku: string;
    type: 'simple' | 'variable';
    price: number;
    compare_at_price: number | null;
    is_featured: boolean;
    brand: { id: number; name: string; slug: string } | null;
    categories: { id: number; name: string; slug: string }[];
    images: { id: number; url: string; urls: Record<string, string>; alt_text: string | null; is_primary: boolean; sort_order: number }[];
    primary_image: string | null;
    variants: ProductVariant[];
    inventory: { quantity: number; available: number; in_stock: boolean } | null;
    discount: { id: number; name: string; type: string; value: number } | null;
    variant_discounts: Record<number, { id: number; name: string; type: string; value: number }>;
}

export interface CartProduct {
    id: number;
    name: string;
    slug: string;
    sku: string;
}

export interface CartVariant {
    id: number;
    name: string;
    sku: string;
}

export interface ItemDiscount {
    id: number;
    name: string;
    type: string;
    level: 'product' | 'category' | 'brand' | 'sitewide';
    target: string | null;
    amount: number;
}

export interface CartItem {
    id: number;
    product: CartProduct;
    product_variant: CartVariant | null;
    quantity: number;
    unit_price: number;
    line_total: number;
    image: string | null;
    item_discounts: ItemDiscount[];
}

export interface CartDiscount {
    id: number;
    name: string;
    type: string;
    value: number;
    amount: number;
    level: 'product' | 'category' | 'brand' | 'sitewide';
    target: string | null;
}

export interface CartSummary {
    id: number;
    items: CartItem[];
    subtotal: number;
    discount_total: number;
    total: number;
    item_count: number;
    coupon_code: string | null;
    discount_details: CartDiscount | CartDiscount[] | null;
}


