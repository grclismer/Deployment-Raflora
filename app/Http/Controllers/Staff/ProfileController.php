<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->role === 'staff', 403);

        return view('staff.account-settings', ['user' => $request->user()]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'staff', 403);

        $validated = $request->validate([
            'profile_image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $oldImage = $user->profile_image;
        $newImage = $validated['profile_image']->store('profile-images', 'public');

        if (! $newImage) {
            throw new RuntimeException('The staff profile photo could not be stored.');
        }

        try {
            $user->profile_image = $newImage;
            $user->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newImage);
            throw $exception;
        }

        if ($oldImage && $oldImage !== $newImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('staff.profile.edit')->with('success', 'Profile photo updated successfully.');
    }
}
