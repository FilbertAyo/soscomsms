<?php

namespace App\Http\Controllers\Api;

use App\Enums\IdentityDocumentType;
use App\Enums\SenderIdUseCase;
use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\SenderIdRequest;
use App\Services\SenderIdService;
use App\Support\SenderIdName;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class SenderIdRequestController extends Controller
{
    use ResolvesCustomer;

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $requests = $customer->senderIdRequests()
            ->with([
                'senderId',
                'identityDocument' => fn ($query) => $query->select([
                    'id',
                    'sender_id_request_id',
                    'original_name',
                    'size',
                ]),
            ])
            ->latest('id')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['requests' => $requests]);
    }

    public function show(Request $request, SenderIdRequest $senderIdRequest): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($senderIdRequest->customer_id === $customer->id, 404);

        return response()->json([
            'request' => $senderIdRequest->toApiArray(),
        ]);
    }

    public function identityDocument(Request $request, SenderIdRequest $senderIdRequest): Response
    {
        $customer = $this->currentCustomer($request);
        abort_unless($senderIdRequest->customer_id === $customer->id, 404);

        return $senderIdRequest->identityDocumentResponse();
    }

    public function store(Request $request, SenderIdService $senderIds): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        if ($request->filled('name')) {
            $request->merge([
                'name' => SenderIdName::normalize($request->string('name')->toString()),
            ]);
        }

        if ($request->exists('sample_content')) {
            $request->merge([
                'sample_content' => trim($request->string('sample_content')->toString()),
            ]);
        }

        if ($request->exists('website_url')) {
            $website = trim($request->string('website_url')->toString());
            if ($website !== '' && ! preg_match('/^https?:\/\//i', $website)) {
                $website = 'https://'.$website;
            }
            $request->merge([
                'website_url' => $website === '' ? null : $website,
            ]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:13',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! SenderIdName::isValid((string) $value)) {
                        $fail(SenderIdName::invalidMessage());
                    }
                },
            ],
            'purpose' => ['required', Rule::enum(SenderIdUseCase::class)],
            'organization' => ['required', 'string', 'max:120'],
            'website_url' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sample_content' => ['required', 'string', 'min:1', 'max:160'],
            'identity_document_type' => ['required', Rule::enum(IdentityDocumentType::class)],
            'identity_document' => [
                'required',
                'file',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! is_string($path = $value->getRealPath())) {
                        $fail('Upload a PDF of the selected identity document.');

                        return;
                    }

                    $header = file_get_contents($path, false, null, 0, 5);
                    if (! is_string($header) || ! str_starts_with($header, '%PDF')) {
                        $fail('Upload a PDF of the selected identity document.');
                    }
                },
            ],
        ]);

        /** @var UploadedFile $document */
        $document = $validated['identity_document'];
        unset($validated['identity_document']);

        $created = $senderIds->request($customer, $validated, $document);

        return response()->json([
            'request' => $created->toApiArray(),
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function resubmit(
        Request $request,
        SenderIdRequest $senderIdRequest,
        SenderIdService $senderIds,
    ): JsonResponse {
        $customer = $this->currentCustomer($request);
        abort_unless($senderIdRequest->customer_id === $customer->id, 404);

        $validated = $request->validate([
            'identity_document_type' => ['required', Rule::enum(IdentityDocumentType::class)],
            'identity_document' => [
                'required',
                'file',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! is_string($path = $value->getRealPath())) {
                        $fail('Upload a PDF of the selected identity document.');

                        return;
                    }

                    $header = file_get_contents($path, false, null, 0, 5);
                    if (! is_string($header) || ! str_starts_with($header, '%PDF')) {
                        $fail('Upload a PDF of the selected identity document.');
                    }
                },
            ],
        ]);

        /** @var UploadedFile $document */
        $document = $validated['identity_document'];

        $updated = $senderIds->resubmitDocument(
            $senderIdRequest,
            $validated['identity_document_type'],
            $document,
        );

        return response()->json([
            'request' => $updated->toApiArray(),
            'csrf_token' => csrf_token(),
        ]);
    }
}
