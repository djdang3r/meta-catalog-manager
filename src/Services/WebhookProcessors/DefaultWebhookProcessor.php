<?php

namespace ScriptDevelop\MetaCatalogManager\Services\WebhookProcessors;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\MetaCatalogManager\Contracts\WebhookProcessorInterface;

class DefaultWebhookProcessor implements WebhookProcessorInterface
{
    public function handle(Request $request): Response|JsonResponse
    {
        if ($request->isMethod('GET')) {
            return $this->verifyWebhook(
                $request,
                config('meta-catalog.webhook.verify_token', '')
            );
        }

        if ($request->isMethod('POST')) {
            $payload = $request->all();

            if (!$this->verifySignature($request)) {
                Log::channel(config('meta-catalog.logging.channel', 'stack'))
                    ->warning('Meta Catalog webhook: invalid signature');

                return response()->json(['error' => 'Invalid signature'], 403);
            }

            $this->dispatchEvent($payload);

            return response()->json(['success' => true]);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }

    public function verifyWebhook(Request $request, string $verifyToken): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::channel(config('meta-catalog.logging.channel', 'stack'))
                ->info('Meta Catalog webhook: verification successful');

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::channel(config('meta-catalog.logging.channel', 'stack'))
            ->warning('Meta Catalog webhook: verification failed', [
                'mode'  => $mode,
                'token' => $token,
            ]);

        return response('Verification failed', 403);
    }

    public function processProductFeed(array $payload): void
    {
        Log::channel(config('meta-catalog.logging.channel', 'stack'))
            ->info('Meta Catalog webhook: product_feed event received', [
                'payload' => $payload,
            ]);
    }

    public function processItemsBatch(array $payload): void
    {
        Log::channel(config('meta-catalog.logging.channel', 'stack'))
            ->info('Meta Catalog webhook: items_batch event received', [
                'payload' => $payload,
            ]);
    }

    protected function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256', '');

        if (empty($signature)) {
            return false;
        }

        $appSecret = config('meta-catalog.oauth.app_secret', '');
        $payload = $request->getContent();

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expected, $signature);
    }

    protected function dispatchEvent(array $payload): void
    {
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                match ($change['field'] ?? '') {
                    'product_feed' => $this->processProductFeed($change['value'] ?? []),
                    'items_batch'  => $this->processItemsBatch($change['value'] ?? []),
                    default => Log::channel(config('meta-catalog.logging.channel', 'stack'))
                        ->info('Meta Catalog webhook: unknown field', [
                            'field' => $change['field'] ?? 'unknown',
                        ]),
                };
            }
        }
    }
}