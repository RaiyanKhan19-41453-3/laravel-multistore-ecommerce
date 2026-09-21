import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import DeleteUser from '@/components/delete-user';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: '/settings/profile',
    },
];

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<SharedData>().props;

    const { data, setData, post, errors, processing, recentlySuccessful } = useForm({
        name: auth.user.name,
        email: auth.user.email,
        avatar_file: null as File | null,
        _method: 'PATCH',
    });
    const [preview, setPreview] = useState<string | null>(null);
    const avatarSrc = preview ?? auth.user.avatar ?? null;

    const pickAvatar = (file: File | null) => {
        setPreview((old) => {
            if (old) URL.revokeObjectURL(old);
            return null;
        });
        if (file) setPreview(URL.createObjectURL(file));
        setData('avatar_file', file);
    };

    const removeAvatar = () => {
        pickAvatar(null);
        router.post(
            route('profile.update'),
            { name: data.name, email: data.email, remove_avatar: true, _method: 'PATCH' },
            { preserveScroll: true },
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // POST with spoofed PATCH: PHP discards multipart bodies on real
        // PATCH/PUT requests, so the file would never arrive otherwise.
        post(route('profile.update'), {
            onSuccess: () => pickAvatar(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Profile information" description="Update your name and email address" />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="flex items-center gap-4">
                            {avatarSrc ? (
                                <img src={avatarSrc} alt={data.name} className="h-16 w-16 rounded-full object-cover" />
                            ) : (
                                <span className="flex h-16 w-16 items-center justify-center rounded-full bg-neutral-200 text-xl font-bold dark:bg-neutral-700">
                                    {data.name.charAt(0).toUpperCase()}
                                </span>
                            )}
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="avatar">Profile photo</Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="avatar"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={(e) => pickAvatar(e.target.files?.[0] ?? null)}
                                        className="max-w-64 cursor-pointer"
                                    />
                                    {(avatarSrc || auth.user.avatar) && (
                                        <Button type="button" variant="outline" onClick={removeAvatar}>
                                            Remove
                                        </Button>
                                    )}
                                </div>
                                <InputError className="mt-0" message={errors.avatar_file} />
                                <p className="text-xs text-neutral-500">JPEG, PNG or WebP up to 2MB.</p>
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>

                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoComplete="name"
                                placeholder="Full name"
                            />

                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>

                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                autoComplete="username"
                                placeholder="Email address"
                            />

                            <InputError className="mt-2" message={errors.email} />
                        </div>

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <div>
                                <p className="mt-2 text-sm text-neutral-800">
                                    Your email address is unverified.
                                    <Link
                                        href={route('verification.send')}
                                        method="post"
                                        as="button"
                                        className="rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
                                    >
                                        Click here to re-send the verification email.
                                    </Link>
                                </p>

                                {status === 'verification-link-sent' && (
                                    <div className="mt-2 text-sm font-medium text-green-600">
                                        A new verification link has been sent to your email address.
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Save</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Saved</p>
                            </Transition>
                        </div>
                    </form>
                </div>

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
