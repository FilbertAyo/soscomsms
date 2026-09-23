<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Services\ContactImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactImportController extends Controller
{
    use ResolvesCustomer;

    public function store(Request $request, ContactImporter $importer): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $validated = $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
            'name_column' => ['nullable', 'string', 'max:80'],
            'phone_column' => ['nullable', 'string', 'max:80'],
            'group_column' => ['nullable', 'string', 'max:80'],
            'has_header' => ['nullable', 'boolean'],
            'name_index' => ['nullable', 'integer', 'min:0'],
            'phone_index' => ['nullable', 'integer', 'min:0'],
            'group_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $importer->import(
            $customer,
            $validated['file'],
            $validated['name_column'] ?? 'name',
            $validated['phone_column'] ?? 'phone',
            $validated['group_column'] ?? 'group',
            $request->boolean('has_header', true),
            $request->exists('name_index') ? $request->integer('name_index') : null,
            $request->exists('phone_index') ? $request->integer('phone_index') : null,
            $request->exists('group_index') ? $request->integer('group_index') : null,
        );

        return response()->json([
            ...$result,
            'status' => 'completed',
            'csrf_token' => csrf_token(),
        ]);
    }
}
