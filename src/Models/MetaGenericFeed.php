<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\MetaCatalogManager\Enums\GenericFeedType;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaGenericFeed extends Model
{
    use GeneratesUlid, SoftDeletes;

    protected $table = 'meta_generic_feeds';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_catalog_id',
        'meta_feed_id',
        'commerce_partner_integration_id',
        'feed_type',
        'name',
        'last_upload_at',
        'last_upload_status',
    ];

    protected $casts = [
        'feed_type'      => GenericFeedType::class,
        'last_upload_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(
            config('meta-catalog.models.meta_catalog', MetaCatalog::class),
            'meta_catalog_id'
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Filtra feeds por tipo.
     */
    public function scopeByType(Builder $query, GenericFeedType $type): Builder
    {
        return $query->where('feed_type', $type->value);
    }
}
