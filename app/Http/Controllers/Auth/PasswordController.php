<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\UserManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(
        UpdatePasswordRequest $request,
        UserManager $users
    ): RedirectResponse {
        $data = $request->validated();

        $user = $users->changePassword(
            $request->user('web'),
            $data['current_password'],
            $data['password']
        );

        $guard = Auth::guard('web');
        $guard->setUser($user);
        $guard->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Password changed. Sign in with your new password.');
    }
}
