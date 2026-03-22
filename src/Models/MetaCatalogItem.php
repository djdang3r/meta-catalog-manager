<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\MetaCatalogManager\Enums\CatalogItemType;
use ScriptDevelop\MetaCatalogManager\Enums\ItemAvailability;
use ScriptDevelop\MetaCatalogManager\Enums\ItemCondition;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaCatalogItem extends Model
{
    use GeneratesUlid, SoftDeletes;

    protected $table = 'meta_catalog_items';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_catalog_id',
        'meta_product_feed_id',
        'meta_product_item_id',
        'retailer_id',
        'item_type',
        'title',
        'description',
        'rich_text_description',
        'brand',
        'category',
        'product_type',
        'item_group_id',
        'price',
        'sale_price',
        'sale_price_effective_date',
        'availability',
        'condition',
        'quantity_to_sell_on_facebook',
        'image_url',
        'additional_image_urls',
        'link',
        'mobile_link',
        'color',
        'size',
        'gender',
        'age_group',
        'material',
        'pattern',
        'additional_variant_attribute',
        'custom_label_0',
        'custom_label_1',
        'custom_label_2',
        'custom_label_3',
        'custom_label_4',
        'internal_label',
        'shipping',
        'shipping_weight',
        'visibility',
        'review_status',
        'errors',
        'fb_product_category',
        'google_product_category',
        'gtin',
        'mpn',
        'app_links',
    ];

    protected $casts = [
        'additional_image_urls'       => 'array',
        'additional_variant_attribute' => 'array',
        'internal_label'              => 'array',
        'shipping'                    => 'array',
        'errors'                      => 'array',
        'availability'                => ItemAvailability::class,
        'condition'                   => ItemCondition::class,
        'item_type'                   => CatalogItemType::class,
        'app_links'                   => 'array',
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

    public function feed(): BelongsTo
    {
        return $this->belongsTo(
            config('meta-catalog.models.meta_product_feed', MetaProductFeed::class),
            'meta_product_feed_id'
        );
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(
            config('meta-catalog.models.meta_inventory_log', MetaInventoryLog::class),
            'meta_catalog_item_id'
        )->latest();
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeInStock($query)
    {
        return $query->where('availability', ItemAvailability::IN_STOCK->value);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('availability', ItemAvailability::OUT_OF_STOCK->value);
    }

    public function scopeByItemGroup($query, string $groupId)
    {
        return $query->where('item_group_id', $groupId);
    }

    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('errors')->where('errors', '!=', '[]');
    }

    public function scopeWithGtin($query)
    {
        return $query->whereNotNull('gtin');
    }

    public function scopeByFbCategory($query, string $category)
    {
        return $query->where('fb_product_category', 'like', '%' . $category . '%');
    }

    public function scopeByGoogleCategory($query, string $category)
    {
        return $query->where('google_product_category', 'like', '%' . $category . '%');
    }

    public function scopeWithRichDescription($query)
    {
        return $query->whereNotNull('rich_text_description');
    }

    public function scopeWithInternalLabel($query, string $label)
    {
        return $query->whereJsonContains('internal_label', $label);
    }
}
