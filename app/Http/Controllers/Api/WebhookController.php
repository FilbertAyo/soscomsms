<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WebhookController extends Controller implements HasMiddleware
{
    use ResolvesCustomer;

    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next) {
                abort_unless((bool) config('sms.webhooks.enabled'), 404);

                return $next($request);
            }),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        return response()->json([
            'webhooks' => $customer->webhooks()->latest('id')->get()->map->toApiArray()->values(),
        ]);
    }

    public function store(Request $request, WebhookDispatcher $dispatcher): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'url' => ['required', 'string', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
        ]);

        $created = $dispatcher->create(
            $customer,
            $validated['name'],
            $validated['url'],
            $validated['events'],
        );

        return response()->json([
            'webhook' => $created['webhook']->toApiArray(),
            'secret' => $created['secret'],
            'csrf_token' => csrf_token(),
        ], 201);
    }

    public function update(Request $request, Webhook $webhook, WebhookDispatcher $dispatcher): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($webhook->customer_id === $customer->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:80'],
            'url' => ['sometimes', 'string', 'max:500'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $updated = $dispatcher->update($webhook, $validated);

        return response()->json([
            'webhook' => $updated->toApiArray(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function destroy(Request $request, Webhook $webhook, WebhookDispatcher $dispatcher): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($webhook->customer_id === $customer->id, 404);

        $dispatcher->delete($webhook);

        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    }

    public function rotateSecret(Request $request, Webhook $webhook, WebhookDispatcher $dispatcher): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($webhook->customer_id === $customer->id, 404);

        $rotated = $dispatcher->rotateSecret($webhook);

        return response()->json([
            'webhook' => $rotated['webhook']->toApiArray(),
            'secret' => $rotated['secret'],
            'csrf_token' => csrf_token(),
        ]);
    }

    public function deliveries(Request $request, Webhook $webhook): JsonResponse
    {
        $customer = $this->currentCustomer($request);
        abort_unless($webhook->customer_id === $customer->id, 404);

        $rows = $webhook->deliveries()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['deliveries' => $rows]);
    }
}
