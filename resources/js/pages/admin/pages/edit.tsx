import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

interface CmsPageData {
    id: number;
    title: string;
    title_ar: string | null;
    slug: string;
    body: string | null;
    body_ar: string | null;
    meta_title: string | null;
    meta_description: string | null;
    is_published: boolean;
    sort_order: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Pages', href: '/admin/pages' },
    { title: 'Edit', href: '#' },
];

export default function PageEdit({ page }: { page: CmsPageData }) {
    const { data, setData, put, processing, errors } = useForm({
        title: page.title,
        title_ar: page.title_ar ?? '',
        slug: page.slug,
        body: page.body ?? '',
        body_ar: page.body_ar ?? '',
        meta_title: page.meta_title ?? '',
        meta_description: page.meta_description ?? '',
        is_published: page.is_published,
        sort_order: page.sort_order,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/pages/${page.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit — ${page.title}`} />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="Edit Page" description={`Editing "${page.title}"`} />

                <form onSubmit={submit} className="bg-card max-w-2xl space-y-4 rounded-xl border p-5">
                    <div className="grid gap-1">
                        <Label htmlFor="title">Title *</Label>
                        <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                        {errors.title && <p className="text-destructive text-xs">{errors.title}</p>}
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="title_ar">Title (Arabic)</Label>
                        <Input id="title_ar" value={data.title_ar} onChange={(e) => setData('title_ar', e.target.value)} dir="rtl" />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="slug">Slug</Label>
                        <Input id="slug" value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="body">Body (HTML)</Label>
                        <textarea
                            id="body"
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            rows={10}
                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="body_ar">Body Arabic (HTML)</Label>
                        <textarea
                            id="body_ar"
                            value={data.body_ar}
                            onChange={(e) => setData('body_ar', e.target.value)}
                            rows={10}
                            className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                            dir="rtl"
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="meta_title">Meta Title</Label>
                        <Input id="meta_title" value={data.meta_title} onChange={(e) => setData('meta_title', e.target.value)} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="meta_description">Meta Description</Label>
                        <Input id="meta_description" value={data.meta_description} onChange={(e) => setData('meta_description', e.target.value)} />
                    </div>
                    <div className="flex items-center gap-4">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.is_published}
                                onChange={(e) => setData('is_published', e.target.checked)}
                                className="rounded"
                            />
                            Published
                        </label>
                        <div className="grid gap-1">
                            <Label htmlFor="sort_order">Sort Order</Label>
                            <Input
                                id="sort_order"
                                type="number"
                                value={data.sort_order}
                                onChange={(e) => setData('sort_order', Number(e.target.value))}
                                className="w-24"
                            />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>
                        Save Changes
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
