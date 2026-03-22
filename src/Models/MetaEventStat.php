<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaEventStat extends Model
{
    use GeneratesUlid;

    protected $table = 'meta_event_stats';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_event_source_id',
        'date_start',
        'date_stop',
        'event',
        'device_type',
        'total_matched_content_ids',
        'total_content_ids_matched_other_catalogs',
        'total_unmatched_content_ids',
        'unique_matched_content_ids',
        'unique_content_ids_matched_other_catalogs',
        'unique_unmatched_content_ids',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_stop'  => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function eventSource(): BelongsTo
    {
        return $this->belongsTo(
            config('meta-catalog.models.meta_event_source', MetaEventSource::class),
            'meta_event_source_id'
        );
    }
}
