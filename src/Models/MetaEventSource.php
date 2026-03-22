<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaEventSource extends Model
{
    use GeneratesUlid;

    protected $table = 'meta_event_sources';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_catalog_id',
        'source_id',
        'source_type',
        'name',
        'status',
        'last_check_at',
        'last_check_results',
    ];

    protected $casts = [
        'last_check_at'      => 'datetime',
        'last_check_results' => 'array',
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

    public function stats(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_event_stat', MetaEventStat::class),
            'meta_event_source_id'
        );
    }
}
