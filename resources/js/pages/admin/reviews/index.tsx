import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, Trash2 } from 'lucide-react';

interface Review {
    id: number;
    rating: number;
    title: string | null;
    body: string | null;
    is_approved: boolean;
    verified_purchase: boolean;
    guest_name: string | null;
    created_at: string;
    user: { id: number; name: string } | null;
    product: { id: number; name: string; slug: string };
}

interface PaginatedData {
    data: Review[];
    current_page: number;
    last_page: number;
    total: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Reviews', href: '/admin/reviews' },
];

export default function ReviewsIndex({ reviews, filters }: { reviews: PaginatedData; filters: { is_approved?: string } }) {
    const approve = (id: number) => {
        router.post(`/admin/reviews/${id}/approve`, {}, { preserveState: true });
    };

    const destroy = (id: number) => {
        if (confirm('Delete this review?')) {
            router.delete(`/admin/reviews/${id}`, { preserveState: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reviews" />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="Reviews" description="Moderate customer product reviews." />

                <div className="flex gap-2">
                    <Button
                        variant={filters.is_approved === undefined ? 'default' : 'outline'}
                        onClick={() => router.get('/admin/reviews', {}, { preserveState: true })}
                    >
                        All
                    </Button>
                    <Button
                        variant={filters.is_approved === '0' ? 'default' : 'outline'}
                        onClick={() => router.get('/admin/reviews', { is_approved: 0 }, { preserveState: true })}
                    >
                        Pending
                    </Button>
                    <Button
                        variant={filters.is_approved === '1' ? 'default' : 'outline'}
                        onClick={() => router.get('/admin/reviews', { is_approved: 1 }, { preserveState: true })}
                    >
                        Approved
                    </Button>
                </div>

                <div className="bg-card rounded-xl border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted">
                                <tr>
                                    <th className="px-3 py-2 text-left">Product</th>
                                    <th className="px-3 py-2 text-left">User</th>
                                    <th className="px-3 py-2 text-left">Rating</th>
                                    <th className="px-3 py-2 text-left">Title</th>
                                    <th className="px-3 py-2 text-left">Status</th>
                                    <th className="px-3 py-2 text-left">Date</th>
                                    <th className="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {reviews.data.map((review) => (
                                    <tr key={review.id} className="border-t">
                                        <td className="px-3 py-2">
                                            <a href={`/admin/products/${review.product.id}`} className="text-primary hover:underline">
                                                {review.product.name}
                                            </a>
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex items-center gap-1.5">
                                                {review.user?.name ?? review.guest_name ?? 'Guest'}
                                                {review.verified_purchase && (
                                                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-green-700">
                                                        Verified
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            {'★'.repeat(review.rating)}
                                            {'☆'.repeat(5 - review.rating)}
                                        </td>
                                        <td className="px-3 py-2">{review.title ?? '-'}</td>
                                        <td className="px-3 py-2">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs ${review.is_approved ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'}`}
                                            >
                                                {review.is_approved ? 'Approved' : 'Pending'}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2">{new Date(review.created_at).toLocaleDateString()}</td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="flex justify-end gap-1">
                                                {!review.is_approved && (
                                                    <Button size="sm" variant="outline" onClick={() => approve(review.id)}>
                                                        <Check className="h-3 w-3" />
                                                    </Button>
                                                )}
                                                <Button size="sm" variant="destructive" onClick={() => destroy(review.id)}>
                                                    <Trash2 className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {reviews.data.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="text-muted-foreground px-3 py-8 text-center">
                                            No reviews.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {reviews.last_page > 1 && (
                        <div className="flex justify-center gap-2 border-t p-3">
                            {Array.from({ length: reviews.last_page }, (_, i) => i + 1).map((page) => (
                                <Button
                                    key={page}
                                    size="sm"
                                    variant={reviews.current_page === page ? 'default' : 'outline'}
                                    onClick={() => router.get('/admin/reviews', { ...filters, page }, { preserveState: true })}
                                >
                                    {page}
                                </Button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
