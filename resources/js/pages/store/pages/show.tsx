import StoreLayout from '@/layouts/store-layout';
import { useT } from '@/lib/store';
import type { CmsPage } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function PageShow({ slug }: { slug: string }) {
    const t = useT();
    const [page, setPage] = useState<CmsPage | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setLoading(true);
        void fetch(`/api/pages/${slug}`)
            .then((r) => {
                if (!r.ok) throw new Error();
                return r.json();
            })
            .then((json: { success: boolean; data: CmsPage }) => {
                if (json.success) setPage(json.data);
                else setError('Page not found.');
            })
            .catch(() => setError('Page not found.'))
            .finally(() => setLoading(false));
    }, [slug]);

    if (loading) {
        return (
            <StoreLayout title="Page">
                <div className="mx-auto max-w-3xl px-4 py-12 text-[var(--store-muted)]">{t('store.loading')}</div>
            </StoreLayout>
        );
    }

    if (error || !page) {
        return (
            <StoreLayout title="Page">
                <div className="mx-auto max-w-3xl px-4 py-12">
                    <p className="text-[var(--store-muted)]">{error ?? t('store.page_not_found')}</p>
                    <Link href="/" className="mt-2 inline-block text-sm text-[var(--store-accent)] hover:underline">
                        {t('store.back_to_home')}
                    </Link>
                </div>
            </StoreLayout>
        );
    }

    return (
        <StoreLayout title={page.title}>
            <Head>
                <title>{page.meta_title ?? page.title}</title>
                {page.meta_description && <meta name="description" content={page.meta_description} />}
            </Head>

            <div className="mx-auto max-w-3xl px-4 py-8">
                <Link href="/" className="mb-4 inline-block text-sm text-[var(--store-muted)] hover:underline">
                    ← {t('store.back_to_home')}
                </Link>

                <h1 className="mb-6 text-3xl font-bold">{page.title}</h1>

                <div className="prose prose-sm max-w-none text-[var(--store-text)]" dangerouslySetInnerHTML={{ __html: page.body }} />
            </div>
        </StoreLayout>
    );
}
