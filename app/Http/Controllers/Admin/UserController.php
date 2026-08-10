<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin accounts and their roles (§9.1, §19.3).
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('users.manage'), 403);

        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()->with('roles')->orderBy('name')->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'isActive' => $user->is_active,
                    'roles' => $user->roles->pluck('name'),
                    'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                ]),
            'roles' => User::ROLES,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('users.manage'), 403);

        return Inertia::render('Admin/Users/Edit', [
            'user' => null,
            'roles' => User::ROLES,
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        abort_unless($request->user()->can('users.manage'), 403);

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'isActive' => $user->is_active,
                'roles' => $user->roles->pluck('name'),
            ],
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(12)->letters()->numbers()->symbols()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(User::ROLES)],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $user->syncRoles($data['roles']);

        return redirect()->route('admin.users.index')->with('success', __('admin.saved'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(12)->letters()->numbers()->symbols()],
            'is_active' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(User::ROLES)],
        ]);

        $isSelf = $user->is($request->user());

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            // Locking yourself out of the panel you administer is not a
            // decision the UI should let you make by accident.
            'is_active' => $isSelf ? true : (bool) ($data['is_active'] ?? true),
        ]);

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (! $isSelf) {
            $user->syncRoles($data['roles']);
        }

        return back()->with('success', __('admin.saved'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('users.manage'), 403);
        abort_if($user->is($request->user()), 422);

        // Deactivated, not deleted: the activity log must keep pointing at a
        // real person (§9.1 audit trail).
        $user->forceFill(['is_active' => false])->save();

        return redirect()->route('admin.users.index')->with('success', __('admin.user_disabled'));
    }
}
