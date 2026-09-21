<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TanzaniaPhone;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ];

        if (! $user->isAdmin()) {
            $rules['business_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['job_title'] = ['sometimes', 'nullable', 'string', 'max:255'];
            $rules['phone'] = [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! filled($value)) {
                        return;
                    }

                    if (! TanzaniaPhone::isValid((string) $value)) {
                        $fail('Enter a valid Tanzanian mobile number');
                    }
                },
            ];
        }

        $validated = $request->validate($rules);

        $user->fill(array_intersect_key($validated, array_flip(['name', 'email'])));
        $user->save();

        if (! $user->isAdmin()) {
            $customer = $user->customer ?? $user->customer()->create([
                'status' => CustomerStatus::PendingProfile,
                'sms_balance' => 0,
            ]);

            foreach (['business_name', 'job_title', 'phone'] as $field) {
                if (! array_key_exists($field, $validated)) {
                    continue;
                }

                $value = $validated[$field];

                if ($field === 'phone' && filled($value)) {
                    $value = TanzaniaPhone::normalize((string) $value);
                }

                $customer->{$field} = filled($value) ? $value : null;
            }

            $customer->refreshStatusFromProfile();
            $customer->save();
        }

        return response()->json([
            'user' => $user->fresh('customer')?->toAuthArray(),
            'csrf_token' => csrf_token(),
        ]);
    }
}
