<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Jobs\ImportContactsJob;
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
        ]);

        $nameColumn = $validated['name_column'] ?? 'name';
        $phoneColumn = $validated['phone_column'] ?? 'phone';
        $groupColumn = $validated['group_column'] ?? 'group';

        if ((string) config('queue.default') === 'sync') {
            $result = $importer->import(
                $customer,
                $validated['file'],
                $nameColumn,
                $phoneColumn,
                $groupColumn,
            );

            return response()->json([
                ...$result,
                'status' => 'completed',
                'csrf_token' => csrf_token(),
            ]);
        }

        $path = $validated['file']->store('contact-imports');
        ImportContactsJob::dispatch(
            $customer->id,
            $path,
            $nameColumn,
            $phoneColumn,
            $groupColumn,
        );

        return response()->json([
            'status' => 'queued',
            'created' => 0,
            'skipped' => 0,
            'invalid' => 0,
            'errors' => [],
            'message' => 'Your contacts are importing in the background.',
            'csrf_token' => csrf_token(),
        ]);
    }
}
