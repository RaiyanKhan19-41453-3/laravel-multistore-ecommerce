<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->except(['avatar_file', 'remove_avatar']));

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        if ($request->boolean('remove_avatar')) {
            $this->deleteAvatarFile($user->avatar);
            $user->avatar = null;
        } elseif ($request->hasFile('avatar_file')) {
            $this->deleteAvatarFile($user->avatar);
            /** @var UploadedFile $file */
            $file = $request->file('avatar_file');
            $user->avatar = '/storage/'.$file->store('avatars', 'public');
        }

        $user->save();

        return to_route('profile.edit');
    }

    private function deleteAvatarFile(?string $avatar): void
    {
        if (! $avatar) {
            return;
        }

        $path = ltrim((string) preg_replace('#^/storage/#', '', $avatar), '/');

        if ($path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
