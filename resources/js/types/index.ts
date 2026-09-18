import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    permissions: string[];
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
    permission?: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    locale: string;
    direction: 'rtl' | 'ltr';
    store: {
        id?: string;
        slug?: string;
        name: string;
        country: string;
        currency: string;
        currencySymbol: string;
        locale: string;
    };
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
    review_summary: { total: number; average: number };
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
    review_summary: { total: number; average: number };
    meta_title?: string;
    meta_description?: string;
}

export interface Review {
    id: number;
    rating: number;
    title: string | null;
    body: string | null;
    is_approved: boolean;
    created_at: string;
    user: { id: number; name: string };
}

export interface ReviewSummary {
    total: number;
    average: number;
    distribution: Record<number, number>;
}

export interface CmsPage {
    id: number;
    title: string;
    slug: string;
    body: string;
    meta_title: string | null;
    meta_description: string | null;
}

export interface WishlistItem {
    id: number;
    created_at: string;
    product: {
        id: number;
        name: string;
        slug: string;
        price: number;
        compare_at_price: number | null;
        brand: { name: string; slug: string } | null;
        primary_image: string | null;
        in_stock: boolean;
    } | null;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
}

export interface AccountOrderItem {
    id: number;
    name: string;
    sku: string;
    unit_price: number;
    quantity: number;
    subtotal: number;
    total: number;
    product: { id: number; name: string; slug: string } | null;
}

export interface AccountShipment {
    id: number;
    courier: string | null;
    tracking_number: string | null;
    status: string;
    note: string | null;
    created_at: string;
}

export interface AccountOrder {
    id: number;
    order_number: string;
    status: string;
    subtotal: number;
    discount_total: number;
    shipping_cost: number;
    tax_amount: number;
    total: number;
    coupon_code: string | null;
    created_at: string;
    paid_at: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    shipping_name: string;
    shipping_phone: string;
    shipping_address: string;
    shipping_city: string;
    shipping_state: string;
    shipping_country: string;
    notes: string | null;
    items: AccountOrderItem[];
    payments: { id: number; method: string; status: string; amount: number }[];
    shipments: AccountShipment[];
}

export interface AccountAddress {
    id: number;
    name: string;
    phone: string;
    address: string;
    city: string;
    state: string;
    postal_code: string | null;
    country: string | null;
    is_default: boolean;
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
