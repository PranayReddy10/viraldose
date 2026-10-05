<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private ImageService $images) {}

    public function index()
    {
        return view('admin.users.index', ['users' => User::withCount('posts')->orderBy('name')->paginate(30)]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['role' => User::ROLE_AUTHOR, 'is_active' => true])]);
    }

    public function store(UserRequest $request)
    {
        $data = $request->safe()->except(['avatar', 'password_confirmation']);
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $this->images->store($request->file('avatar'), 'uploads/avatars');
        }
        User::create($data);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', compact('user'));
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->safe()->except(['avatar', 'password', 'password_confirmation']);
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }
        if ($request->hasFile('avatar')) {
            $old = $user->avatar;
            $data['avatar'] = $this->images->store($request->file('avatar'), 'uploads/avatars');
            $this->images->delete($old);
        }
        if ($user->id === $request->user()->id) {
            // Never let an admin lock themselves out.
            $data['role'] = User::ROLE_ADMIN;
            $data['is_active'] = true;
        }
        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }
        if ($user->posts()->withTrashed()->exists()) {
            return back()->withErrors(['user' => 'Reassign or delete this user\'s posts first.']);
        }
        $this->images->delete($user->avatar);
        $user->delete();

        return back()->with('status', 'User deleted.');
    }
}
