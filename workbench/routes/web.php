<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * A deliberately minimal login for the workbench only: the package ships no login, and a
 * host application brings its own.
 */
Route::middleware('web')->group(function (): void {
    Route::get('/', static fn () => redirect('/artisan'));

    Route::get('/login', static fn () => response(
        '<form method="post" action="/login"><input type="hidden" name="_token" value="' . csrf_token() . '">'
        . '<label>Email <input name="email" value="admin@example.test"></label> '
        . '<label>Password <input name="password" type="password"></label> <button>Sign in</button></form>',
    ))->name('login');

    Route::post('/login', static function (Request $request) {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt(['email' => (string) ($credentials['email'] ?? ''), 'password' => (string) ($credentials['password'] ?? '')])) {
            $request->session()->regenerate();

            return redirect()->intended('/artisan');
        }

        return back()->withErrors(['email' => 'Those credentials do not match.']);
    });

    Route::post('/logout', static function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();

        return redirect('/login');
    })->name('logout');

    Route::get('/whoami', static fn () => ['user' => Auth::user() instanceof User ? Auth::user()->email : null]);
});
