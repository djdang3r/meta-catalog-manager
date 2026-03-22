<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaProductFeedUpload extends Model
{
    use GeneratesUlid;

    protected $table = 'meta_product_feed_uploads';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_product_feed_id',
        'meta_upload_session_id',
        'status',
        'num_detected_items',
        'num_persisted_items',
        'num_deleted_items',
        'error_count',
        'warning_count',
        'error_report_url',
        'upload_url',
        'update_only',
        'error_report_status',
        'upload_type',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'update_only'  => 'boolean',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function feed(): BelongsTo
    {
        return $this->belongsTo(
            config('meta-catalog.models.meta_product_feed', MetaProductFeed::class),
            'meta_product_feed_id'
        );
    }
}
