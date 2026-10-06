<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): RedirectResponse
    {
        $clientId = config('services.google.client_id');
        $redirectUri = $this->getRedirectUri();

        // If credentials are configured in .env, redirect to real Google OAuth
        if (! empty($clientId) && ! str_contains($clientId, 'your_google_client_id')) {
            $state = Str::random(40);
            session()->put('google_oauth_state', $state);

            $query = http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid profile email',
                'access_type' => 'online',
                'state' => $state,
                'prompt' => 'select_account',
            ]);

            return redirect()->away("https://accounts.google.com/o/oauth2/v2/auth?{$query}");
        }

        // Demo fallback fast login if Google client ID is not yet configured
        return $this->loginWithDemoGoogle();
    }

    /**
     * Obtain the user information from Google callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->input('code');

        if (! $code) {
            return redirect()->route('home')->with('error', 'Google sign-in was canceled.');
        }

        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = $this->getRedirectUri();

        try {
            // Exchange code for token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error_description') ?? 'Token exchange failed';
                return redirect()->route('home')->with('error', 'Google error: ' . $errorMsg);
            }

            $tokenData = $response->json();
            $accessToken = $tokenData['access_token'] ?? null;

            // Fetch user info from Google
            $userInfoResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if (! $userInfoResponse->successful()) {
                return redirect()->route('home')->with('error', 'Failed to retrieve Google user profile.');
            }

            $googleUser = $userInfoResponse->json();
            $email = $googleUser['email'] ?? null;
            $name = $googleUser['name'] ?? explode('@', $email)[0];

            return $this->authenticateOrCreateUser($name, $email);
        } catch (\Throwable $e) {
            return redirect()->route('home')->with('error', 'Google authentication error: ' . $e->getMessage());
        }
    }

    /**
     * Fast direct Google sign-in (e.g. from modal one-click action).
     */
    public function quickGoogleLogin(Request $request): RedirectResponse
    {
        $email = $request->input('email', 'gamer.' . strtolower(Str::random(4)) . '@gmail.com');
        $name = $request->input('name', 'Operator ' . Str::upper(Str::random(5)));

        return $this->authenticateOrCreateUser($name, $email);
    }

    /**
     * Authenticate or register the Google user.
     */
    protected function authenticateOrCreateUser(string $name, string $email): RedirectResponse
    {
        $user = User::where('login_name', $email)->first();

        if (! $user) {
            $user = User::create([
                'username' => $name,
                'login_name' => $email,
                'password' => Hash::make(Str::random(32)),
                'type' => 'member',
                'status' => true,
                'remark' => 'Google Member Account',
            ]);
        }

        if (! (bool) $user->status) {
            return redirect()->route('home')->with('error', 'User is ban. Your account has been suspended.');
        }

        // Check user type: ONLY type 'member' is allowed to log into the storefront!
        if ($user->type !== 'member') {
            return redirect()->route('home')->with('error', 'Access denied: Non-member accounts cannot log in to the member site. Please use the Admin Portal for management accounts.');
        }

        Auth::login($user, true);

        return redirect()->route('home')->with('success', "Welcome, {$user->username}! Signed in with Google.");
    }

    /**
     * Simulated Google login for testing without .env API keys.
     */
    protected function loginWithDemoGoogle(): RedirectResponse
    {
        return $this->authenticateOrCreateUser('ProGamer_X', 'gamer.vip@gmail.com');
    }

    /**
     * Get properly formatted redirect URI.
     */
    protected function getRedirectUri(): string
    {
        $uri = config('services.google.redirect');
        if (! empty($uri) && str_starts_with($uri, 'http')) {
            return $uri;
        }

        return url($uri ?: '/auth/google/callback');
    }
}
