<?php

namespace ScriptDevelop\MetaCatalogManager\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

interface WebhookProcessorInterface
{
    public function handle(Request $request): Response|JsonResponse;
    public function verifyWebhook(Request $request, string $verifyToken): Response;
    public function processProductFeed(array $payload): void;
    public function processItemsBatch(array $payload): void;
}