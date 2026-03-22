<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ScriptDevelop\MetaCatalogManager\Enums\InventoryChangeSource;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaInventoryLog extends Model
{
    use GeneratesUlid;

    protected $table      = 'meta_inventory_logs';
    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    // Sin softDeletes — el historial de inventario es inmutable

    protected $fillable = [
        'meta_catalog_item_id',
        'meta_catalog_id',
        'previous_quantity',
        'new_quantity',
        'delta',
        'source',
        'meta_batch_request_id',
        'meta_product_feed_upload_id',
        'notes',
    ];

    protected $casts = [
        'source'            => InventoryChangeSource::class,
        'previous_quantity' => 'integer',
        'new_quantity'      => 'integer',
        'delta'             => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function item(): BelongsTo
    {
        return $this->belongsTo(
            config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class),
            'meta_catalog_item_id'
        );
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(
            config('meta-catalog.models.meta_catalog', MetaCatalog::class),
            'meta_catalog_id'
        );
    }

    public function batchRequest(): BelongsTo
    {
        return $this->belongsTo(
            config('meta-catalog.models.meta_batch_request', MetaBatchRequest::class),
            'meta_batch_request_id'
        );
    }

    public function feedUpload(): BelongsTo
    {
        return $this->belongsTo(
            config('meta-catalog.models.meta_product_feed_upload', MetaProductFeedUpload::class),
            'meta_product_feed_upload_id'
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeFromBatch($query)
    {
        return $query->where('source', InventoryChangeSource::BATCH_API->value);
    }

    public function scopeFromFeed($query)
    {
        return $query->where('source', InventoryChangeSource::FEED_UPLOAD->value);
    }

    public function scopeStockDecreases($query)
    {
        return $query->where('delta', '<', 0);
    }

    public function scopeStockIncreases($query)
    {
        return $query->where('delta', '>', 0);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function wasRestock(): bool
    {
        return $this->delta > 0;
    }

    public function wasDecrease(): bool
    {
        return $this->delta < 0;
    }
}
