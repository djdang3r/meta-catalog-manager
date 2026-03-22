<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ScriptDevelop\MetaCatalogManager\Enums\BatchRequestStatus;
use ScriptDevelop\MetaCatalogManager\Enums\CatalogItemType;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaBatchRequest extends Model
{
    use GeneratesUlid;

    protected $table = 'meta_batch_requests';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_catalog_id',
        'handle',
        'item_type',
        'operation',
        'status',
        'items_count',
        'success_count',
        'error_count',
        'errors',
        'completed_at',
    ];

    protected $casts = [
        'errors'       => 'array',
        'status'       => BatchRequestStatus::class,
        'completed_at' => 'datetime',
        'item_type'    => CatalogItemType::class,
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

    public function scopePending($query)
    {
        return $query->where('status', BatchRequestStatus::PENDING->value);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', BatchRequestStatus::PROCESSING->value);
    }

    public function scopeComplete($query)
    {
        return $query->where('status', BatchRequestStatus::COMPLETE->value);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', BatchRequestStatus::FAILED->value);
    }
}
