<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use App\Services\UserManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($filters['search'] ?? '');

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'is_active'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.form', [
            'user' => new User,
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(
        SaveUserRequest $request,
        UserManager $users
    ): RedirectResponse {
        $users->create($request->user('web'), $request->validated());

        return redirect()->route('users.index')
            ->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.form', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(
        SaveUserRequest $request,
        User $user,
        UserManager $users
    ): RedirectResponse {
        $users->update($request->user('web'), $user, $request->validated());

        return redirect()->route('users.index')
            ->with('status', 'User updated.');
    }
}
