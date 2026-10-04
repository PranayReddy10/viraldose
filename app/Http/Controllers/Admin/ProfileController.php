<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private ImageService $images) {}

    public function edit(Request $request)
    {
        return view('admin.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'url', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', Password::min(8), 'confirmed'],
        ]);
        unset($data['current_password'], $data['avatar']);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if ($request->hasFile('avatar')) {
            $this->images->delete($user->avatar);
            $data['avatar'] = $this->images->store($request->file('avatar'), 'uploads/avatars');
        }
        $user->update($data);

        return back()->with('status', 'Profile updated.');
    }
}
