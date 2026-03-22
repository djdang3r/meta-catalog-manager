<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\MetaCatalogManager\Enums\CatalogVertical;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaCatalog extends Model
{
    use GeneratesUlid, SoftDeletes;

    protected $table = 'meta_catalogs';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_business_account_id',
        'meta_catalog_id',
        'name',
        'vertical',
        'country',
        'currency',
        'timezone_id',
        'product_count',
        'is_catalog_segment',
        'status',
    ];

    protected $casts = [
        'vertical'          => CatalogVertical::class,
        'is_catalog_segment' => 'boolean',
        'product_count'     => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class),
            'meta_business_account_id'
        );
    }

    public function feeds(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_product_feed', MetaProductFeed::class),
            'meta_catalog_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class),
            'meta_catalog_id'
        );
    }

    public function productSets(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_product_set', MetaProductSet::class),
            'meta_catalog_id'
        );
    }

    public function batchRequests(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_batch_request', MetaBatchRequest::class),
            'meta_catalog_id'
        );
    }

    public function diagnostics(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_catalog_diagnostic', MetaCatalogDiagnostic::class),
            'meta_catalog_id'
        );
    }

    public function eventSources(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_event_source', MetaEventSource::class),
            'meta_catalog_id'
        );
    }
}
