<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the login identifier (login_name or username).
     */
    public function getLoginIdentifier(): string
    {
        return (string) ($this->input('login_name') ?? $this->input('username') ?? $this->input('name') ?? '');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login_name' => ['required_without_all:username,name', 'nullable', 'string'],
            'username'   => ['required_without_all:login_name,name', 'nullable', 'string'],
            'name'       => ['nullable', 'string'],
            'password'   => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials by username or login_name.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = $this->getLoginIdentifier();

        // Authenticate by username OR login_name
        $user = User::where('login_name', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $user || ! Hash::check($this->input('password'), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login_name' => trans('auth.failed'),
            ]);
        }

        // Check if account is active or banned
        if (! (bool) $user->status) {
            throw ValidationException::withMessages([
                'login_name' => 'User is ban',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login_name' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->getLoginIdentifier()) . '|' . $this->ip());
    }
}
