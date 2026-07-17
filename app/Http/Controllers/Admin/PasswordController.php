<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('admin.password.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'current_password' => ['required', 'current_password'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(12)->letters()->mixedCase()->numbers(),
                ],
            ],
            [
                'current_password.required' => 'Bitte geben Sie Ihr aktuelles Passwort ein.',
                'current_password.current_password' => 'Das aktuelle Passwort ist nicht korrekt.',
                'password.required' => 'Bitte geben Sie ein neues Passwort ein.',
                'password.confirmed' => 'Die beiden neuen Passwörter stimmen nicht überein.',
                'password.min' => 'Das neue Passwort muss mindestens 12 Zeichen lang sein.',
                'password.letters' => 'Das neue Passwort muss Buchstaben enthalten.',
                'password.mixed' => 'Das neue Passwort muss Groß- und Kleinbuchstaben enthalten.',
                'password.numbers' => 'Das neue Passwort muss mindestens eine Zahl enthalten.',
            ],
        );

        $user = $request->user();
        $user->password = $validated['password'];
        $user->save();
        $request->session()->regenerate();

        return back()->with('status', 'Passwort wurde geändert.');
    }
}
