<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelegramAuthController extends Controller
{
    /**
     * Handle member login or account creation via Telegram phone number.
     * Only 1 member account is created per Telegram phone number.
     * Member type is always 'member'.
     * Different user types (root, super_senior, etc.) are rejected.
     */
    public function loginWithPhone(Request $request): RedirectResponse
    {
        $request->validate([
            'phone'    => ['required', 'string', 'min:7', 'max:20'],
            'username' => ['nullable', 'string', 'max:50'],
        ]);

        // Normalize phone number (strip whitespace, hyphens, parentheses)
        $rawPhone = trim($request->input('phone'));
        $cleanPhone = preg_replace('/[^\+0-9]/', '', $rawPhone);

        if (strlen(preg_replace('/[^0-9]/', '', $cleanPhone)) < 7) {
            throw ValidationException::withMessages([
                'phone' => 'Please provide a valid Telegram phone number (at least 7 digits).',
            ]);
        }

        // Look up existing member by Telegram phone number
        $user = User::where('login_name', $cleanPhone)->first();

        if (! $user) {
            // Option 2: Create new Member account with Telegram number
            $displayName = trim((string) $request->input('username'));
            if (empty($displayName)) {
                $suffix = substr(preg_replace('/[^0-9]/', '', $cleanPhone), -4);
                $displayName = 'TG_Member_' . $suffix;
            }

            // Ensure unique username if collision
            if (User::where('username', $displayName)->exists()) {
                $displayName .= '_' . Str::lower(Str::random(3));
            }

            $user = User::create([
                'username'           => $displayName,
                'login_name'         => $cleanPhone,
                'password'           => Hash::make(Str::random(32)),
                'encrypted_password' => null,
                'type'               => 'member',
                'status'             => true,
                'remark'             => "Telegram Member Account ({$cleanPhone})",
            ]);
        }

        // 1. Check if user is banned
        if (! (bool) $user->status) {
            throw ValidationException::withMessages([
                'phone' => 'User is ban',
            ]);
        }

        // 2. Check user type: ONLY type 'member' is allowed on storefront
        if ($user->type !== 'member') {
            throw ValidationException::withMessages([
                'phone' => 'Access denied: Non-member accounts cannot log in to the member site.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', "Welcome, {$user->username}! Connected with Telegram ({$cleanPhone}).");
    }

    /**
     * Optional callback for Telegram Login Widget (if configured with Telegram Bot).
     */
    public function callback(Request $request): RedirectResponse
    {
        $id = $request->input('id');
        $firstName = $request->input('first_name', 'Telegram User');
        $username = $request->input('username');

        if (! $id) {
            return redirect()->route('home')->with('error', 'Telegram authentication failed.');
        }

        $loginName = 'tg_' . $id;
        $user = User::where('login_name', $loginName)->first();

        if (! $user) {
            $displayName = $username ? '@' . $username : $firstName;
            if (User::where('username', $displayName)->exists()) {
                $displayName .= '_' . substr($id, -3);
            }

            $user = User::create([
                'username'           => $displayName,
                'login_name'         => $loginName,
                'password'           => Hash::make(Str::random(32)),
                'encrypted_password' => null,
                'type'               => 'member',
                'status'             => true,
                'remark'             => "Telegram Widget Account (ID: {$id})",
            ]);
        }

        if (! (bool) $user->status) {
            return redirect()->route('home')->with('error', 'User is ban');
        }

        if ($user->type !== 'member') {
            return redirect()->route('home')->with('error', 'Access denied: Non-member accounts cannot log in to the member site.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', "Welcome, {$user->username}! Connected with Telegram.");
    }
}
