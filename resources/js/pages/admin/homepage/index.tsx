import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Images, Pencil, Plus, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface BlockRow {
    key: string | null;
    banner_id: number | null;
    title: string | null;
    is_active: boolean;
    sort_order: number;
}

interface Slide {
    id: number;
    eyebrow: string | null;
    eyebrow_ar: string | null;
    title: string;
    title_ar: string | null;
    subtitle: string | null;
    subtitle_ar: string | null;
    cta_label: string | null;
    cta_label_ar: string | null;
    cta_link: string | null;
    image: string | null;
    layout: string;
    show_eyebrow: boolean;
    show_title: boolean;
    show_subtitle: boolean;
    show_button: boolean;
    is_active: boolean;
    sort_order: number;
}

interface BannerRecord {
    id: number;
    title: string;
    title_ar: string | null;
    subtitle: string | null;
    subtitle_ar: string | null;
    button_label: string | null;
    button_label_ar: string | null;
    button_link: string | null;
    layout: string;
    text_layout: string;
    show_title: boolean;
    show_subtitle: boolean;
    show_button: boolean;
    media_type: string;
    iframe_url: string | null;
    images: string[] | null;
    is_active: boolean;
    sort_order: number;
}

const LABELS: Record<string, string> = {
    hero: 'Hero',
    brands: 'Brand marquee',
    categories: 'Category index',
    featured: 'Featured products',
    top_rated: 'Top rated',
    spotlight: 'Spotlight deal',
    banners: 'Banners',
    perks: 'Perks',
};

const DISPLAY_LABELS: Record<string, string> = {
    split: 'Split — headline plus product collage',
    slider: 'Slider — rotating full-width banners',
    centered: 'Centered — minimal centered headline',
};

const LAYOUT_LABELS: Record<string, string> = {
    single: 'Single — 1 full-width image',
    double: 'Double — 2 columns, 2 images',
    quad: 'Quad — 4 columns, 4 images',
};

const TEXT_LABELS: Record<string, string> = {
    split: 'Text left, button right',
    left: 'Text left, no button',
    center: 'Centered text, button after images',
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Homepage', href: '/admin/homepage' },
];

const rowId = (row: BlockRow, i: number) => (row.banner_id != null ? `banner-${row.banner_id}` : `section-${row.key ?? i}`);

const rowKey = (row: { key: string | null; banner_id: number | null }) =>
    row.banner_id != null ? `banner-${row.banner_id}` : `section-${row.key ?? ''}`;

export default function HomepageIndex({
    blocks,
    heroDisplay,
    displays,
    slides,
    banners,
    bannerLayouts,
    bannerTextLayouts,
}: {
    blocks: BlockRow[];
    heroDisplay: string;
    displays: string[];
    slides: Slide[];
    banners: BannerRecord[];
    bannerLayouts: string[];
    bannerTextLayouts: string[];
}) {
    const [rows, setRows] = useState<BlockRow[]>(() => [...blocks].sort((a, b) => a.sort_order - b.sort_order));
    const [display, setDisplay] = useState(heroDisplay);
    const [busy, setBusy] = useState(false);

    // Banner create/delete refreshes props (rows added/removed server-side).
    // Merge membership without dropping unsaved local order/toggles.
    useEffect(() => {
        setRows((prev) => {
            const prevIds = new Set(prev.map(rowKey));
            const nextIds = new Set(blocks.map(rowKey));
            if (prevIds.size === nextIds.size && [...prevIds].every((id) => nextIds.has(id))) return prev;

            const merged = prev.filter((r) => nextIds.has(rowKey(r)));
            for (const s of blocks) {
                if (!prevIds.has(rowKey(s))) merged.push({ ...s });
            }
            return merged;
        });
    }, [blocks]);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Slide | null>(null);
    const [bannerDialogOpen, setBannerDialogOpen] = useState(false);
    const [editingBanner, setEditingBanner] = useState<BannerRecord | null>(null);

    const fingerprint = (list: BlockRow[]) => JSON.stringify(list.map((r) => [r.key, r.banner_id, r.is_active]));
    const dirty =
        display !== heroDisplay ||
        fingerprint(rows) !== fingerprint(blocks) ||
        rows.some((r, i) => {
            const original = blocks.find((s) => (s.banner_id != null ? s.banner_id === r.banner_id : s.key === r.key && s.banner_id == null));
            return original == null || original.sort_order !== i;
        });

    const move = (index: number, direction: -1 | 1) => {
        const next = [...rows];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        setRows(next);
    };

    const toggle = (index: number, active: boolean) => {
        setRows((prev) => prev.map((r, i) => (i === index ? { ...r, is_active: active } : r)));
    };

    const save = () => {
        setBusy(true);
        router.put(
            '/admin/homepage',
            {
                hero_display: display,
                blocks: rows.map((r, i) => ({ type: r.banner_id != null ? 'banner' : 'section', key: r.key, banner_id: r.banner_id, is_active: r.is_active, sort_order: i })),
            },
            { onFinish: () => setBusy(false) },
        );
    };

    const toggleSlide = (id: number) => {
        router.post(`/admin/homepage/slides/${id}/toggle`, {}, { preserveState: true });
    };

    const destroySlide = (slide: Slide) => {
        if (confirm(`Delete slide "${slide.title}"?`)) {
            router.delete(`/admin/homepage/slides/${slide.id}`, { preserveState: true });
        }
    };

    const openBannerCreate = () => {
        setEditingBanner(null);
        setBannerDialogOpen(true);
    };

    const openBannerEdit = (bannerId: number) => {
        const record = banners.find((b) => b.id === bannerId) ?? null;
        setEditingBanner(record);
        setBannerDialogOpen(true);
    };

    const destroyBanner = (bannerId: number, title: string | null) => {
        if (confirm(`Delete banner "${title ?? `#${bannerId}`}"? This removes it from the layout too.`)) {
            router.delete(`/admin/banners/${bannerId}`, { preserveState: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Homepage" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Homepage" description="Hero style, slides, banners, section visibility, and their exact order." />
                    <Button onClick={save} disabled={!dirty || busy}>
                        {busy ? 'Saving...' : 'Save Layout'}
                    </Button>
                </div>

                <div className="bg-card max-w-2xl space-y-4 rounded-xl border p-5">
                    <h2 className="text-base font-semibold">Hero style</h2>
                    <div className="grid gap-1.5">
                        <Label>How the hero section looks</Label>
                        <Select value={display} onValueChange={setDisplay}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {displays.map((d) => (
                                    <SelectItem key={d} value={d}>
                                        {DISPLAY_LABELS[d] ?? d}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-xs">Slider rotates through the slides below. Split and centered need no slides.</p>
                    </div>
                </div>

                <div className="bg-card max-w-2xl rounded-xl border">
                    <div className="flex items-center justify-between p-4 pb-0">
                        <h2 className="text-base font-semibold">Hero slides</h2>
                        <Button
                            size="sm"
                            onClick={() => {
                                setEditing(null);
                                setDialogOpen(true);
                            }}
                        >
                            <Plus className="mr-1 h-3 w-3" /> Add Slide
                        </Button>
                    </div>
                    <div className="overflow-x-auto p-4 pt-2">
                        {slides.length === 0 ? (
                            <p className="text-muted-foreground py-4 text-center text-sm">No slides yet. The slider falls back to the split hero.</p>
                        ) : (
                            <div className="space-y-2">
                                {slides.map((slide) => (
                                    <div key={slide.id} className="flex items-center gap-3 rounded-lg border p-3">
                                        <span className="flex h-12 w-20 shrink-0 items-center justify-center overflow-hidden rounded-md bg-neutral-100">
                                            {slide.image ? (
                                                <img src={slide.image} alt="" className="h-full w-full object-cover" />
                                            ) : (
                                                <span className="text-muted-foreground text-[10px]">No image</span>
                                            )}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">{slide.title}</p>
                                            <p className="text-muted-foreground truncate text-xs">{slide.subtitle ?? '—'}</p>
                                        </div>
                                        <Switch checked={slide.is_active} onCheckedChange={() => toggleSlide(slide.id)} />
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                setEditing(slide);
                                                setDialogOpen(true);
                                            }}
                                        >
                                            <Pencil className="h-3 w-3" />
                                        </Button>
                                        <Button size="sm" variant="destructive" onClick={() => destroySlide(slide)}>
                                            <Trash2 className="h-3 w-3" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <div className="bg-card max-w-2xl rounded-xl border">
                    <div className="flex items-center justify-between p-4 pb-0">
                        <h2 className="text-base font-semibold">Blocks in order</h2>
                        <Button size="sm" onClick={openBannerCreate}>
                            <Plus className="mr-1 h-3 w-3" /> Add Banner
                        </Button>
                    </div>
                    <div className="overflow-x-auto p-4 pt-2">
                    <table className="w-full text-sm">
                            <thead className="bg-muted">
                                <tr>
                                    <th className="px-3 py-2 text-left">Block</th>
                                    <th className="px-3 py-2 text-left">Visible</th>
                                    <th className="px-3 py-2 text-right">Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, i) => (
                                    <tr key={rowId(row, i)} className="border-t">
                                        <td className="px-3 py-2 font-medium">
                                            {row.banner_id != null ? (
                                                <span className="inline-flex items-center gap-2">
                                                    <Images className="text-muted-foreground h-4 w-4" />
                                                    Banner: {row.title ?? `#${row.banner_id}`}
                                                    <div className="flex gap-1">
                                                        <Button size="sm" variant="outline" onClick={() => openBannerEdit(row.banner_id as number)}>
                                                            <Pencil className="h-3 w-3" />
                                                        </Button>
                                                        <Button size="sm" variant="destructive" onClick={() => destroyBanner(row.banner_id as number, row.title)}>
                                                            <Trash2 className="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </span>
                                            ) : (
                                                <>
                                                    {LABELS[row.key ?? ''] ?? row.key}
                                                    {!row.is_active && <span className="text-muted-foreground ml-2 text-xs">(hidden)</span>}
                                                </>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Switch checked={row.is_active} onCheckedChange={(v) => toggle(i, v)} />
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button size="sm" variant="outline" disabled={i === 0} onClick={() => move(i, -1)}>
                                                    <ArrowUp className="h-3 w-3" />
                                                </Button>
                                                <Button size="sm" variant="outline" disabled={i === rows.length - 1} onClick={() => move(i, 1)}>
                                                    <ArrowDown className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="text-muted-foreground px-4 py-3 text-xs">
                        New banners appear here automatically after the spotlight slot. Move any banner anywhere to place it precisely.
                    </p>
                </div>
            </div>

            {dialogOpen && <SlideDialog key={editing ? `edit-${editing.id}` : 'create'} editing={editing} onClose={() => setDialogOpen(false)} />}
            {bannerDialogOpen && (
                <BannerDialog
                    key={editingBanner ? `banner-edit-${editingBanner.id}` : 'banner-create'}
                    editing={editingBanner}
                    layouts={bannerLayouts}
                    textLayouts={bannerTextLayouts}
                    onClose={() => setBannerDialogOpen(false)}
                />
            )}
        </AppLayout>
    );
}

function SlideDialog({ editing, onClose }: { editing: Slide | null; onClose: () => void }) {
    const { data, setData, errors } = useForm({
        eyebrow: editing?.eyebrow ?? '',
        eyebrow_ar: editing?.eyebrow_ar ?? '',
        title: editing?.title ?? '',
        title_ar: editing?.title_ar ?? '',
        subtitle: editing?.subtitle ?? '',
        subtitle_ar: editing?.subtitle_ar ?? '',
        cta_label: editing?.cta_label ?? '',
        cta_label_ar: editing?.cta_label_ar ?? '',
        cta_link: editing?.cta_link ?? '/products',
        show_eyebrow: editing?.show_eyebrow ?? true,
        show_title: editing?.show_title ?? true,
        show_subtitle: editing?.show_subtitle ?? true,
        show_button: editing?.show_button ?? true,
        layout: editing?.layout ?? 'split',
        sort_order: editing?.sort_order ?? 0,
        is_active: editing?.is_active ?? true,
        image_file: null as File | null,
    });
    const [preview, setPreview] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        const payload = { ...data, sort_order: Number(data.sort_order) };
        const done = { onFinish: () => setBusy(false), onSuccess: onClose };
        if (editing) {
            router.post(`/admin/homepage/slides/${editing.id}`, { ...payload, _method: 'PUT' }, done);
        } else {
            router.post('/admin/homepage/slides', payload, done);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{editing ? `Edit — ${editing.title}` : 'Add Slide'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-1.5">
                        <Label>Banner image</Label>
                        <div className="flex items-center gap-4">
                            <span className="flex h-16 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-neutral-100">
                                {preview || editing?.image ? (
                                    <img src={preview ?? editing?.image ?? ''} alt="" className="h-full w-full object-cover" />
                                ) : (
                                    <span className="text-muted-foreground text-[10px]">None — gradient fallback</span>
                                )}
                            </span>
                            <Input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] ?? null;
                                    setData('image_file', file);
                                    setPreview(file ? URL.createObjectURL(file) : null);
                                }}
                                className="max-w-56"
                            />
                        </div>
                        {errors.image_file && <p className="text-destructive text-xs">{errors.image_file}</p>}
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Slide layout</Label>
                        <Select value={data.layout} onValueChange={(v) => setData('layout', v)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="split">Split — text beside the image</SelectItem>
                                <SelectItem value="full">Full — image fills the whole slide</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.layout && <p className="text-destructive text-xs">{errors.layout}</p>}
                        <p className="text-muted-foreground text-xs">Full-bleed works best with wide images.</p>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="slide-eyebrow">Eyebrow</Label>
                            <Input id="slide-eyebrow" value={data.eyebrow} onChange={(e) => setData('eyebrow', e.target.value)} placeholder="New season" />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="slide-eyebrow-ar">Eyebrow (Arabic)</Label>
                            <Input id="slide-eyebrow-ar" value={data.eyebrow_ar} onChange={(e) => setData('eyebrow_ar', e.target.value)} dir="rtl" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="slide-title">Title *</Label>
                            <Input id="slide-title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                            {errors.title && <p className="text-destructive text-xs">{errors.title}</p>}
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="slide-title-ar">Title (Arabic)</Label>
                            <Input id="slide-title-ar" value={data.title_ar} onChange={(e) => setData('title_ar', e.target.value)} dir="rtl" />
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="slide-subtitle">Subtitle</Label>
                        <Textarea id="slide-subtitle" value={data.subtitle} onChange={(e) => setData('subtitle', e.target.value)} rows={2} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="slide-subtitle-ar">Subtitle (Arabic)</Label>
                        <Textarea id="slide-subtitle-ar" value={data.subtitle_ar} onChange={(e) => setData('subtitle_ar', e.target.value)} rows={2} dir="rtl" />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="slide-cta">Button label</Label>
                            <Input id="slide-cta" value={data.cta_label} onChange={(e) => setData('cta_label', e.target.value)} placeholder="Shop now" />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="slide-cta-link">Button link</Label>
                            <Input id="slide-cta-link" value={data.cta_link} onChange={(e) => setData('cta_link', e.target.value)} placeholder="/products" dir="ltr" />
                            {errors.cta_link && <p className="text-destructive text-xs">{errors.cta_link}</p>}
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label>Show elements</Label>
                        <div className="divide-y divide-border rounded-lg border text-sm">
                            {(
                                [
                                    ['show_eyebrow', 'Eyebrow'],
                                    ['show_title', 'Title'],
                                    ['show_subtitle', 'Subtitle'],
                                    ['show_button', 'Button'],
                                ] as const
                            ).map(([field, label]) => (
                                <label key={field} className="flex cursor-pointer items-center justify-between gap-2 px-3 py-2">
                                    {label}
                                    <Switch checked={data[field]} onCheckedChange={(v) => setData(field, v)} />
                                </label>
                            ))}
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="slide-sort">Sort order</Label>
                            <Input id="slide-sort" type="number" min={0} value={data.sort_order} onChange={(e) => setData('sort_order', Number(e.target.value))} />
                        </div>
                        <div className="flex items-end gap-2 pb-2">
                            <Switch checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
                            <Label>Active</Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={busy}>
                            {editing ? 'Save' : 'Create'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function BannerDialog({
    editing,
    layouts,
    textLayouts,
    onClose,
}: {
    editing: BannerRecord | null;
    layouts: string[];
    textLayouts: string[];
    onClose: () => void;
}) {
    const { data, setData, errors } = useForm({
        title: editing?.title ?? '',
        title_ar: editing?.title_ar ?? '',
        subtitle: editing?.subtitle ?? '',
        subtitle_ar: editing?.subtitle_ar ?? '',
        button_label: editing?.button_label ?? '',
        button_label_ar: editing?.button_label_ar ?? '',
        button_link: editing?.button_link ?? '/products',
        layout: editing?.layout ?? 'single',
        text_layout: editing?.text_layout ?? 'split',
        show_title: editing?.show_title ?? true,
        show_subtitle: editing?.show_subtitle ?? true,
        show_button: editing?.show_button ?? true,
        media_type: editing?.media_type ?? 'image',
        iframe_url: editing?.iframe_url ?? '',
        sort_order: editing?.sort_order ?? 0,
        is_active: editing?.is_active ?? true,
    });
    const [kept, setKept] = useState<string[]>(editing?.images ?? []);
    const [files, setFiles] = useState<File[]>([]);
    const [busy, setBusy] = useState(false);
    const formErrors = errors as unknown as Record<string, string | undefined>;

    const removeKept = (src: string) => setKept((prev) => prev.filter((s) => s !== src));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        const payload = {
            ...data,
            sort_order: Number(data.sort_order),
            kept_images: kept,
            image_files: files,
        };
        const done = { onFinish: () => setBusy(false), onSuccess: onClose };
        if (editing) {
            router.post(`/admin/banners/${editing.id}`, { ...payload, _method: 'PUT' }, done);
        } else {
            router.post('/admin/banners', payload, done);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{editing ? `Edit banner — ${editing.title}` : 'Add Banner'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label>Media type</Label>
                            <Select value={data.media_type} onValueChange={(v) => setData('media_type', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="image">Images</SelectItem>
                                    <SelectItem value="iframe">Embed (YouTube, map…)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Show elements</Label>
                            <div className="divide-y divide-border rounded-lg border text-sm">
                                <label className="flex cursor-pointer items-center justify-between gap-2 px-3 py-2">
                                    Title
                                    <Switch checked={data.show_title} onCheckedChange={(v) => setData('show_title', v)} />
                                </label>
                                <label className="flex cursor-pointer items-center justify-between gap-2 px-3 py-2">
                                    Subtitle
                                    <Switch checked={data.show_subtitle} onCheckedChange={(v) => setData('show_subtitle', v)} />
                                </label>
                                <label className="flex cursor-pointer items-center justify-between gap-2 px-3 py-2">
                                    Button
                                    <Switch checked={data.show_button} onCheckedChange={(v) => setData('show_button', v)} />
                                </label>
                            </div>
                        </div>
                    </div>
                    {data.media_type === 'iframe' ? (
                        <EmbedPicker
                            embedUrl={data.iframe_url}
                            onResolved={(embedUrl, title) => {
                                setData('iframe_url', embedUrl);
                                if (!data.title.trim() && title) setData('title', title);
                            }}
                            onClear={() => setData('iframe_url', '')}
                            error={errors.iframe_url}
                        />
                    ) : (
                    <div className="grid gap-1.5">
                        <Label>Images (up to 4 total)</Label>
                        {kept.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {kept.map((src) => (
                                    <span key={src} className="relative block h-16 w-24 overflow-hidden rounded-lg border">
                                        <img src={src} alt="" className="h-full w-full object-cover" />
                                        <button
                                            type="button"
                                            aria-label="Remove image"
                                            onClick={() => removeKept(src)}
                                            className="absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-full bg-black/60 text-white"
                                        >
                                            <X className="h-3 w-3" />
                                        </button>
                                    </span>
                                ))}
                            </div>
                        )}
                        <Input type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={(e) => setFiles(Array.from(e.target.files ?? []))} />
                        <p className="text-muted-foreground text-xs">JPEG, PNG or WebP up to 2MB each. New uploads are added to the kept ones.</p>
                        {formErrors.image_files && <p className="text-destructive text-xs">{formErrors.image_files}</p>}
                    </div>
                    )}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="hbanner-title">Title *</Label>
                            <Input id="hbanner-title" value={data.title} onChange={(e) => setData('title', e.target.value)} />
                            {errors.title && <p className="text-destructive text-xs">{errors.title}</p>}
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="hbanner-title-ar">Title (Arabic)</Label>
                            <Input id="hbanner-title-ar" value={data.title_ar} onChange={(e) => setData('title_ar', e.target.value)} dir="rtl" />
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="hbanner-subtitle">Subtitle</Label>
                        <Textarea id="hbanner-subtitle" value={data.subtitle} onChange={(e) => setData('subtitle', e.target.value)} rows={2} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="hbanner-subtitle-ar">Subtitle (Arabic)</Label>
                        <Textarea id="hbanner-subtitle-ar" value={data.subtitle_ar} onChange={(e) => setData('subtitle_ar', e.target.value)} rows={2} dir="rtl" />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="hbanner-btn">Button label</Label>
                            <Input id="hbanner-btn" value={data.button_label} onChange={(e) => setData('button_label', e.target.value)} placeholder="Shop now" />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="hbanner-btn-link">Button link</Label>
                            <Input id="hbanner-btn-link" value={data.button_link} onChange={(e) => setData('button_link', e.target.value)} placeholder="/products" dir="ltr" />
                            {errors.button_link && <p className="text-destructive text-xs">{errors.button_link}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label>Images layout</Label>
                            <Select value={data.layout} onValueChange={(v) => setData('layout', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {layouts.map((l) => (
                                        <SelectItem key={l} value={l}>
                                            {LAYOUT_LABELS[l] ?? l}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label>Text layout</Label>
                            <Select value={data.text_layout} onValueChange={(v) => setData('text_layout', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {textLayouts.map((l) => (
                                        <SelectItem key={l} value={l}>
                                            {TEXT_LABELS[l] ?? l}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-1.5">
                            <Label htmlFor="hbanner-sort">Sort order</Label>
                            <Input id="hbanner-sort" type="number" min={0} value={data.sort_order} onChange={(e) => setData('sort_order', Number(e.target.value))} />
                        </div>
                        <div className="flex items-end gap-2 pb-2">
                            <Switch checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
                            <Label>Active</Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={busy}>
                            {editing ? 'Save' : 'Create'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * In-dialog embed finder: paste any YouTube link (watch, Shorts,
 * share, embed) or Maps link and it resolves to a safe embed URL
 * with a live preview — no tab-hopping. The video title fills the
 * banner title when it is still empty.
 */
function EmbedPicker({
    embedUrl,
    onResolved,
    onClear,
    error,
}: {
    embedUrl: string;
    onResolved: (embedUrl: string, title: string | null) => void;
    onClear: () => void;
    error?: string;
}) {
    const [sourceLink, setSourceLink] = useState('');
    const [fetching, setFetching] = useState(false);
    const [fetchError, setFetchError] = useState<string | null>(null);

    const fetchEmbed = async () => {
        const url = sourceLink.trim();
        if (!url) return;
        setFetching(true);
        setFetchError(null);

        try {
            const csrf = document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=');
            const res = await fetch('/admin/banners/fetch-embed', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...(csrf ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf) } : {}),
                },
                body: JSON.stringify({ url }),
            });
            const json = (await res.json()) as { embed_url?: string; title?: string | null; message?: string; errors?: Record<string, string[]> };

            if (!res.ok || !json.embed_url) {
                setFetchError(json.errors?.url?.[0] ?? json.message ?? 'That link cannot be embedded.');
                return;
            }

            onResolved(json.embed_url, json.title ?? null);
            setSourceLink('');
        } catch {
            setFetchError('Could not reach the embed service. Check the link and try again.');
        } finally {
            setFetching(false);
        }
    };

    if (embedUrl) {
        return (
            <div className="grid gap-1.5">
                <Label>Embed preview</Label>
                <div className="overflow-hidden rounded-lg border">
                    <div className="aspect-video w-full bg-black">
                        <iframe src={embedUrl} title="Embed preview" loading="lazy" className="h-full w-full" />
                    </div>
                    <div className="flex items-center justify-between gap-2 px-3 py-2">
                        <p className="truncate font-mono text-[11px] text-muted-foreground" dir="ltr">
                            {embedUrl}
                        </p>
                        <Button type="button" variant="ghost" size="sm" onClick={onClear}>
                            Change
                        </Button>
                    </div>
                </div>
                {error && <p className="text-destructive text-xs">{error}</p>}
            </div>
        );
    }

    return (
        <div className="grid gap-1.5">
            <Label htmlFor="hbanner-source">Video or map link</Label>
            <div className="flex gap-2">
                <Input
                    id="hbanner-source"
                    value={sourceLink}
                    onChange={(e) => setSourceLink(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            void fetchEmbed();
                        }
                    }}
                    placeholder="Paste a YouTube or Maps link…"
                    dir="ltr"
                />
                <Button type="button" variant="outline" disabled={fetching || !sourceLink.trim()} onClick={() => void fetchEmbed()}>
                    {fetching ? 'Fetching…' : 'Fetch'}
                </Button>
            </div>
            {(fetchError ?? error) && <p className="text-destructive text-xs">{fetchError ?? error}</p>}
            <p className="text-muted-foreground text-xs">Watch, Shorts, and share links all work — the title fills in itself. One embed per banner.</p>
        </div>
    );
}
