<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContactStatus;
use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\PaginationMeta;
use App\Support\TanzaniaPhone;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $contacts = $customer->contacts()
            ->with('group')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('group', function ($group) use ($term): void {
                            $group->where('name', 'like', $term);
                        });
                });
            })
            ->when($request->filled('group_id'), function ($query) use ($request): void {
                $query->where('contact_group_id', $request->integer('group_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->latest('id')
            ->paginate(PaginationMeta::perPage($request, 10, 500));

        return response()->json([
            'contacts' => $contacts->getCollection()->map->toApiArray()->values(),
            'meta' => PaginationMeta::from($contacts),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $validated = $this->validatedContact($request, $customer->id);

        $contact = $customer->contacts()->create([
            'name' => $validated['name'],
            'phone' => TanzaniaPhone::normalize($validated['phone']),
            'contact_group_id' => $validated['contact_group_id'] ?? null,
            'status' => ContactStatus::Active,
        ]);

        return response()->json([
            'contact' => $contact->load('group')->toApiArray(),
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($contact->customer_id === $customer->id, 404);

        $validated = $this->validatedContact($request, $customer->id, $contact->id);

        if (array_key_exists('phone', $validated)) {
            $validated['phone'] = TanzaniaPhone::normalize($validated['phone']);
        }

        $contact->update($validated);

        return response()->json([
            'contact' => $contact->fresh('group')?->toApiArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    /**
     * @return array{name?: string, phone?: string, contact_group_id?: int|null, status?: string}
     */
    private function validatedContact(Request $request, int $customerId, ?int $ignoreId = null): array
    {
        if ($request->filled('phone')) {
            $request->merge([
                'phone' => TanzaniaPhone::normalize($request->string('phone')->toString()),
            ]);
        }

        $phoneUnique = Rule::unique('contacts', 'phone')->where('customer_id', $customerId);
        if ($ignoreId !== null) {
            $phoneUnique = $phoneUnique->ignore($ignoreId);
        }

        $rules = [
            'name' => [$ignoreId === null ? 'required' : 'sometimes', 'string', 'max:120'],
            'phone' => [
                $ignoreId === null ? 'required' : 'sometimes',
                'string',
                'max:20',
                $phoneUnique,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! TanzaniaPhone::isValid((string) $value)) {
                        $fail('Enter a valid Tanzanian mobile number');
                    }
                },
            ],
            'contact_group_id' => [
                'nullable',
                Rule::exists('contact_groups', 'id')->where('customer_id', $customerId),
            ],
            'status' => ['sometimes', Rule::enum(ContactStatus::class)],
        ];

        return $request->validate($rules);
    }
}
