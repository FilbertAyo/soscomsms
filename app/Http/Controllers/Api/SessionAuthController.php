<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\WelcomeCustomerNotification;
use App\Services\AuthOtpService;
use App\Services\CreditLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SessionAuthController extends Controller
{
    public function register(Request $request, AuthOtpService $otp): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        /** @var User $user */
        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'customer',
            ]);

            $customer = $user->customer()->create([
                'status' => CustomerStatus::PendingProfile,
                'sms_balance' => 0,
            ]);

            app(CreditLedger::class)->grantWelcome($customer);

            return $user;
        });

        $user->notify(new WelcomeCustomerNotification((int) config('sms.welcome_credits', 20)));

        return response()->json($otp->issue($user), 201);
    }

    public function login(Request $request, AuthOtpService $otp): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ])) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = User::query()->where('email', $credentials['email'])->firstOrFail();

        return response()->json($otp->issue($user, $request->boolean('remember')));
    }

    public function verifyOtp(Request $request, AuthOtpService $otp): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $result = $otp->consume($validated['challenge'], $validated['otp']);
        $user = $result['user'];

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::guard('web')->login($user, $result['remember']);
        $request->session()->regenerate(true);

        return response()->json($this->sessionPayload($user->fresh('customer') ?? $user));
    }

    public function resendOtp(Request $request, AuthOtpService $otp): JsonResponse
    {
        $validated = $request->validate([
            'challenge' => ['required', 'string'],
        ]);

        return response()->json($otp->resend($validated['challenge']));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Signed out.',
            'csrf_token' => csrf_token(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->sessionPayload($user));
    }

    /**
     * @return array{user: array<string, mixed>, csrf_token: string}
     */
    private function sessionPayload(User $user): array
    {
        return [
            'user' => $user->toAuthArray(),
            'csrf_token' => csrf_token(),
        ];
    }
}
