<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use ScriptDevelop\MetaCatalogManager\Enums\OfferApplicationType;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogOffer;

class OfferService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Obtiene los feeds de tipo OFFER de un catálogo desde la Graph API.
     *
     * GET /{catalog_id}/product_feeds?filter[feed_type]=OFFER
     *
     * @return array Respuesta cruda de la API
     */
    public function fetchFromApi(MetaCatalog $catalog): array
    {
        $client = $this->accountService->getApiClient($catalog->account);

        return $client->request(
            'GET',
            Endpoints::GET_FEEDS,
            Endpoints::catalog($catalog->meta_catalog_id),
            null,
            ['filter' => ['feed_type' => 'OFFER']]
        );
    }

    /**
     * Sincroniza las ofertas desde el feed de la API hacia la base de datos.
     *
     * @return Collection<MetaCatalogOffer>
     */
    public function syncFromApi(MetaCatalog $catalog): Collection
    {
        $response   = $this->fetchFromApi($catalog);
        $data       = $response['data'] ?? [];
        $modelClass = config('meta-catalog.models.meta_catalog_offer', MetaCatalogOffer::class);
        $synced     = collect();

        foreach ($data as $apiOffer) {
            $offer = $modelClass::updateOrCreate(
                ['meta_offer_id' => $apiOffer['id']],
                [
                    'meta_catalog_id'  => $catalog->id,
                    'offer_id'         => $apiOffer['name'] ?? $apiOffer['id'],
                    'title'            => $apiOffer['name'] ?? null,
                    'status'           => 'active',
                    'start_date_time'  => $apiOffer['created_time'] ?? now(),
                    'application_type' => OfferApplicationType::SALE->value,
                    'value_type'       => 'FIXED_AMOUNT',
                ]
            );

            $synced->push($offer);
        }

        return $synced;
    }

    /**
     * Crea una oferta en Meta y la guarda en la base de datos.
     *
     * Proceso:
     * 1. POST /{catalog_id}/product_feeds (feed_type=OFFER) → crea el feed de ofertas
     * 2. POST /{feed_id}/uploads → sube los datos de la oferta
     * 3. Guarda el offer en DB
     */
    public function createOffer(MetaCatalog $catalog, array $data): MetaCatalogOffer
    {
        $client = $this->accountService->getApiClient($catalog->account);

        // Paso 1: Crear el feed de tipo OFFER
        $feedResponse = $client->request(
            'POST',
            Endpoints::CREATE_FEED,
            Endpoints::catalog($catalog->meta_catalog_id),
            [
                'name'      => $data['title'] ?? ($data['offer_id'] ?? 'Offer Feed'),
                'feed_type' => 'OFFER',
            ]
        );

        $feedId = $feedResponse['id'] ?? null;

        // Paso 2: Subir los datos de la oferta si se proporciona una URL o payload
        if ($feedId && isset($data['upload_url'])) {
            $client->request(
                'POST',
                Endpoints::UPLOAD_FEED,
                Endpoints::feed($feedId),
                ['url' => $data['upload_url']]
            );
        }

        // Paso 3: Guardar en DB
        $modelClass = config('meta-catalog.models.meta_catalog_offer', MetaCatalogOffer::class);

        return $modelClass::create([
            'meta_catalog_id'                        => $catalog->id,
            'meta_offer_id'                          => $feedId,
            'offer_id'                               => $data['offer_id'],
            'title'                                  => $data['title'] ?? null,
            'description'                            => $data['description'] ?? null,
            'application_type'                       => $data['application_type'] ?? OfferApplicationType::SALE->value,
            'value_type'                             => $data['value_type'],
            'fixed_amount_off'                       => $data['fixed_amount_off'] ?? null,
            'percent_off'                            => $data['percent_off'] ?? null,
            'target_type'                            => $data['target_type'] ?? 'LINE_ITEM',
            'target_granularity'                     => $data['target_granularity'] ?? 'ITEM_LEVEL',
            'target_selection'                       => $data['target_selection'] ?? 'ALL_CATALOG_PRODUCTS',
            'target_filter'                          => $data['target_filter'] ?? null,
            'target_product_retailer_ids'            => $data['target_product_retailer_ids'] ?? null,
            'target_product_group_retailer_ids'      => $data['target_product_group_retailer_ids'] ?? null,
            'target_product_set_retailer_ids'        => $data['target_product_set_retailer_ids'] ?? null,
            'target_shipping_option_types'           => $data['target_shipping_option_types'] ?? null,
            'prerequisite_filter'                    => $data['prerequisite_filter'] ?? null,
            'prerequisite_product_retailer_ids'      => $data['prerequisite_product_retailer_ids'] ?? null,
            'prerequisite_product_group_retailer_ids' => $data['prerequisite_product_group_retailer_ids'] ?? null,
            'prerequisite_product_set_retailer_ids'  => $data['prerequisite_product_set_retailer_ids'] ?? null,
            'min_quantity'                           => $data['min_quantity'] ?? 0,
            'min_subtotal'                           => $data['min_subtotal'] ?? null,
            'target_quantity'                        => $data['target_quantity'] ?? 0,
            'redemption_limit_per_order'             => $data['redemption_limit_per_order'] ?? 0,
            'coupon_codes'                           => $data['coupon_codes'] ?? null,
            'public_coupon_code'                     => $data['public_coupon_code'] ?? null,
            'redeem_limit_per_user'                  => $data['redeem_limit_per_user'] ?? 0,
            'start_date_time'                        => $data['start_date_time'],
            'end_date_time'                          => $data['end_date_time'] ?? null,
            'exclude_sale_priced_products'           => $data['exclude_sale_priced_products'] ?? false,
            'offer_terms'                            => $data['offer_terms'] ?? null,
            'status'                                 => $data['status'] ?? 'active',
        ]);
    }

    /**
     * Actualiza una oferta en la base de datos.
     *
     * Nota: La API de Meta no permite editar ofertas una vez creadas.
     * Esta operación actualiza solo el registro local.
     */
    public function updateOffer(MetaCatalogOffer $offer, array $data): MetaCatalogOffer
    {
        $offer->update($data);

        return $offer->fresh();
    }

    /**
     * Elimina una oferta (soft delete).
     */
    public function deleteOffer(MetaCatalogOffer $offer): bool
    {
        return (bool) $offer->delete();
    }

    /**
     * Busca una oferta por su ULID interno.
     */
    public function find(string $id): ?MetaCatalogOffer
    {
        $modelClass = config('meta-catalog.models.meta_catalog_offer', MetaCatalogOffer::class);

        return $modelClass::find($id);
    }

    /**
     * Retorna todas las ofertas de un catálogo.
     *
     * @return Collection<MetaCatalogOffer>
     */
    public function forCatalog(MetaCatalog $catalog): Collection
    {
        $modelClass = config('meta-catalog.models.meta_catalog_offer', MetaCatalogOffer::class);

        return $modelClass::where('meta_catalog_id', $catalog->id)->get();
    }

    /**
     * Retorna las ofertas de tipo SALE activas del catálogo.
     *
     * @return Collection<MetaCatalogOffer>
     */
    public function getActiveSales(MetaCatalog $catalog): Collection
    {
        $modelClass = config('meta-catalog.models.meta_catalog_offer', MetaCatalogOffer::class);

        return $modelClass::where('meta_catalog_id', $catalog->id)
            ->active()
            ->saleType()
            ->get();
    }

    /**
     * Retorna las ofertas de tipo BUYER_APPLIED (cupones) activas del catálogo.
     *
     * @return Collection<MetaCatalogOffer>
     */
    public function getActiveCoupons(MetaCatalog $catalog): Collection
    {
        $modelClass = config('meta-catalog.models.meta_catalog_offer', MetaCatalogOffer::class);

        return $modelClass::where('meta_catalog_id', $catalog->id)
            ->active()
            ->buyerApplied()
            ->get();
    }
}
