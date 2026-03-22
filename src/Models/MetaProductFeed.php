<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\MetaCatalogManager\Enums\FeedIngestionSourceType;
use ScriptDevelop\MetaCatalogManager\Enums\FeedOverrideType;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaProductFeed extends Model
{
    use GeneratesUlid, SoftDeletes;

    protected $table = 'meta_product_feeds';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_catalog_id',
        'meta_feed_id',
        'name',

        // Feed type & supplementary / localized feed support
        'ingestion_source_type',
        'override_type',
        'primary_feed_ids',
        'update_only',

        // Replace schedule
        'replace_schedule_url',
        'replace_schedule_interval',
        'replace_schedule_hour',
        'replace_schedule_minute',
        'replace_schedule_day_of_week',
        'next_replace_upload_at',
        'last_replace_upload_at',

        // Update schedule
        'update_schedule_url',
        'update_schedule_interval',
        'update_schedule_hour',
        'update_schedule_minute',
        'update_schedule_day_of_week',
        'next_update_upload_at',
        'last_update_upload_at',

        // Auth credentials (almacenadas encriptadas)
        'feed_username',
        'feed_password',

        // Feed file info
        'file_name',
        'format',
        'encoding',
        'delimiter',
        'quoted_fields_mode',
    ];

    protected $casts = [
        'ingestion_source_type'  => FeedIngestionSourceType::class,
        'override_type'          => FeedOverrideType::class,
        'primary_feed_ids'       => 'array',
        'update_only'            => 'boolean',
        'next_replace_upload_at' => 'datetime',
        'last_replace_upload_at' => 'datetime',
        'next_update_upload_at'  => 'datetime',
        'last_update_upload_at'  => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Encrypted Mutators/Accessors
    // -------------------------------------------------------------------------

    public function setFeedUsernameAttribute(?string $value): void
    {
        $this->attributes['feed_username'] = $value !== null ? encrypt($value) : null;
    }

    public function getFeedUsernameAttribute(?string $value): ?string
    {
        return $value !== null ? decrypt($value) : null;
    }

    public function setFeedPasswordAttribute(?string $value): void
    {
        $this->attributes['feed_password'] = $value !== null ? encrypt($value) : null;
    }

    public function getFeedPasswordAttribute(?string $value): ?string
    {
        return $value !== null ? decrypt($value) : null;
    }

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

    public function uploads(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_product_feed_upload', MetaProductFeedUpload::class),
            'meta_product_feed_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class),
            'meta_product_feed_id'
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePrimary($query)
    {
        return $query->where('ingestion_source_type', FeedIngestionSourceType::PRIMARY_FEED->value);
    }

    public function scopeSupplementary($query)
    {
        return $query->where('ingestion_source_type', FeedIngestionSourceType::SUPPLEMENTARY_FEED->value);
    }

    public function scopeLocalized($query)
    {
        return $query->whereNotNull('override_type');
    }

    public function scopeLanguageOverride($query)
    {
        return $query->where('override_type', FeedOverrideType::LANGUAGE->value);
    }

    public function scopeCountryOverride($query)
    {
        return $query->where('override_type', FeedOverrideType::COUNTRY->value);
    }

    public function scopeLanguageAndCountryOverride($query)
    {
        return $query->where('override_type', FeedOverrideType::LANGUAGE_AND_COUNTRY->value);
    }
}
