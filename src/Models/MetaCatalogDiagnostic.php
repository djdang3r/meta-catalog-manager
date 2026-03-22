<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaCatalogDiagnostic extends Model
{
    use GeneratesUlid;

    protected $table = 'meta_catalog_diagnostics';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_catalog_id',
        'error_type',
        'severity',
        'count',
        'description',
        'affected_items_count',
        'samples',
        'fetched_at',
    ];

    protected $casts = [
        'samples'    => 'array',
        'fetched_at' => 'datetime',
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
}
