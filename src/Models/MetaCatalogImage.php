<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaCatalogImage extends Model
{
    use GeneratesUlid;

    protected $table = 'meta_catalog_images';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_catalog_item_id',
        'type',
        'position',
        'original_url',
        'local_path',
        'local_url',
        'mime_type',
        'file_size',
        'downloaded_at',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
        'position'      => 'integer',
        'file_size'     => 'integer',
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

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeDownloaded($query)
    {
        return $query->whereNotNull('downloaded_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('downloaded_at');
    }

    public function scopeMain($query)
    {
        return $query->where('type', 'product_main');
    }

    public function scopeAdditional($query)
    {
        return $query->where('type', 'product_additional')->orderBy('position');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isDownloaded(): bool
    {
        return $this->downloaded_at !== null;
    }
}
