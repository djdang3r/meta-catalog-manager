<?php

namespace ScriptDevelop\MetaCatalogManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use ScriptDevelop\MetaCatalogManager\Enums\OfferApplicationType;
use ScriptDevelop\MetaCatalogManager\Enums\OfferTargetGranularity;
use ScriptDevelop\MetaCatalogManager\Enums\OfferTargetSelection;
use ScriptDevelop\MetaCatalogManager\Enums\OfferTargetType;
use ScriptDevelop\MetaCatalogManager\Enums\OfferValueType;
use ScriptDevelop\MetaCatalogManager\Traits\GeneratesUlid;

class MetaCatalogOffer extends Model
{
    use GeneratesUlid, SoftDeletes;

    protected $table = 'meta_catalog_offers';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'meta_catalog_id',
        'meta_offer_id',
        'offer_id',
        'title',
        'description',
        'application_type',
        'value_type',
        'fixed_amount_off',
        'percent_off',
        'target_type',
        'target_granularity',
        'target_selection',
        'target_filter',
        'target_product_retailer_ids',
        'target_product_group_retailer_ids',
        'target_product_set_retailer_ids',
        'target_shipping_option_types',
        'prerequisite_filter',
        'prerequisite_product_retailer_ids',
        'prerequisite_product_group_retailer_ids',
        'prerequisite_product_set_retailer_ids',
        'min_quantity',
        'min_subtotal',
        'target_quantity',
        'redemption_limit_per_order',
        'coupon_codes',
        'public_coupon_code',
        'redeem_limit_per_user',
        'start_date_time',
        'end_date_time',
        'exclude_sale_priced_products',
        'offer_terms',
        'status',
    ];

    protected $casts = [
        'application_type'                        => OfferApplicationType::class,
        'value_type'                              => OfferValueType::class,
        'target_type'                             => OfferTargetType::class,
        'target_granularity'                      => OfferTargetGranularity::class,
        'target_selection'                        => OfferTargetSelection::class,
        'target_filter'                           => 'array',
        'target_product_retailer_ids'             => 'array',
        'target_product_group_retailer_ids'       => 'array',
        'target_product_set_retailer_ids'         => 'array',
        'target_shipping_option_types'            => 'array',
        'prerequisite_filter'                     => 'array',
        'prerequisite_product_retailer_ids'       => 'array',
        'prerequisite_product_group_retailer_ids' => 'array',
        'prerequisite_product_set_retailer_ids'   => 'array',
        'coupon_codes'                            => 'array',
        'exclude_sale_priced_products'            => 'boolean',
        'start_date_time'                         => 'datetime',
        'end_date_time'                           => 'datetime',
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
     * Ofertas activas: status=active AND start_date_time <= now AND (end_date_time IS NULL OR end_date_time >= now).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('start_date_time', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('end_date_time')
                  ->orWhere('end_date_time', '>=', now());
            });
    }

    /**
     * Ofertas en estado inactivo.
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Ofertas expiradas.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired')
            ->orWhere(function (Builder $q) {
                $q->whereNotNull('end_date_time')
                  ->where('end_date_time', '<', now());
            });
    }

    /**
     * Solo ofertas de tipo SALE.
     */
    public function scopeSaleType(Builder $query): Builder
    {
        return $query->where('application_type', OfferApplicationType::SALE->value);
    }

    /**
     * Solo ofertas automáticas en el checkout.
     */
    public function scopeAutomaticType(Builder $query): Builder
    {
        return $query->where('application_type', OfferApplicationType::AUTOMATIC_AT_CHECKOUT->value);
    }

    /**
     * Solo ofertas aplicadas por el comprador (cupones).
     */
    public function scopeBuyerApplied(Builder $query): Builder
    {
        return $query->where('application_type', OfferApplicationType::BUYER_APPLIED->value);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Verifica si la oferta está activa (status + fechas de vigencia).
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        if ($this->start_date_time > $now) {
            return false;
        }

        if ($this->end_date_time !== null && $this->end_date_time < $now) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si es una oferta de tipo SALE.
     */
    public function isSale(): bool
    {
        return $this->application_type === OfferApplicationType::SALE;
    }

    /**
     * Verifica si la oferta tiene códigos de cupón.
     */
    public function hasCouponCode(): bool
    {
        return !empty($this->coupon_codes) || $this->public_coupon_code !== null;
    }

    /**
     * Verifica si es una oferta de envío (shipping discount).
     */
    public function isShippingOffer(): bool
    {
        return $this->target_type === OfferTargetType::SHIPPING;
    }
}
