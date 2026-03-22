<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use ScriptDevelop\MetaCatalogManager\Enums\GenericFeedType;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaGenericFeed;

class GenericFeedService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Crea un feed genérico via /{catalog_id}/product_feeds con feed_type.
     *
     * Aplica para: OFFER, PRODUCT_RATINGS_AND_REVIEWS.
     */
    public function createFeed(MetaCatalog $catalog, GenericFeedType $feedType, string $name): MetaGenericFeed
    {
        $client = $this->accountService->getApiClient($catalog->account);

        $response = $client->request(
            'POST',
            Endpoints::CREATE_FEED,
            Endpoints::catalog($catalog->meta_catalog_id),
            [
                'name'      => $name,
                'feed_type' => $feedType->value,
            ]
        );

        $modelClass = config('meta-catalog.models.meta_generic_feed', MetaGenericFeed::class);

        return $modelClass::create([
            'meta_catalog_id' => $catalog->id,
            'meta_feed_id'    => $response['id'],
            'feed_type'       => $feedType->value,
            'name'            => $name,
        ]);
    }

    /**
     * Sube un archivo de datos al feed via /{feed_id}/uploads desde URL.
     *
     * @return array Respuesta cruda de la API (id de la sesión de upload)
     */
    public function uploadFromUrl(MetaGenericFeed $feed, string $url): array
    {
        $client = $this->accountService->getApiClient($feed->catalog->account);

        $response = $client->request(
            'POST',
            Endpoints::UPLOAD_FEED,
            Endpoints::feed($feed->meta_feed_id),
            ['url' => $url]
        );

        $feed->update([
            'last_upload_at'     => now(),
            'last_upload_status' => 'in_progress',
        ]);

        return $response;
    }

    /**
     * Sube un archivo de datos al feed via /{feed_id}/uploads desde archivo local (multipart).
     *
     * @return array Respuesta cruda de la API (id de la sesión de upload)
     */
    public function uploadFromFile(MetaGenericFeed $feed, string $filePath, string $mimeType = 'text/csv'): array
    {
        $client = $this->accountService->getApiClient($feed->catalog->account);

        $multipart = [
            [
                'name'     => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
                'headers'  => ['Content-Type' => $mimeType],
            ],
        ];

        $response = $client->request(
            'POST',
            Endpoints::UPLOAD_FEED,
            Endpoints::feed($feed->meta_feed_id),
            ['multipart' => $multipart]
        );

        $feed->update([
            'last_upload_at'     => now(),
            'last_upload_status' => 'in_progress',
        ]);

        return $response;
    }

    /**
     * Usa la Generic Feed Files API (endpoint diferente).
     *
     * POST /{commercePartnerIntegrationId}/file_update
     *
     * Para: PROMOTIONS, SHIPPING_PROFILES, NAVIGATION_MENU, PRODUCT_RATINGS_AND_REVIEWS.
     * Requiere commerce_partner_integration_id.
     *
     * @return array Respuesta cruda de la API
     */
    public function uploadViaGenericApi(
        MetaBusinessAccount $account,
        string $commercePartnerIntegrationId,
        GenericFeedType $feedType,
        string $filePath,
        string $mimeType = 'text/csv'
    ): array {
        $client = $this->accountService->getApiClient($account);

        $multipart = [
            [
                'name'     => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => basename($filePath),
                'headers'  => ['Content-Type' => $mimeType],
            ],
            [
                'name'     => 'feed_type',
                'contents' => $feedType->value,
            ],
        ];

        return $client->request(
            'POST',
            Endpoints::GENERIC_FILE_UPDATE,
            Endpoints::commercePartner($commercePartnerIntegrationId),
            ['multipart' => $multipart]
        );
    }

    /**
     * Obtiene las sesiones de upload de un feed.
     *
     * GET /{feed_id}/uploads
     *
     * @return array Respuesta cruda de la API
     */
    public function getUploadSessions(MetaGenericFeed $feed): array
    {
        $client = $this->accountService->getApiClient($feed->catalog->account);

        return $client->request(
            'GET',
            Endpoints::GET_FEED_UPLOADS,
            Endpoints::feed($feed->meta_feed_id)
        );
    }

    /**
     * Obtiene errores de una sesión de upload.
     *
     * GET /{upload_session_id}/errors
     *
     * @param string      $uploadSessionId  ID de la sesión de upload
     * @param MetaBusinessAccount $account  Cuenta para autenticación
     * @param string|null $errorPriority    Filtro opcional: 'FATAL', 'ERROR', 'WARNING'
     * @return array Respuesta cruda de la API
     */
    public function getUploadErrors(string $uploadSessionId, MetaBusinessAccount $account, ?string $errorPriority = null): array
    {
        $client = $this->accountService->getApiClient($account);

        $params = [];
        if ($errorPriority !== null) {
            $params['error_priority'] = $errorPriority;
        }

        return $client->request(
            'GET',
            Endpoints::UPLOAD_SESSION_ERRORS,
            Endpoints::uploadSession($uploadSessionId),
            null,
            $params
        );
    }

    // -------------------------------------------------------------------------
    // Shortcuts por tipo
    // -------------------------------------------------------------------------

    /**
     * Sube perfiles de envío via la Generic Feed Files API.
     *
     * @return array Respuesta cruda de la API
     */
    public function uploadShippingProfiles(
        MetaBusinessAccount $account,
        string $commercePartnerIntegrationId,
        string $filePath
    ): array {
        return $this->uploadViaGenericApi(
            $account,
            $commercePartnerIntegrationId,
            GenericFeedType::SHIPPING_PROFILES,
            $filePath,
            'text/csv'
        );
    }

    /**
     * Sube calificaciones y opiniones via el feed estándar de un catálogo.
     *
     * @return array Respuesta cruda de la API
     */
    public function uploadRatingsAndReviews(MetaCatalog $catalog, string $filePath): array
    {
        // Primero busca o crea el feed de ratings para este catálogo
        $modelClass = config('meta-catalog.models.meta_generic_feed', MetaGenericFeed::class);

        $feed = $modelClass::where('meta_catalog_id', $catalog->id)
            ->where('feed_type', GenericFeedType::PRODUCT_RATINGS_AND_REVIEWS->value)
            ->first();

        if ($feed === null) {
            $feed = $this->createFeed($catalog, GenericFeedType::PRODUCT_RATINGS_AND_REVIEWS, 'Ratings and Reviews');
        }

        return $this->uploadFromFile($feed, $filePath, 'text/csv');
    }

    /**
     * Sube promociones via la Generic Feed Files API.
     *
     * @return array Respuesta cruda de la API
     */
    public function uploadPromotions(
        MetaBusinessAccount $account,
        string $commercePartnerIntegrationId,
        string $filePath
    ): array {
        return $this->uploadViaGenericApi(
            $account,
            $commercePartnerIntegrationId,
            GenericFeedType::PROMOTIONS,
            $filePath,
            'text/csv'
        );
    }

    /**
     * Sube menú de navegación via la Generic Feed Files API.
     *
     * @return array Respuesta cruda de la API
     */
    public function uploadNavigationMenu(
        MetaBusinessAccount $account,
        string $commercePartnerIntegrationId,
        string $filePath
    ): array {
        return $this->uploadViaGenericApi(
            $account,
            $commercePartnerIntegrationId,
            GenericFeedType::NAVIGATION_MENU,
            $filePath,
            'application/json'
        );
    }
}
