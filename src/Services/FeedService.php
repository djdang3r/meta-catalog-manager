<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use ScriptDevelop\MetaCatalogManager\Enums\FeedIngestionSourceType;
use ScriptDevelop\MetaCatalogManager\Enums\FeedOverrideType;
use ScriptDevelop\MetaCatalogManager\Enums\GenericFeedType;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaProductFeed;
use ScriptDevelop\MetaCatalogManager\Models\MetaProductFeedUpload;

class FeedService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Obtiene los feeds de un catálogo desde la Graph API.
     *
     * @return array Respuesta cruda de la API
     */
    public function fetchFromApi(MetaCatalog $catalog): array
    {
        $client = $this->accountService->getApiClient($catalog->account);

        return $client->request(
            'GET',
            Endpoints::GET_FEEDS,
            Endpoints::catalog($catalog->meta_catalog_id)
        );
    }

    /**
     * Sincroniza feeds desde la API hacia la base de datos local.
     *
     * @return Collection<MetaProductFeed>
     */
    public function syncFromApi(MetaCatalog $catalog): Collection
    {
        $response   = $this->fetchFromApi($catalog);
        $data       = $response['data'] ?? [];
        $modelClass = config('meta-catalog.models.meta_product_feed', MetaProductFeed::class);
        $synced     = collect();

        foreach ($data as $apiFeed) {
            $feed = $modelClass::updateOrCreate(
                ['meta_feed_id' => $apiFeed['id']],
                [
                    'meta_catalog_id'          => $catalog->id,
                    'name'                     => $apiFeed['name'] ?? null,
                    'ingestion_source_type'    => $apiFeed['ingestion_source_type'] ?? FeedIngestionSourceType::PRIMARY_FEED->value,
                    'next_replace_upload_at'   => $apiFeed['next_scheduled_upload_at'] ?? null,
                    'last_replace_upload_at'   => $apiFeed['latest_upload']['created_at'] ?? null,
                    'file_name'                => $apiFeed['file_name'] ?? null,
                    'format'                   => $apiFeed['format'] ?? null,
                    'encoding'                 => $apiFeed['encoding'] ?? 'UTF-8',
                    'delimiter'                => $apiFeed['delimiter'] ?? null,
                ]
            );

            $synced->push($feed);
        }

        return $synced;
    }

    /**
     * Crea un feed en la Graph API y lo guarda en la DB.
     *
     * Si $data incluye 'feed_type', se envía en el payload para crear feeds
     * especializados (OFFER, PRODUCT_RATINGS_AND_REVIEWS, etc.).
     */
    public function create(MetaCatalog $catalog, array $data): MetaProductFeed
    {
        $client = $this->accountService->getApiClient($catalog->account);

        // Incluir feed_type en el payload si fue especificado
        $payload = $data;
        if (isset($payload['feed_type']) && $payload['feed_type'] instanceof GenericFeedType) {
            $payload['feed_type'] = $payload['feed_type']->value;
        }

        $response = $client->request(
            'POST',
            Endpoints::CREATE_FEED,
            Endpoints::catalog($catalog->meta_catalog_id),
            $payload
        );

        $modelClass = config('meta-catalog.models.meta_product_feed', MetaProductFeed::class);

        return $modelClass::create([
            'meta_catalog_id'              => $catalog->id,
            'meta_feed_id'                 => $response['id'],
            'name'                         => $data['name'] ?? null,
            'ingestion_source_type'        => FeedIngestionSourceType::PRIMARY_FEED,
            'replace_schedule_url'         => $data['schedule']['url'] ?? null,
            'replace_schedule_interval'    => $data['schedule']['interval'] ?? null,
            'replace_schedule_hour'        => $data['schedule']['hour'] ?? null,
            'replace_schedule_minute'      => $data['schedule']['minute'] ?? null,
            'replace_schedule_day_of_week' => $data['schedule']['day_of_week'] ?? null,
            'format'                       => $data['format'] ?? null,
            'encoding'                     => $data['encoding'] ?? 'UTF-8',
            'delimiter'                    => $data['delimiter'] ?? null,
            'quoted_fields_mode'           => $data['quoted_fields_mode'] ?? 'AUTO',
            'feed_username'                => $data['credentials']['user'] ?? null,
            'feed_password'                => $data['credentials']['password'] ?? null,
        ]);
    }

    /**
     * Crea un Supplementary Feed en la API y lo guarda en DB.
     * Requiere: name, primary_feed_ids (array de IDs Meta), y opcionalmente schedule.
     *
     * Si $data incluye 'feed_type', se envía en el payload.
     */
    public function createSupplementaryFeed(MetaCatalog $catalog, array $data): MetaProductFeed
    {
        $client = $this->accountService->getApiClient($catalog->account);

        $payload = array_merge($data, [
            'ingestion_source_type' => 'SUPPLEMENTARY_FEED',
        ]);

        // Normalizar feed_type enum a string si fue pasado como enum
        if (isset($payload['feed_type']) && $payload['feed_type'] instanceof GenericFeedType) {
            $payload['feed_type'] = $payload['feed_type']->value;
        }

        $response = $client->request(
            'POST',
            Endpoints::CREATE_FEED,
            Endpoints::catalog($catalog->meta_catalog_id),
            $payload
        );

        $modelClass = config('meta-catalog.models.meta_product_feed', MetaProductFeed::class);

        return $modelClass::create([
            'meta_catalog_id'          => $catalog->id,
            'meta_feed_id'             => $response['id'],
            'name'                     => $data['name'] ?? null,
            'ingestion_source_type'    => FeedIngestionSourceType::SUPPLEMENTARY_FEED,
            'primary_feed_ids'         => $data['primary_feed_ids'] ?? [],
            'update_only'              => $data['update_only'] ?? true,
            'replace_schedule_url'     => $data['schedule']['url'] ?? null,
            'replace_schedule_interval' => $data['schedule']['interval'] ?? null,
            'replace_schedule_hour'    => $data['schedule']['hour'] ?? null,
            'replace_schedule_minute'  => $data['schedule']['minute'] ?? null,
            'replace_schedule_day_of_week' => $data['schedule']['day_of_week'] ?? null,
            'format'                   => $data['format'] ?? null,
            'encoding'                 => $data['encoding'] ?? 'UTF-8',
            'delimiter'                => $data['delimiter'] ?? null,
        ]);
    }

    /**
     * Actualiza un feed en la Graph API y sincroniza en DB.
     */
    public function update(MetaProductFeed $feed, array $data): MetaProductFeed
    {
        $client = $this->accountService->getApiClient($feed->catalog->account);

        $client->request(
            'POST',
            Endpoints::UPDATE_FEED,
            Endpoints::feed($feed->meta_feed_id),
            $data
        );

        $feed->update($data);

        return $feed->fresh();
    }

    /**
     * Elimina un feed de la Graph API y la DB.
     */
    public function delete(MetaProductFeed $feed): bool
    {
        $client = $this->accountService->getApiClient($feed->catalog->account);

        $client->request(
            'DELETE',
            Endpoints::DELETE_FEED,
            Endpoints::feed($feed->meta_feed_id)
        );

        return (bool) $feed->delete();
    }

    /**
     * Obtiene los uploads de un feed desde la Graph API.
     *
     * @return array Respuesta cruda de la API
     */
    public function getUploads(MetaProductFeed $feed): array
    {
        $client = $this->accountService->getApiClient($feed->catalog->account);

        return $client->request(
            'GET',
            Endpoints::GET_FEED_UPLOADS,
            Endpoints::feed($feed->meta_feed_id)
        );
    }

    /**
     * Sincroniza los uploads de un feed desde la API hacia la DB.
     *
     * @return Collection<MetaProductFeedUpload>
     */
    public function syncUploads(MetaProductFeed $feed): Collection
    {
        $response   = $this->getUploads($feed);
        $data       = $response['data'] ?? [];
        $modelClass = config('meta-catalog.models.meta_product_feed_upload', MetaProductFeedUpload::class);
        $synced     = collect();

        foreach ($data as $apiUpload) {
            $upload = $modelClass::updateOrCreate(
                ['meta_upload_session_id' => $apiUpload['id']],
                [
                    'meta_product_feed_id' => $feed->id,
                    'status'               => $apiUpload['status'] ?? 'complete',
                    'num_detected_items'   => $apiUpload['num_detected_items'] ?? 0,
                    'num_persisted_items'  => $apiUpload['num_persisted_items'] ?? 0,
                    'num_deleted_items'    => $apiUpload['num_deleted_items'] ?? 0,
                    'error_count'          => $apiUpload['error_count'] ?? 0,
                    'warning_count'        => $apiUpload['warning_count'] ?? 0,
                    'error_report_url'     => $apiUpload['error_report_uri'] ?? null,
                    'started_at'           => $apiUpload['start_time'] ?? null,
                    'completed_at'         => $apiUpload['end_time'] ?? null,
                ]
            );

            $synced->push($upload);
        }

        return $synced;
    }

    /**
     * Dispara un upload en la Graph API y registra en la DB.
     */
    public function triggerUpload(MetaProductFeed $feed, array $data = []): MetaProductFeedUpload
    {
        $client = $this->accountService->getApiClient($feed->catalog->account);

        $response = $client->request(
            'POST',
            Endpoints::UPLOAD_FEED,
            Endpoints::feed($feed->meta_feed_id),
            $data
        );

        $modelClass = config('meta-catalog.models.meta_product_feed_upload', MetaProductFeedUpload::class);

        return $modelClass::create([
            'meta_product_feed_id'   => $feed->id,
            'meta_upload_session_id' => $response['id'] ?? null,
            'status'                 => 'in_progress',
            'upload_url'             => $response['upload_url'] ?? null,
            'started_at'             => now(),
        ]);
    }

    /**
     * Upload one-time desde URL.
     * POST /{feed_id}/uploads con campo 'url'
     */
    public function uploadFromUrl(MetaProductFeed $feed, string $url, bool $updateOnly = false): MetaProductFeedUpload
    {
        $client = $this->accountService->getApiClient($feed->catalog->account);

        $payload = ['url' => $url];
        if ($updateOnly) {
            $payload['update_only'] = true;
        }

        $response = $client->request(
            'POST',
            Endpoints::UPLOAD_FEED,
            Endpoints::feed($feed->meta_feed_id),
            $payload
        );

        $modelClass = config('meta-catalog.models.meta_product_feed_upload', MetaProductFeedUpload::class);

        return $modelClass::create([
            'meta_product_feed_id'   => $feed->id,
            'meta_upload_session_id' => $response['id'] ?? null,
            'status'                 => 'in_progress',
            'update_only'            => $updateOnly,
            'upload_type'            => 'url',
            'upload_url'             => $url,
            'started_at'             => now(),
        ]);
    }

    /**
     * Upload one-time desde archivo local (multipart).
     * POST /{feed_id}/uploads con campo 'file' (multipart)
     */
    public function uploadFromFile(MetaProductFeed $feed, string $filePath, string $mimeType = 'text/csv', bool $updateOnly = false): MetaProductFeedUpload
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

        if ($updateOnly) {
            $multipart[] = [
                'name'     => 'update_only',
                'contents' => 'true',
            ];
        }

        $response = $client->request(
            'POST',
            Endpoints::UPLOAD_FEED,
            Endpoints::feed($feed->meta_feed_id),
            ['multipart' => $multipart]
        );

        $modelClass = config('meta-catalog.models.meta_product_feed_upload', MetaProductFeedUpload::class);

        return $modelClass::create([
            'meta_product_feed_id'   => $feed->id,
            'meta_upload_session_id' => $response['id'] ?? null,
            'status'                 => 'in_progress',
            'update_only'            => $updateOnly,
            'upload_type'            => 'file',
            'started_at'             => now(),
        ]);
    }

    /**
     * Obtiene errores de una sesión de upload.
     * GET /{upload_session_id}/errors
     *
     * @return array con data[] de errores (id, summary, description, severity, samples)
     */
    public function getUploadErrors(MetaProductFeedUpload $upload): array
    {
        $client = $this->accountService->getApiClient($upload->feed->catalog->account);

        return $client->request(
            'GET',
            Endpoints::UPLOAD_SESSION_ERRORS,
            Endpoints::uploadSession($upload->meta_upload_session_id)
        );
    }

    /**
     * Solicita la generación del reporte completo de errores.
     * POST /{upload_session_id}/error_report
     *
     * @return array ['success' => true]
     */
    public function requestErrorReport(MetaProductFeedUpload $upload): array
    {
        $client = $this->accountService->getApiClient($upload->feed->catalog->account);

        $response = $client->request(
            'POST',
            Endpoints::UPLOAD_SESSION_ERROR_REPORT,
            Endpoints::uploadSession($upload->meta_upload_session_id)
        );

        $upload->update(['error_report_status' => 'PENDING']);

        return $response;
    }

    /**
     * Obtiene el status y URL del reporte de errores generado.
     * GET /{upload_session_id}?fields=error_report
     * Actualiza upload->error_report_status en DB.
     *
     * @return array ['error_report' => ['report_status' => ..., 'file_handle' => ...], 'id' => ...]
     */
    public function getErrorReport(MetaProductFeedUpload $upload): array
    {
        $client = $this->accountService->getApiClient($upload->feed->catalog->account);

        $response = $client->request(
            'GET',
            Endpoints::GET_UPLOAD_SESSION,
            Endpoints::uploadSession($upload->meta_upload_session_id),
            null,
            ['fields' => 'error_report']
        );

        $reportStatus = $response['error_report']['report_status'] ?? null;
        if ($reportStatus !== null) {
            $upload->update(['error_report_status' => $reportStatus]);
        }

        return $response;
    }

    /**
     * Obtiene los data sources (primary feeds) del catálogo.
     * GET /{catalog_id}/data_sources?ingestion_source_type=PRIMARY
     */
    public function getDataSources(MetaCatalog $catalog, string $ingestionSourceType = 'PRIMARY'): array
    {
        $client = $this->accountService->getApiClient($catalog->account);

        return $client->request(
            'GET',
            Endpoints::GET_DATA_SOURCES,
            Endpoints::catalog($catalog->meta_catalog_id),
            null,
            ['ingestion_source_type' => $ingestionSourceType]
        );
    }

    /**
     * Crea un feed de calificaciones y opiniones.
     *
     * POST /{catalog_id}/product_feeds con feed_type=PRODUCT_RATINGS_AND_REVIEWS
     */
    public function createRatingsAndReviewsFeed(MetaCatalog $catalog, string $name): MetaProductFeed
    {
        return $this->create($catalog, [
            'name'      => $name,
            'feed_type' => GenericFeedType::PRODUCT_RATINGS_AND_REVIEWS->value,
        ]);
    }

    /**
     * Sube un archivo de calificaciones y opiniones al feed.
     *
     * Wrapper tipado para ratings sobre uploadFromUrl / uploadFromFile.
     *
     * @param MetaProductFeed $feed       Feed de tipo PRODUCT_RATINGS_AND_REVIEWS
     * @param string          $urlOrPath  URL pública o path local del archivo CSV
     * @param bool            $isFile     true = archivo local, false = URL
     * @return MetaProductFeedUpload
     */
    public function uploadRatingsAndReviews(MetaProductFeed $feed, string $urlOrPath, bool $isFile = false): MetaProductFeedUpload
    {
        if ($isFile) {
            return $this->uploadFromFile($feed, $urlOrPath, 'text/csv');
        }

        return $this->uploadFromUrl($feed, $urlOrPath);
    }

    // =========================================================================
    // Localized Catalog Feeds (override_type)
    // =========================================================================

    /**
     * Crea un feed localizado (idioma, país o idioma+país) vinculado al catálogo.
     *
     * POST /{catalog_id}/product_feeds con override_type
     *
     * El parámetro $schedule acepta:
     * [
     *   'interval' => 'DAILY',
     *   'url'      => 'https://...',
     *   'hour'     => 22,
     * ]
     *
     * @param MetaCatalog     $catalog
     * @param string          $name        Nombre descriptivo del feed
     * @param FeedOverrideType $overrideType 'language' | 'country' | 'language_and_country'
     * @param array           $schedule    Configuración de schedule (opcional)
     * @return MetaProductFeed
     */
    public function createLocalizedFeed(
        MetaCatalog $catalog,
        string $name,
        FeedOverrideType $overrideType,
        array $schedule = []
    ): MetaProductFeed {
        $client = $this->accountService->getApiClient($catalog->account);

        $payload = [
            'name'          => $name,
            'override_type' => $overrideType->value,
        ];

        if (!empty($schedule)) {
            $payload['schedule'] = $schedule;
        }

        $response = $client->request(
            'POST',
            Endpoints::CREATE_FEED,
            Endpoints::catalog($catalog->meta_catalog_id),
            $payload
        );

        $modelClass = config('meta-catalog.models.meta_product_feed', MetaProductFeed::class);

        return $modelClass::create([
            'meta_catalog_id'              => $catalog->id,
            'meta_feed_id'                 => $response['id'],
            'name'                         => $name,
            'ingestion_source_type'        => FeedIngestionSourceType::PRIMARY_FEED,
            'override_type'                => $overrideType->value,
            'replace_schedule_url'         => $schedule['url'] ?? null,
            'replace_schedule_interval'    => $schedule['interval'] ?? null,
            'replace_schedule_hour'        => $schedule['hour'] ?? null,
        ]);
    }

    /**
     * Shortcut: crea un feed de idiomas (lista de traducciones).
     *
     * @param MetaCatalog $catalog
     * @param string      $name     Nombre del feed (ej: "Language Feed ES/FR")
     * @param array       $schedule Schedule opcional
     * @return MetaProductFeed
     */
    public function createLanguageFeed(MetaCatalog $catalog, string $name, array $schedule = []): MetaProductFeed
    {
        return $this->createLocalizedFeed($catalog, $name, FeedOverrideType::LANGUAGE, $schedule);
    }

    /**
     * Shortcut: crea un feed de países (lista de precios/disponibilidad por país).
     *
     * @param MetaCatalog $catalog
     * @param string      $name     Nombre del feed (ej: "Country Feed US/UK")
     * @param array       $schedule Schedule opcional
     * @return MetaProductFeed
     */
    public function createCountryFeed(MetaCatalog $catalog, string $name, array $schedule = []): MetaProductFeed
    {
        return $this->createLocalizedFeed($catalog, $name, FeedOverrideType::COUNTRY, $schedule);
    }

    /**
     * Shortcut: crea un feed de idioma+país (localización avanzada con URL por locale).
     *
     * @param MetaCatalog $catalog
     * @param string      $name     Nombre del feed (ej: "Language+Country Feed fr_XX|CA")
     * @param array       $schedule Schedule opcional
     * @return MetaProductFeed
     */
    public function createLanguageAndCountryFeed(MetaCatalog $catalog, string $name, array $schedule = []): MetaProductFeed
    {
        return $this->createLocalizedFeed($catalog, $name, FeedOverrideType::LANGUAGE_AND_COUNTRY, $schedule);
    }

    /**
     * Retorna todos los feeds localizados de un catálogo.
     *
     * @return Collection<MetaProductFeed>
     */
    public function getLocalizedFeeds(MetaCatalog $catalog): Collection
    {
        $modelClass = config('meta-catalog.models.meta_product_feed', MetaProductFeed::class);

        return $modelClass::where('meta_catalog_id', $catalog->id)
            ->localized()
            ->get();
    }
}
