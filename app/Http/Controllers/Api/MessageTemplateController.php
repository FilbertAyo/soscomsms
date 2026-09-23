<?php

namespace App\Http\Controllers\Api;

use App\Enums\MessageEncoding;
use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Services\SegmentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageTemplateController extends Controller
{
    use ResolvesCustomer;

    public function __construct(private SegmentationService $segmentation) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $templates = $customer->messageTemplates()
            ->orderBy('name')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['templates' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $validated = $this->validated($request, $customer->id);
        $template = $customer->messageTemplates()->create($this->attributes($validated));

        return response()->json([
            'template' => $template->toApiArray(),
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function update(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($messageTemplate->customer_id === $customer->id, 404);

        $validated = $this->validated($request, $customer->id, $messageTemplate->id);
        $messageTemplate->update($this->attributes($validated));

        return response()->json([
            'template' => $messageTemplate->toApiArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function destroy(Request $request, MessageTemplate $messageTemplate): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($messageTemplate->customer_id === $customer->id, 404);

        $messageTemplate->delete();

        return response()->json([
            'message' => 'Template deleted.',
            'csrf_token' => csrf_token(),
        ]);
    }

    /**
     * @return array{name: string, body: string}
     */
    private function validated(Request $request, int $customerId, ?int $ignoreId = null): array
    {
        $unique = Rule::unique('message_templates', 'name')->where('customer_id', $customerId);
        if ($ignoreId !== null) {
            $unique->ignore($ignoreId);
        }

        /** @var array{name: string, body: string} $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', $unique],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        return $validated;
    }

    /**
     * @param  array{name: string, body: string}  $validated
     * @return array{name: string, body: string, encoding: MessageEncoding, character_count: int, segment_count: int}
     */
    private function attributes(array $validated): array
    {
        $analysis = $this->segmentation->analyze($validated['body']);

        return [
            'name' => $validated['name'],
            'body' => $validated['body'],
            'encoding' => MessageEncoding::from($analysis['encoding']),
            'character_count' => $analysis['characterCount'],
            'segment_count' => $analysis['segmentCount'],
        ];
    }
}
