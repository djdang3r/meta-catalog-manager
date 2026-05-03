<?php

use Illuminate\Support\Facades\Route;
use ScriptDevelop\MetaCatalogManager\Http\Controllers\MetaCatalogWebhookController;

Route::prefix('meta-catalog-webhook')->group(function () {
    Route::match(['get', 'post'], '/', [MetaCatalogWebhookController::class, 'handle'])
        ->name('meta-catalog.webhook.handle');
});