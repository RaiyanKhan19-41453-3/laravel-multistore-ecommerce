import Heading from '@/components/heading';
import StoreLogo from '@/components/store/store-logo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface StoreProfile {
    name: string;
    tagline: string | null;
    logo: string | null;
    show_store_name: boolean;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    meta_title: string | null;
    meta_description: string | null;
    favicon: string | null;
    og_image: string | null;
    google_tag_id: string | null;
    google_site_verification: string | null;
    meta_pixel_id: string | null;
    twitter_handle: string | null;
    twitter_card: string | null;
    og_image_alt: string | null;
    robots_noindex: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Store Profile', href: '/admin/store-profile' },
];

function BrandFile({
    label,
    current,
    accept,
    hint,
    fileKey,
    error,
    onPick,
    onRemove,
    removing,
}: {
    label: string;
    current: string | null;
    accept: string;
    hint: string;
    fileKey: number;
    error?: string;
    onPick: (file: File | null) => void;
    onRemove: () => void;
    removing: boolean;
}) {
    const [preview, setPreview] = useState<string | null>(null);
    const src = preview ?? current;

    const pick = (file: File | null) => {
        setPreview((old) => {
            if (old) URL.revokeObjectURL(old);
            return file ? URL.createObjectURL(file) : null;
        });
        onPick(file);
    };

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <div className="flex items-center gap-4">
                <span className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-white">
                    {src ? (
                        <img src={src} alt={label} className="h-full w-full object-contain" />
                    ) : (
                        <span className="text-muted-foreground text-xs">None</span>
                    )}
                </span>
                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        key={fileKey}
                        type="file"
                        accept={accept}
                        onChange={(e) => pick(e.target.files?.[0] ?? null)}
                        className="max-w-56"
                    />
                    {current && (
                        <Button type="button" variant="ghost" disabled={removing} onClick={onRemove}>
                            {removing ? 'Removing...' : 'Remove'}
                        </Button>
                    )}
                </div>
            </div>
            {error && <p className="text-destructive text-xs">{error}</p>}
            <p className="text-muted-foreground text-xs">{hint}</p>
        </div>
    );
}

export default function StoreProfileIndex({ profile }: { profile: StoreProfile }) {
    const { data, setData, post, errors, processing } = useForm({
        name: profile.name,
        tagline: profile.tagline ?? '',
        show_store_name: profile.show_store_name ?? true,
        email: profile.email ?? '',
        phone: profile.phone ?? '',
        address: profile.address ?? '',
        city: profile.city ?? '',
        meta_title: profile.meta_title ?? '',
                                        meta_description: profile.meta_description ?? '',
                                        google_tag_id: profile.google_tag_id ?? '',
        google_site_verification: profile.google_site_verification ?? '',
                                        meta_pixel_id: profile.meta_pixel_id ?? '',
                                        twitter_handle: profile.twitter_handle ?? '',
                                        twitter_card: profile.twitter_card ?? 'summary_large_image',
                                        og_image_alt: profile.og_image_alt ?? '',
                                        robots_noindex: profile.robots_noindex ?? false,
        logo_file: null as File | null,
        favicon_file: null as File | null,
        og_image_file: null as File | null,
        _method: 'PUT',
    });
    const [fileKey, setFileKey] = useState(0);
    const [removing, setRemoving] = useState<string | null>(null);

    const clearFiles = () => {
        setData('logo_file', null);
        setData('favicon_file', null);
        setData('og_image_file', null);
        setFileKey((k) => k + 1);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // POST with spoofed PUT: PHP discards multipart bodies on real
        // PUT/PATCH requests, so uploads would never arrive otherwise.
        post(route('admin.store-profile.update'), { onSuccess: clearFiles });
    };

    const removeFile = (removeKey: 'remove_logo' | 'remove_favicon' | 'remove_og_image', label: string) => {
        if (!confirm(`Remove the ${label}?`)) return;
        setRemoving(removeKey);
        router.post(route('admin.store-profile.update'), { _method: 'PUT', [removeKey]: true }, { onFinish: () => setRemoving(null) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Store Profile" />
            <div className="flex flex-col gap-6 p-4">
                <Heading title="Store Profile" description="Your store's public identity: logo, contact, favicon, and the meta tags search engines and social apps see." />

                <form onSubmit={submit} className="max-w-2xl space-y-5 rounded-xl border p-5">
                    <BrandFile
                        label="Logo"
                        current={profile.logo}
                        accept="image/jpeg,image/png,image/webp,image/svg+xml"
                        hint="JPEG, PNG, WebP or SVG up to 2MB. Shown in the storefront header."
                        fileKey={fileKey}
                        error={errors.logo_file}
                        onPick={(file) => setData('logo_file', file)}
                        onRemove={() => removeFile('remove_logo', 'store logo')}
                        removing={removing === 'remove_logo'}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="profile-name">Store name</Label>
                        <Input id="profile-name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="My Store" />
                        {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                    </div>

                            <div className="grid gap-2">
                                <Label>Header preview</Label>
                                <div
                                    className="flex items-center justify-center gap-2.5 rounded-xl bg-[#0e7a3d] px-4 py-3 text-white"
                                >
                            <StoreLogo size="md" />
                            {data.show_store_name && (
                                <span className="text-[22px] leading-none font-bold tracking-tight">{data.name || 'My Store'}</span>
                            )}
                        </div>
                        <p className="text-muted-foreground text-xs">
                            How the logo and name look in the storefront header. Upload a logo above to replace the default mark.
                        </p>
                    </div>

                    <div className="flex items-center justify-between gap-4 rounded-xl border p-4">
                        <div>
                            <Label htmlFor="profile-show-store-name">Show store name</Label>
                            <p className="text-muted-foreground text-xs">Display the name beside the logo in the storefront header.</p>
                        </div>
                        <Switch
                            id="profile-show-store-name"
                            checked={data.show_store_name}
                            onCheckedChange={(checked) => setData('show_store_name', checked)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="profile-tagline">Tagline</Label>
                        <Input
                            id="profile-tagline"
                            value={data.tagline}
                            onChange={(e) => setData('tagline', e.target.value)}
                            placeholder="Everything you love, delivered"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="profile-email">Contact email</Label>
                            <Input id="profile-email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} placeholder="hello@example.com" dir="ltr" />
                            {errors.email && <p className="text-destructive text-xs">{errors.email}</p>}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="profile-phone">Contact phone</Label>
                            <Input id="profile-phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} placeholder="01XXXXXXXXX" dir="ltr" />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="profile-address">Address</Label>
                        <Input id="profile-address" value={data.address} onChange={(e) => setData('address', e.target.value)} placeholder="Street, house, area" />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="profile-city">City</Label>
                        <Input id="profile-city" value={data.city} onChange={(e) => setData('city', e.target.value)} placeholder="Dhaka" />
                    </div>

                    <div className="border-t pt-5">
                        <h2 className="text-base font-semibold">Search & social</h2>
                        <p className="text-muted-foreground mb-4 text-sm">Defaults used when a page does not set its own title, description, or share image.</p>

                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="profile-meta-title">Default meta title</Label>
                                <Input
                                    id="profile-meta-title"
                                    value={data.meta_title}
                                    onChange={(e) => setData('meta_title', e.target.value)}
                                    placeholder="My Store: quality products online"
                                    maxLength={255}
                                />
                                <p className="text-muted-foreground text-xs">{data.meta_title.length}/255: aim under 60 characters.</p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="profile-meta-description">Default meta description</Label>
                                <Textarea
                                    id="profile-meta-description"
                                    value={data.meta_description}
                                    onChange={(e) => setData('meta_description', e.target.value)}
                                    placeholder="Shop quality products with fast delivery and easy returns."
                                    rows={3}
                                    maxLength={500}
                                />
                                <p className="text-muted-foreground text-xs">{data.meta_description.length}/500: aim under 160 characters.</p>
                            </div>

                            <BrandFile
                                label="Favicon"
                                current={profile.favicon}
                                accept="image/jpeg,image/png,image/webp,image/svg+xml,image/x-icon"
                                hint="PNG, SVG or ICO up to 1MB. The tab icon."
                                fileKey={fileKey}
                                error={errors.favicon_file}
                                onPick={(file) => setData('favicon_file', file)}
                                onRemove={() => removeFile('remove_favicon', 'favicon')}
                                removing={removing === 'remove_favicon'}
                            />

                            <BrandFile
                                label="OG image"
                                current={profile.og_image}
                                accept="image/jpeg,image/png,image/webp"
                                hint="JPEG, PNG or WebP up to 2MB. Shown when sharing links without their own image (1200×630 ideal)."
                                fileKey={fileKey}
                                error={errors.og_image_file}
                                onPick={(file) => setData('og_image_file', file)}
                                onRemove={() => removeFile('remove_og_image', 'share image')}
                                removing={removing === 'remove_og_image'}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="profile-og-alt">Share image alt text</Label>
                                <Input
                                    id="profile-og-alt"
                                    value={data.og_image_alt}
                                    onChange={(e) => setData('og_image_alt', e.target.value)}
                                    placeholder="Front of the store on opening day"
                                    maxLength={255}
                                />
                                {errors.og_image_alt && <p className="text-destructive text-xs">{errors.og_image_alt}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="profile-twitter-card">X card layout</Label>
                                <select
                                    id="profile-twitter-card"
                                    value={data.twitter_card}
                                    onChange={(e) => setData('twitter_card', e.target.value)}
                                    className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm"
                                >
                                    <option value="summary_large_image">Large image</option>
                                    <option value="summary">Compact summary</option>
                                </select>
                                {errors.twitter_card && <p className="text-destructive text-xs">{errors.twitter_card}</p>}
                            </div>

                            <div className="flex items-center justify-between gap-4 rounded-xl border p-4">
                                <div>
                                    <Label htmlFor="profile-robots-noindex">Hide from search engines</Label>
                                    <p className="text-muted-foreground text-xs">Adds a noindex tag. Useful for demo or staging shops.</p>
                                </div>
                                <Switch
                                    id="profile-robots-noindex"
                                    checked={data.robots_noindex}
                                    onCheckedChange={(checked) => setData('robots_noindex', checked)}
                                />
                            </div>
                        </div>
                    </div>

                    <div className="border-t pt-5">
                        <h2 className="text-base font-semibold">Analytics & pixels</h2>
                        <p className="text-muted-foreground mb-4 text-sm">Leave a field empty to disable it. IDs are validated to safe formats only.</p>

                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="profile-google-tag">Google tag ID</Label>
                                <Input
                                    id="profile-google-tag"
                                    value={data.google_tag_id}
                                    onChange={(e) => setData('google_tag_id', e.target.value.toUpperCase())}
                                    placeholder="GTM-XXXXXX or G-XXXXXXXXXX"
                                    dir="ltr"
                                />
                                {errors.google_tag_id && <p className="text-destructive text-xs">{errors.google_tag_id}</p>}
                                <p className="text-muted-foreground text-xs">Tag Manager containers (GTM-…) or Analytics 4 IDs (G-…) load automatically.</p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="profile-gsv">Google site verification</Label>
                                <Input
                                    id="profile-gsv"
                                    value={data.google_site_verification}
                                    onChange={(e) => setData('google_site_verification', e.target.value)}
                                    placeholder="Verification token from Search Console"
                                    dir="ltr"
                                />
                                {errors.google_site_verification && <p className="text-destructive text-xs">{errors.google_site_verification}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="profile-pixel">Meta Pixel ID</Label>
                                <Input
                                    id="profile-pixel"
                                    value={data.meta_pixel_id}
                                    onChange={(e) => setData('meta_pixel_id', e.target.value.replace(/\D/g, ''))}
                                    placeholder="123456789012345"
                                    dir="ltr"
                                />
                                {errors.meta_pixel_id && <p className="text-destructive text-xs">{errors.meta_pixel_id}</p>}
                                <p className="text-muted-foreground text-xs">Fires PageView on every page.</p>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="profile-twitter">X (Twitter) handle</Label>
                                <Input
                                    id="profile-twitter"
                                    value={data.twitter_handle}
                                    onChange={(e) => setData('twitter_handle', e.target.value)}
                                    placeholder="@shop"
                                    dir="ltr"
                                />
                                {errors.twitter_handle && <p className="text-destructive text-xs">{errors.twitter_handle}</p>}
                                <p className="text-muted-foreground text-xs">Shown as the card author, e.g. @shop.</p>
                            </div>
                        </div>
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving...' : 'Save Profile'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
