<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use ScriptDevelop\MetaCatalogManager\Enums\BatchRequestStatus;
use ScriptDevelop\MetaCatalogManager\Enums\InventoryChangeSource;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaBatchRequest;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogItem;
use ScriptDevelop\MetaCatalogManager\Models\MetaInventoryLog;
use ScriptDevelop\MetaCatalogManager\Models\MetaProductFeedUpload;

class InventoryService
{
    public function __construct(
        protected AccountService $accountService,
        protected BatchService   $batchService
    ) {}

    // -------------------------------------------------------------------------
    // Real-time inventory updates via Batch API
    // -------------------------------------------------------------------------

    /**
     * Actualiza el inventario de uno o múltiples ítems en tiempo real via Batch API.
     * Registra el historial de cada cambio automáticamente.
     *
     * @param MetaCatalog $catalog
     * @param array $updates  Array de ['retailer_id' => 'SKU123', 'quantity' => 50]
     * @param string|null $notes  Nota opcional para todos los logs de este batch
     * @return MetaBatchRequest
     *
     * @example
     * MetaCatalog::inventory()->updateBatch($catalog, [
     *     ['retailer_id' => 'SKU-001', 'quantity' => 100],
     *     ['retailer_id' => 'SKU-002', 'quantity' => 0],
     * ]);
     */
    public function updateBatch(
        MetaCatalog $catalog,
        array $updates,
        ?string $notes = null
    ): MetaBatchRequest {
        // Construir requests para la Batch API
        $requests = array_map(fn($update) => [
            'method' => 'UPDATE',
            'data'   => [
                'id'                          => $update['retailer_id'],
                'quantity_to_sell_on_facebook' => $update['quantity'],
            ],
        ], $updates);

        // Enviar a Meta
        $batchRequest = $this->batchService->sendBatch($catalog, $requests);

        // Registrar historial para cada ítem que tengamos localmente
        $itemModelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        foreach ($updates as $update) {
            $item = $itemModelClass::where('meta_catalog_id', $catalog->id)
                ->where('retailer_id', $update['retailer_id'])
                ->first();

            if ($item) {
                $this->log(
                    item:          $item,
                    newQuantity:   $update['quantity'],
                    source:        InventoryChangeSource::BATCH_API,
                    batchRequest:  $batchRequest,
                    notes:         $notes
                );
            }
        }

        return $batchRequest;
    }

    /**
     * Actualiza el inventario de un único ítem.
     * Shorthand para updateBatch() con un solo producto.
     */
    public function updateSingle(
        MetaCatalogItem $item,
        int $newQuantity,
        ?string $notes = null
    ): MetaBatchRequest {
        return $this->updateBatch(
            $item->catalog,
            [['retailer_id' => $item->retailer_id, 'quantity' => $newQuantity]],
            $notes
        );
    }

    // -------------------------------------------------------------------------
    // Manual logging (para cambios vía feed o externos)
    // -------------------------------------------------------------------------

    /**
     * Crea un log de inventario manualmente.
     * Actualiza también el campo quantity_to_sell_on_facebook en el modelo local.
     *
     * @param MetaCatalogItem          $item
     * @param int|null                 $newQuantity     null si no se conoce el nuevo valor
     * @param InventoryChangeSource    $source
     * @param MetaBatchRequest|null    $batchRequest    link al batch que originó el cambio
     * @param MetaProductFeedUpload|null $feedUpload    link al feed upload que originó el cambio
     * @param string|null              $notes
     */
    public function log(
        MetaCatalogItem       $item,
        ?int                  $newQuantity,
        InventoryChangeSource $source        = InventoryChangeSource::MANUAL,
        ?MetaBatchRequest     $batchRequest  = null,
        ?MetaProductFeedUpload $feedUpload   = null,
        ?string               $notes         = null
    ): MetaInventoryLog {
        $previousQuantity = $item->quantity_to_sell_on_facebook;
        $delta            = ($newQuantity !== null && $previousQuantity !== null)
            ? $newQuantity - $previousQuantity
            : null;

        $logModelClass = config('meta-catalog.models.meta_inventory_log', MetaInventoryLog::class);

        $log = $logModelClass::create([
            'meta_catalog_item_id'        => $item->id,
            'meta_catalog_id'             => $item->meta_catalog_id,
            'previous_quantity'           => $previousQuantity,
            'new_quantity'                => $newQuantity,
            'delta'                       => $delta,
            'source'                      => $source,
            'meta_batch_request_id'       => $batchRequest?->id,
            'meta_product_feed_upload_id' => $feedUpload?->id,
            'notes'                       => $notes,
        ]);

        // Actualizar el campo local si se conoce el nuevo valor
        if ($newQuantity !== null) {
            $item->update(['quantity_to_sell_on_facebook' => $newQuantity]);
        }

        return $log;
    }

    // -------------------------------------------------------------------------
    // History queries
    // -------------------------------------------------------------------------

    /**
     * Retorna el historial completo de inventario de un ítem.
     */
    public function getHistory(MetaCatalogItem $item, int $limit = 50): Collection
    {
        $logModelClass = config('meta-catalog.models.meta_inventory_log', MetaInventoryLog::class);

        return $logModelClass::where('meta_catalog_item_id', $item->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Retorna el último log de inventario de un ítem.
     */
    public function getLastLog(MetaCatalogItem $item): ?MetaInventoryLog
    {
        $logModelClass = config('meta-catalog.models.meta_inventory_log', MetaInventoryLog::class);

        return $logModelClass::where('meta_catalog_item_id', $item->id)
            ->latest()
            ->first();
    }

    /**
     * Retorna ítems de un catálogo con stock por debajo del umbral indicado.
     * Útil para alertas de reposición.
     */
    public function getLowStock(MetaCatalog $catalog, int $threshold = 10): Collection
    {
        $itemModelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        return $itemModelClass::where('meta_catalog_id', $catalog->id)
            ->where('quantity_to_sell_on_facebook', '<=', $threshold)
            ->where('quantity_to_sell_on_facebook', '>', 0)
            ->orderBy('quantity_to_sell_on_facebook')
            ->get();
    }

    /**
     * Retorna ítems sin stock (quantity = 0 o null).
     */
    public function getOutOfStock(MetaCatalog $catalog): Collection
    {
        $itemModelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        return $itemModelClass::where('meta_catalog_id', $catalog->id)
            ->where(function ($q) {
                $q->whereNull('quantity_to_sell_on_facebook')
                  ->orWhere('quantity_to_sell_on_facebook', 0);
            })
            ->get();
    }

    /**
     * Retorna el historial de cambios de inventario de todo un catálogo.
     * Útil para dashboards de actividad.
     */
    public function getCatalogHistory(MetaCatalog $catalog, int $limit = 100): Collection
    {
        $logModelClass = config('meta-catalog.models.meta_inventory_log', MetaInventoryLog::class);

        return $logModelClass::where('meta_catalog_id', $catalog->id)
            ->latest()
            ->limit($limit)
            ->with('item:id,retailer_id,title')
            ->get();
    }
}
