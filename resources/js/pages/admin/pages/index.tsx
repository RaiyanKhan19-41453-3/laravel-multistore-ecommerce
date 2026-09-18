import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface CmsPageItem {
    id: number;
    title: string;
    slug: string;
    is_published: boolean;
    sort_order: number;
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Pages', href: '/admin/pages' },
];

export default function PagesIndex({ pages }: { pages: CmsPageItem[] }) {
    const toggle = (id: number) => {
        router.post(`/admin/pages/${id}/toggle`, {}, { preserveState: true });
    };

    const destroy = (id: number) => {
        if (confirm('Delete this page?')) {
            router.delete(`/admin/pages/${id}`, { preserveState: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="CMS Pages" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="CMS Pages" description="Manage static pages (About, Terms, Privacy, etc.)." />
                    <Link href="/admin/pages/create">
                        <Button>
                            <Plus className="mr-1 h-4 w-4" /> Create Page
                        </Button>
                    </Link>
                </div>

                <div className="bg-card rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted">
                                <tr>
                                    <th className="px-3 py-2 text-left">Title</th>
                                    <th className="px-3 py-2 text-left">Slug</th>
                                    <th className="px-3 py-2 text-left">Status</th>
                                    <th className="px-3 py-2 text-right">Sort</th>
                                    <th className="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {pages.map((page) => (
                                    <tr key={page.id} className="border-t">
                                        <td className="px-3 py-2 font-medium">{page.title}</td>
                                        <td className="px-3 py-2 font-mono text-xs">/{page.slug}</td>
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                onClick={() => toggle(page.id)}
                                                className={`rounded-full px-2 py-0.5 text-xs ${page.is_published ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}
                                            >
                                                {page.is_published ? 'Published' : 'Draft'}
                                            </button>
                                        </td>
                                        <td className="px-3 py-2 text-right">{page.sort_order}</td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Link href={`/admin/pages/${page.id}/edit`}>
                                                    <Button size="sm" variant="outline">
                                                        <Pencil className="h-3 w-3" />
                                                    </Button>
                                                </Link>
                                                <Button size="sm" variant="destructive" onClick={() => destroy(page.id)}>
                                                    <Trash2 className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {pages.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="text-muted-foreground px-3 py-8 text-center">
                                            No pages yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
