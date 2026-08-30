import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Package, Percent, Receipt, SlidersHorizontal, Tags, Trophy, Warehouse, Activity, Truck } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Categories',
        url: '/admin/categories',
        icon: Tags,
    },
    {
        title: 'Brands',
        url: '/admin/brands',
        icon: Trophy,
    },
    {
        title: 'Attributes',
        url: '/admin/attributes',
        icon: SlidersHorizontal,
    },
    {
        title: 'Products',
        url: '/admin/products',
        icon: Package,
    },
    {
        title: 'Inventory',
        url: '/admin/inventory',
        icon: Warehouse,
    },
    {
        title: 'Discounts',
        url: '/admin/discounts',
        icon: Percent,
    },
    {
        title: 'Orders',
        url: '/admin/orders',
        icon: Receipt,
    },
    {
        title: 'Shipping',
        url: '/admin/shipping',
        icon: Truck,
    },
    {
        title: 'Store Activity',
        url: '/admin/store-activity',
        icon: Activity,
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
