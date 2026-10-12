<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\MetaCatalogManager\Enums\OfferApplicationType;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\ApiClient;
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
     * Trae todos los feeds y filtra localmente por feed_type === 'OFFER'.
     *
     * @return array Respuesta cruda de la API
     */
    public function fetchFromApi(MetaCatalog $catalog): array
    {
        $client = $this->accountService->getApiClient($catalog->account);

        $response = $client->request(
            'GET',
            Endpoints::GET_FEEDS,
            Endpoints::catalog($catalog->meta_catalog_id)
        );

        $response['data'] = array_values(array_filter(
            $response['data'] ?? [],
            fn($feed) => ($feed['feed_type'] ?? null) === 'OFFER'
        ));

        return $response;
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

        // Paso 2: Subir los datos reales de la oferta al feed.
        // Meta no expone un endpoint "create offer": las ofertas se ingieren
        // como un feed OFFER (CSV/TSV). Si viene upload_url se usa directo;
        // si no, se construye el CSV con los campos validados.
        if ($feedId) {
            if (!empty($data['upload_url'])) {
                $client->request(
                    'POST',
                    Endpoints::UPLOAD_FEED,
                    Endpoints::feed($feedId),
                    ['url' => $data['upload_url']]
                );
            } else {
                $this->uploadOfferFeedFile($client, $feedId, $data);
            }
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
     * Nota: Meta no permite editar una oferta ya creada. Para cambiarla hay que
     * reemplazar el feed OFFER que la respalda. Esta operación actualiza solo el
     * registro local.
     */
    public function updateOffer(MetaCatalogOffer $offer, array $data): MetaCatalogOffer
    {
        $offer->update($data);

        return $offer->fresh();
    }

    /**
     * Elimina una oferta (soft delete).
     *
     * Meta no permite borrar una oferta suelta; se intenta borrar el feed OFFER
     * que la respalda (best-effort: nunca lanza si falla).
     */
    public function deleteOffer(MetaCatalogOffer $offer): bool
    {
        $feedId = $offer->meta_offer_id;

        if ($feedId) {
            try {
                $catalog = $offer->catalog;

                if ($catalog) {
                    $client = $this->accountService->getApiClient($catalog->account);
                    $client->request('DELETE', Endpoints::DELETE_FEED, Endpoints::feed($feedId));
                }
            } catch (\Throwable $e) {
                Log::channel(config('meta-catalog.logging.channel', 'stack'))
                    ->warning('OfferService::deleteOffer — no se pudo borrar el feed en Meta', [
                        'meta_offer_id' => $feedId,
                        'error'         => $e->getMessage(),
                    ]);
            }
        }

        return (bool) $offer->delete();
    }

    /**
     * Sube un CSV con una fila de oferta al feed OFFER vía multipart.
     * Meta ingiere las ofertas desde el archivo del feed, no desde un POST.
     */
    private function uploadOfferFeedFile(ApiClient $client, string $feedId, array $data): void
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $this->buildOfferFeedCsv($data));
        rewind($handle);

        try {
            $client->request(
                'POST',
                Endpoints::UPLOAD_FEED,
                Endpoints::feed($feedId),
                ['multipart' => [[
                    'name'     => 'file',
                    'contents' => $handle,
                    'filename' => 'offer_feed.csv',
                    'headers'  => ['Content-Type' => 'text/csv'],
                ]]]
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * Construye el CSV de una oferta con las columnas del Offer Feed de Meta.
     */
    private function buildOfferFeedCsv(array $data): string
    {
        $columns = [
            'offer_id', 'title', 'description', 'application_type', 'value_type',
            'fixed_amount_off', 'percent_off', 'target_type', 'target_granularity',
            'target_selection', 'target_filter', 'target_product_retailer_ids',
            'target_product_group_retailer_ids', 'target_product_set_retailer_ids',
            'target_shipping_option_types', 'prerequisite_filter',
            'prerequisite_product_retailer_ids', 'prerequisite_product_group_retailer_ids',
            'prerequisite_product_set_retailer_ids', 'min_quantity', 'min_subtotal',
            'target_quantity', 'redemption_limit_per_order', 'coupon_codes',
            'public_coupon_code', 'redeem_limit_per_user', 'start_date_time',
            'end_date_time', 'exclude_sale_priced_products', 'offer_terms',
        ];

        $row = [];
        foreach ($columns as $column) {
            $value = $data[$column] ?? null;

            if (is_array($value)) {
                $value = implode(',', $value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('c');
            }

            $row[] = $value;
        }

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $columns);
        fputcsv($stream, $row);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
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
