import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Bike,
    BookOpen,
    Folder,
    Globe,
    Landmark,
    LayoutGrid,
    LayoutTemplate,
    ListTree,
    Package,
    Percent,
    Printer,
    Receipt,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Star,
    Store,
    Tags,
    Trophy,
    Truck,
    Users,
    Warehouse,
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/admin/dashboard',
        icon: LayoutGrid,
        permission: 'reports.view|orders.view|catalog.view',
    },
    {
        title: 'Categories',
        url: '/admin/categories',
        icon: Tags,
        permission: 'catalog.view|catalog.manage',
    },
    {
        title: 'Brands',
        url: '/admin/brands',
        icon: Trophy,
        permission: 'catalog.view|catalog.manage',
    },
    {
        title: 'Attributes',
        url: '/admin/attributes',
        icon: SlidersHorizontal,
        permission: 'catalog.view|catalog.manage',
    },
    {
        title: 'Products',
        url: '/admin/products',
        icon: Package,
        permission: 'catalog.view|catalog.manage',
    },
    {
        title: 'Print Labels',
        url: '/admin/products/labels',
        icon: Printer,
        permission: 'catalog.view|catalog.manage',
    },
    {
        title: 'Inventory',
        url: '/admin/inventory',
        icon: Warehouse,
        permission: 'inventory',
    },
    {
        title: 'Discounts',
        url: '/admin/discounts',
        icon: Percent,
        permission: 'discounts',
    },
    {
        title: 'Orders',
        url: '/admin/orders',
        icon: Receipt,
        permission: 'orders.view|orders.manage',
    },
    {
        title: 'Customers',
        url: '/admin/customers',
        icon: Users,
        permission: 'customers',
    },
    {
        title: 'Reports',
        url: '/admin/reports',
        icon: BarChart3,
        permission: 'reports',
    },
    {
        title: 'Shipping',
        url: '/admin/shipping',
        icon: Truck,
        permission: 'shipping',
    },
    {
        title: 'Couriers',
        url: '/admin/couriers',
        icon: Bike,
        permission: 'shipping',
    },
    {
        title: 'ZATCA E-Invoicing',
        url: '/admin/zatca',
        icon: Landmark,
        permission: 'zatca',
    },
    {
        title: 'Store Activity',
        url: '/admin/store-activity',
        icon: Activity,
        permission: 'activity',
    },
    {
        title: 'Reviews',
        url: '/admin/reviews',
        icon: Star,
        permission: 'reviews',
    },
    {
        title: 'CMS Pages',
        url: '/admin/pages',
        icon: Globe,
        permission: 'pages',
    },
    {
        title: 'Menus',
        url: '/admin/menus',
        icon: ListTree,
        permission: 'menus',
    },
    {
        title: 'Homepage',
        url: '/admin/homepage',
        icon: LayoutTemplate,
        permission: 'homepage',
    },
    {
        title: 'Audit Log',
        url: '/admin/audit-logs',
        icon: ShieldCheck,
        permission: 'audit',
    },
    {
        title: 'Settings',
        url: '/admin/settings',
        icon: Settings,
        permission: 'settings',
    },
    {
        title: 'Store Profile',
        url: '/admin/store-profile',
        icon: Store,
        permission: 'settings',
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/admin/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
