<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactGroupController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $groups = $customer->contactGroups()
            ->withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['groups' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('contact_groups', 'name')->where('customer_id', $customer->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $group = $customer->contactGroups()->create($validated);
        $group->loadCount('contacts');

        return response()->json([
            'group' => $group->toApiArray(),
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function update(Request $request, ContactGroup $contactGroup): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($contactGroup->customer_id === $customer->id, 404);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('contact_groups', 'name')
                    ->where('customer_id', $customer->id)
                    ->ignore($contactGroup->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $contactGroup->update($validated);
        $contactGroup->loadCount('contacts');

        return response()->json([
            'group' => $contactGroup->toApiArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function destroy(Request $request, ContactGroup $contactGroup): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($contactGroup->customer_id === $customer->id, 404);

        $contactGroup->contacts()->update(['contact_group_id' => null]);
        $contactGroup->delete();

        return response()->json([
            'message' => 'Group deleted.',
            'csrf_token' => csrf_token(),
        ]);
    }
}
