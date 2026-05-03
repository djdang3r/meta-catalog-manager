<?php

namespace ScriptDevelop\MetaCatalogManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ScriptDevelop\MetaCatalogManager\Contracts\WebhookProcessorInterface;
use ScriptDevelop\MetaCatalogManager\Services\WebhookProcessors\DefaultWebhookProcessor;
use Illuminate\Support\Facades\Log;

class MetaCatalogWebhookController extends Controller
{
    protected WebhookProcessorInterface $processor;

    public function __construct()
    {
        try {
            $this->processor = app(WebhookProcessorInterface::class);
        } catch (\Exception $e) {
            $this->processor = new DefaultWebhookProcessor();

            Log::channel(config('meta-catalog.logging.channel', 'stack'))
                ->warning('WebhookProcessorInterface could not be resolved, using default implementation', [
                    'error' => $e->getMessage(),
                ]);
        }
    }

    public function handle(Request $request)
    {
        try {
            return $this->processor->handle($request);
        } catch (\Exception $e) {
            Log::channel(config('meta-catalog.logging.channel', 'stack'))
                ->error('Error processing webhook', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}