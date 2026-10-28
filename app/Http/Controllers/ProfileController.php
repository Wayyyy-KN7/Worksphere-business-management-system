<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('department');

        return view('profile.show', compact('user'));
    }

    public function edit(Request $request)
    {
        $user = $request->user()->load('department');

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user->name = $validated['name'];
        $user->phone = $validated['phone'];

        if ($request->hasFile('profile_photo')) {
            $oldPhotoPath = $user->profile_photo_path;
            $newPhotoPath = $request->file('profile_photo')->store("profile-photos/{$user->id}", 'public');
            $user->profile_photo_path = $newPhotoPath;

            if ($oldPhotoPath) {
                Storage::disk('public')->delete($oldPhotoPath);
            }
        }

        $user->save();

        return redirect()->route('profile.show')->with('success', 'Profile updated successfully.');
    }

    public function updatePhoto(Request $request)
    {
        $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $oldPhotoPath = $user->profile_photo_path;
        $newPhotoPath = $request->file('profile_photo')->store("profile-photos/{$user->id}", 'public');

        $user->update(['profile_photo_path' => $newPhotoPath]);

        if ($oldPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()->route('profile.show')->with('success', 'Profile photo updated.');
    }

    public function destroyPhoto(Request $request)
    {
        $user = $request->user();
        $photoPath = $user->profile_photo_path;

        $user->update(['profile_photo_path' => null]);

        if ($photoPath) {
            Storage::disk('public')->delete($photoPath);
        }

        return redirect()->route('profile.show')->with('success', 'Profile photo removed.');
    }
}