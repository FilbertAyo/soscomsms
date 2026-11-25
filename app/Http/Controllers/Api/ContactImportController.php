<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Services\ContactImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactImportController extends Controller
{
    use ResolvesCustomer;

    public function store(Request $request, ContactImporter $importer): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        if ($request->has('group_name') && is_string($request->input('group_name'))) {
            $request->merge([
                'group_name' => trim($request->input('group_name')),
            ]);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx,xls', 'max:2048'],
            'name_column' => ['nullable', 'string', 'max:80'],
            'phone_column' => ['nullable', 'string', 'max:80'],
            'has_header' => ['nullable', 'boolean'],
            'name_index' => ['nullable', 'integer', 'min:0'],
            'phone_index' => ['nullable', 'integer', 'min:0'],
            'contact_group_id' => [
                'nullable',
                'integer',
                'prohibits:group_name',
                'required_without:group_name',
                Rule::exists('contact_groups', 'id')->where('customer_id', $customer->id),
            ],
            'group_name' => [
                'nullable',
                'string',
                'max:80',
                'prohibits:contact_group_id',
                'required_without:contact_group_id',
            ],
        ], [
            'contact_group_id.required_without' => 'Choose a group for these contacts.',
            'group_name.required_without' => 'Choose a group for these contacts.',
            'contact_group_id.prohibits' => 'Choose an existing group or a new name, not both.',
            'group_name.prohibits' => 'Choose an existing group or a new name, not both.',
            'contact_group_id.exists' => 'Choose a group that belongs to this account.',
        ]);

        $result = $importer->import(
            $customer,
            $validated['file'],
            $validated['name_column'] ?? 'name',
            $validated['phone_column'] ?? 'phone',
            $request->boolean('has_header', true),
            $request->exists('name_index') ? $request->integer('name_index') : null,
            $request->exists('phone_index') ? $request->integer('phone_index') : null,
            isset($validated['contact_group_id']) ? (int) $validated['contact_group_id'] : null,
            $validated['group_name'] ?? null,
        );

        return response()->json([
            ...$result,
            'status' => 'completed',
            'csrf_token' => csrf_token(),
        ]);
    }
}
