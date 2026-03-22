<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaEventSource;
use ScriptDevelop\MetaCatalogManager\Models\MetaEventStat;

class EventStatsService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Obtiene estadísticas de eventos de un catálogo desde la Graph API.
     *
     * @param array $params Parámetros adicionales (time_range, breakdowns, etc.)
     * @return array Respuesta cruda de la API
     */
    public function fetchFromApi(MetaCatalog $catalog, array $params = []): array
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        return $client->request(
            'GET',
            Endpoints::GET_EVENT_STATS,
            Endpoints::catalog($catalog->meta_catalog_id),
            null,
            $params
        );
    }

    /**
     * Obtiene estadísticas de eventos con un breakdown específico.
     *
     * @param string $breakdown Ej: 'device_type', 'event_name'
     * @return array Respuesta cruda de la API
     */
    public function fetchWithBreakdown(MetaCatalog $catalog, string $breakdown = 'device_type'): array
    {
        return $this->fetchFromApi($catalog, ['breakdowns' => $breakdown]);
    }

    /**
     * Sincroniza estadísticas de eventos desde la API hacia la DB.
     *
     * @return Collection<MetaEventStat>
     */
    public function syncFromApi(MetaCatalog $catalog): Collection
    {
        $response   = $this->fetchFromApi($catalog);
        $data       = $response['data'] ?? [];
        $synced     = collect();

        foreach ($catalog->eventSources as $eventSource) {
            foreach ($data as $stat) {
                $modelClass = config('meta-catalog.models.meta_event_stat', MetaEventStat::class);

                $eventStat = $modelClass::updateOrCreate(
                    [
                        'meta_event_source_id' => $eventSource->id,
                        'date_start'           => $stat['date_start'] ?? now()->toDateString(),
                        'event'                => $stat['event'] ?? 'unknown',
                        'device_type'          => $stat['device_type'] ?? null,
                    ],
                    [
                        'date_stop'                                   => $stat['date_stop'] ?? now()->toDateString(),
                        'total_matched_content_ids'                   => $stat['total_matched_content_ids'] ?? 0,
                        'total_content_ids_matched_other_catalogs'    => $stat['total_content_ids_matched_other_catalogs'] ?? 0,
                        'total_unmatched_content_ids'                 => $stat['total_unmatched_content_ids'] ?? 0,
                        'unique_matched_content_ids'                  => $stat['unique_matched_content_ids'] ?? 0,
                        'unique_content_ids_matched_other_catalogs'   => $stat['unique_content_ids_matched_other_catalogs'] ?? 0,
                        'unique_unmatched_content_ids'                => $stat['unique_unmatched_content_ids'] ?? 0,
                    ]
                );

                $synced->push($eventStat);
            }
        }

        return $synced;
    }

    /**
     * Ejecuta las verificaciones de Dynamic Ads (da_checks) para un Pixel.
     *
     * @return array Respuesta cruda de la API
     */
    public function checkPixel(MetaEventSource $eventSource): array
    {
        $account = $eventSource->catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $response = $client->request(
            'GET',
            Endpoints::GET_PIXEL_DA_CHECKS,
            ['pixel_id' => $eventSource->source_id]
        );

        // Actualizar resultados en la DB
        $eventSource->update([
            'last_check_at'      => now(),
            'last_check_results' => $response,
        ]);

        return $response;
    }

    /**
     * Ejecuta las verificaciones de Dynamic Ads (da_checks) para una App.
     *
     * @return array Respuesta cruda de la API
     */
    public function checkApp(MetaEventSource $eventSource): array
    {
        $account = $eventSource->catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $response = $client->request(
            'GET',
            Endpoints::GET_APP_DA_CHECKS,
            ['app_id' => $eventSource->source_id]
        );

        // Actualizar resultados en la DB
        $eventSource->update([
            'last_check_at'      => now(),
            'last_check_results' => $response,
        ]);

        return $response;
    }

    /**
     * Crea o actualiza un EventSource en la DB.
     */
    public function syncEventSource(
        MetaCatalog $catalog,
        string $sourceId,
        string $sourceType
    ): MetaEventSource {
        $modelClass = config('meta-catalog.models.meta_event_source', MetaEventSource::class);

        return $modelClass::updateOrCreate(
            [
                'meta_catalog_id' => $catalog->id,
                'source_id'       => $sourceId,
            ],
            [
                'source_type' => $sourceType,
                'status'      => 'active',
            ]
        );
    }
}
