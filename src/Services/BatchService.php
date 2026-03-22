<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use ScriptDevelop\MetaCatalogManager\Enums\BatchRequestStatus;
use ScriptDevelop\MetaCatalogManager\Enums\CatalogItemType;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaBatchRequest;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;

class BatchService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Envía un lote de requests a la Batch API de Meta y guarda el registro en la DB.
     *
     * @param array  $requests  Array de transacciones ['method' => 'CREATE|UPDATE|DELETE', 'data' => [...]]
     * @param string $itemType  Tipo de ítem (PRODUCT_ITEM, VEHICLE, etc.)
     */
    public function sendBatch(
        MetaCatalog $catalog,
        array $requests,
        string $itemType = 'PRODUCT_ITEM'
    ): MetaBatchRequest {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $payload = [
            'item_type' => $itemType,
            'requests'  => $requests,
        ];

        $response = $client->request(
            'POST',
            Endpoints::ITEMS_BATCH,
            Endpoints::catalog($catalog->meta_catalog_id),
            $payload
        );

        $modelClass = config('meta-catalog.models.meta_batch_request', MetaBatchRequest::class);

        // Determinar operación según los métodos en los requests
        $methods = array_unique(array_column($requests, 'method'));
        $operation = count($methods) === 1
            ? strtolower($methods[0])
            : 'mixed';

        return $modelClass::create([
            'meta_catalog_id' => $catalog->id,
            'handle'          => $response['handles'][0] ?? null,
            'item_type'       => CatalogItemType::from($itemType),
            'operation'       => $operation,
            'status'          => BatchRequestStatus::PENDING,
            'items_count'     => count($requests),
        ]);
    }

    /**
     * Envía un lote de creaciones.
     */
    public function createItems(
        MetaCatalog $catalog,
        array $items,
        string $itemType = 'PRODUCT_ITEM'
    ): MetaBatchRequest {
        $requests = array_map(
            fn($data) => ['method' => 'CREATE', 'data' => $data],
            $items
        );

        return $this->sendBatch($catalog, $requests, $itemType);
    }

    /**
     * Envía un lote de actualizaciones.
     */
    public function updateItems(
        MetaCatalog $catalog,
        array $items,
        string $itemType = 'PRODUCT_ITEM'
    ): MetaBatchRequest {
        $requests = array_map(
            fn($data) => ['method' => 'UPDATE', 'data' => $data],
            $items
        );

        return $this->sendBatch($catalog, $requests, $itemType);
    }

    /**
     * Envía un lote de eliminaciones por retailer_id.
     */
    public function deleteItems(
        MetaCatalog $catalog,
        array $retailerIds,
        string $itemType = 'PRODUCT_ITEM'
    ): MetaBatchRequest {
        $requests = array_map(
            fn($id) => ['method' => 'DELETE', 'data' => ['id' => $id]],
            $retailerIds
        );

        return $this->sendBatch($catalog, $requests, $itemType);
    }

    /**
     * Consulta el estado de un batch en la API y actualiza el registro en DB.
     */
    public function checkStatus(MetaBatchRequest $batchRequest): MetaBatchRequest
    {
        $catalog = $batchRequest->catalog;
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $response = $client->request(
            'GET',
            Endpoints::CHECK_BATCH_STATUS,
            Endpoints::catalog($catalog->meta_catalog_id),
            null,
            ['handle' => $batchRequest->handle]
        );

        // La API retorna status por handle
        $apiStatus = $response['status'] ?? null;

        $update = [];

        if ($apiStatus !== null) {
            $statusMap = [
                'PENDING'    => BatchRequestStatus::PENDING,
                'PROCESSING' => BatchRequestStatus::PROCESSING,
                'FINISHED'   => BatchRequestStatus::COMPLETE,
                'FAILED'     => BatchRequestStatus::FAILED,
            ];

            $update['status'] = $statusMap[strtoupper($apiStatus)] ?? BatchRequestStatus::PROCESSING;

            if (in_array($update['status'], [BatchRequestStatus::COMPLETE, BatchRequestStatus::FAILED])) {
                $update['completed_at']   = now();
                $update['success_count']  = $response['num_persisted'] ?? 0;
                $update['error_count']    = $response['num_invalid_requests'] ?? 0;
                $update['errors']         = $response['errors'] ?? null;
            }
        }

        if (!empty($update)) {
            $batchRequest->update($update);
        }

        return $batchRequest->fresh();
    }

    /**
     * Envía un lote localizado (localized_items_batch) y guarda en DB.
     */
    public function sendLocalizedBatch(
        MetaCatalog $catalog,
        array $requests,
        string $itemType = 'PRODUCT_ITEM'
    ): MetaBatchRequest {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $payload = [
            'item_type' => $itemType,
            'requests'  => $requests,
        ];

        $response = $client->request(
            'POST',
            Endpoints::LOCALIZED_ITEMS_BATCH,
            Endpoints::catalog($catalog->meta_catalog_id),
            $payload
        );

        $modelClass = config('meta-catalog.models.meta_batch_request', MetaBatchRequest::class);

        return $modelClass::create([
            'meta_catalog_id' => $catalog->id,
            'handle'          => $response['handles'][0] ?? null,
            'item_type'       => CatalogItemType::from($itemType),
            'operation'       => 'mixed',
            'status'          => BatchRequestStatus::PENDING,
            'items_count'     => count($requests),
        ]);
    }
}
