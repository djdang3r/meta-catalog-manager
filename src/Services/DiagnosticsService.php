<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use ScriptDevelop\MetaCatalogManager\Enums\EventSourceIssueType;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogDiagnostic;

class DiagnosticsService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Obtiene los diagnósticos de un catálogo desde la Graph API.
     *
     * @return array Respuesta cruda de la API
     */
    public function fetchFromApi(MetaCatalog $catalog): array
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        return $client->request(
            'GET',
            Endpoints::GET_CATALOG_DIAGNOSTICS,
            Endpoints::catalog($catalog->meta_catalog_id)
        );
    }

    /**
     * Sincroniza diagnósticos desde la API hacia la base de datos.
     *
     * @return Collection<MetaCatalogDiagnostic>
     */
    public function syncFromApi(MetaCatalog $catalog): Collection
    {
        $response   = $this->fetchFromApi($catalog);
        $data       = $response['data'] ?? [];
        $modelClass = config('meta-catalog.models.meta_catalog_diagnostic', MetaCatalogDiagnostic::class);
        $synced     = collect();

        // Limpiar diagnósticos anteriores y reemplazar con los nuevos
        $modelClass::where('meta_catalog_id', $catalog->id)->delete();

        foreach ($data as $apiDiagnostic) {
            $diagnostic = $modelClass::create([
                'meta_catalog_id'      => $catalog->id,
                'error_type'           => $apiDiagnostic['type'] ?? 'UNKNOWN',
                'severity'             => strtolower($apiDiagnostic['severity'] ?? 'error'),
                'count'                => $apiDiagnostic['count'] ?? 0,
                'description'          => $apiDiagnostic['description'] ?? null,
                'affected_items_count' => $apiDiagnostic['affected_entity_count'] ?? 0,
                'samples'              => $apiDiagnostic['samples'] ?? null,
                'fetched_at'           => now(),
            ]);

            $synced->push($diagnostic);
        }

        return $synced;
    }

    /**
     * Obtiene todos los errores de un catálogo desde la Graph API.
     *
     * @return array Respuesta cruda de la API
     */
    public function getAllErrors(MetaCatalog $catalog): array
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        return $client->request(
            'GET',
            Endpoints::GET_CATALOG_ALL_ERRORS,
            Endpoints::catalog($catalog->meta_catalog_id)
        );
    }

    /**
     * Verifica si el catálogo tiene errores en la DB local.
     */
    public function hasErrors(MetaCatalog $catalog): bool
    {
        $modelClass = config('meta-catalog.models.meta_catalog_diagnostic', MetaCatalogDiagnostic::class);

        return $modelClass::where('meta_catalog_id', $catalog->id)
            ->where('severity', 'error')
            ->where('count', '>', 0)
            ->exists();
    }

    /**
     * Verifica si el catálogo tiene warnings en la DB local.
     */
    public function hasWarnings(MetaCatalog $catalog): bool
    {
        $modelClass = config('meta-catalog.models.meta_catalog_diagnostic', MetaCatalogDiagnostic::class);

        return $modelClass::where('meta_catalog_id', $catalog->id)
            ->where('severity', 'warning')
            ->where('count', '>', 0)
            ->exists();
    }

    /**
     * Obtiene diagnósticos del tipo EVENT_SOURCE_ISSUES desde la Graph API.
     *
     * GET /{catalog_id}/diagnostics?types=["EVENT_SOURCE_ISSUES"]
     *
     * Retorna array con estructura:
     * [
     *   'type'                    => 'EVENT_SOURCE_ISSUES',
     *   'severity'                => 'MUST_FIX|WARNING',
     *   'title'                   => '...',
     *   'subtitle'                => '...',
     *   'error_code'              => int,
     *   'number_of_affected_items' => int,
     *   'diagnostics'             => [...issues detallados...]
     * ]
     *
     * @return array Datos crudos de la API (campo 'data' del response)
     */
    public function getEventSourceIssues(MetaCatalog $catalog): array
    {
        $client = $this->accountService->getApiClient($catalog->account);

        $response = $client->request(
            'GET',
            Endpoints::GET_CATALOG_DIAGNOSTICS,
            Endpoints::catalog($catalog->meta_catalog_id),
            null,
            ['types' => '["EVENT_SOURCE_ISSUES"]']
        );

        return $response['data'] ?? [];
    }

    /**
     * Verifica si hay issues críticos (MUST_FIX) de event source.
     */
    public function hasCriticalEventSourceIssues(MetaCatalog $catalog): bool
    {
        $issues = $this->getEventSourceIssues($catalog);

        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'MUST_FIX') {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna solo los issues de un tipo específico de EventSourceIssueType.
     *
     * @param MetaCatalog $catalog
     * @param string $type Valor de EventSourceIssueType o string equivalente
     * @return array
     */
    public function getEventSourceIssuesByType(MetaCatalog $catalog, string $type): array
    {
        $issues = $this->getEventSourceIssues($catalog);

        return array_values(array_filter($issues, function (array $issue) use ($type) {
            return ($issue['type'] ?? '') === $type;
        }));
    }

    /**
     * Guarda/actualiza el resultado de EVENT_SOURCE_ISSUES en meta_catalog_diagnostics.
     *
     * Actualiza fetched_at, count, severity, samples para cada tipo de issue.
     *
     * @return Collection<MetaCatalogDiagnostic>
     */
    public function syncEventSourceIssues(MetaCatalog $catalog): Collection
    {
        $issues     = $this->getEventSourceIssues($catalog);
        $modelClass = config('meta-catalog.models.meta_catalog_diagnostic', MetaCatalogDiagnostic::class);
        $synced     = collect();

        foreach ($issues as $issue) {
            $errorType = $issue['type'] ?? 'EVENT_SOURCE_ISSUE';
            $severity  = strtolower($issue['severity'] ?? 'error');

            // Normalizar: MUST_FIX → error, WARNING → warning
            if ($severity === 'must_fix') {
                $severity = 'error';
            }

            $diagnostic = $modelClass::updateOrCreate(
                [
                    'meta_catalog_id' => $catalog->id,
                    'error_type'      => $errorType,
                ],
                [
                    'severity'             => $severity,
                    'count'                => $issue['number_of_affected_items'] ?? 0,
                    'description'          => ($issue['title'] ?? '') . (isset($issue['subtitle']) ? ': ' . $issue['subtitle'] : ''),
                    'affected_items_count' => $issue['number_of_affected_items'] ?? 0,
                    'samples'              => $issue['diagnostics'] ?? null,
                    'fetched_at'           => now(),
                ]
            );

            $synced->push($diagnostic);
        }

        return $synced;
    }
}
