<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use ScriptDevelop\MetaCatalogManager\Enums\FeedOverrideType;
use ScriptDevelop\MetaCatalogManager\Enums\InventoryChangeSource;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogItem;
use ScriptDevelop\MetaCatalogManager\Models\MetaInventoryLog;

class ProductService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Obtiene productos de un catálogo desde la Graph API con paginación.
     *
     * @param int         $limit Cantidad máxima de resultados
     * @param string|null $after Cursor para la siguiente página
     * @return array       Respuesta cruda de la API (incluye data y paging)
     */
    public function getFromApi(MetaCatalog $catalog, int $limit = 50, ?string $after = null): array
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $query = ['limit' => $limit];
        if ($after !== null) {
            $query['after'] = $after;
        }

        return $client->request(
            'GET',
            Endpoints::GET_PRODUCTS,
            Endpoints::catalog($catalog->meta_catalog_id),
            null,
            $query
        );
    }

    /**
     * Crea un producto individual en la Graph API y lo guarda en la base de datos.
     */
    public function createSingle(MetaCatalog $catalog, array $data): MetaCatalogItem
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $response = $client->request(
            'POST',
            Endpoints::CREATE_PRODUCT,
            Endpoints::catalog($catalog->meta_catalog_id),
            $data
        );

        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        return $modelClass::create(array_merge(
            $this->mapApiDataToColumns($data),
            [
                'meta_catalog_id'      => $catalog->id,
                'meta_product_item_id' => $response['id'],
            ]
        ));
    }

    /**
     * Actualiza un producto individual en la Graph API y en la base de datos.
     */
    public function updateSingle(string $productItemId, MetaBusinessAccount $account, array $data): MetaCatalogItem
    {
        $client = $this->accountService->getApiClient($account);

        $client->request(
            'POST',
            Endpoints::UPDATE_PRODUCT,
            Endpoints::product($productItemId),
            $data
        );

        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);
        $item = $modelClass::where('meta_product_item_id', $productItemId)->firstOrFail();

        $item->update($this->mapApiDataToColumns($data));

        return $item->fresh();
    }

    /**
     * Elimina un producto individual de la Graph API y lo soft-elimina de la base de datos.
     */
    public function deleteSingle(string $productItemId, MetaBusinessAccount $account): bool
    {
        $client = $this->accountService->getApiClient($account);

        $client->request(
            'DELETE',
            Endpoints::DELETE_PRODUCT,
            Endpoints::product($productItemId)
        );

        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);
        $item = $modelClass::where('meta_product_item_id', $productItemId)->first();

        if ($item) {
            $item->delete();
        }

        return true;
    }

    /**
     * Obtiene los detalles de un producto individual desde la Graph API y actualiza la DB local.
     *
     * GET /{product_item_id}
     */
    public function getSingle(string $productItemId, MetaBusinessAccount $account): MetaCatalogItem
    {
        $client = $this->accountService->getApiClient($account);

        $response = $client->request(
            'GET',
            Endpoints::GET_PRODUCT,
            Endpoints::product($productItemId),
            null,
            ['fields' => 'id,retailer_id,name,description,url,price,sale_price,currency,availability,condition,image_url,additional_image_urls,brand,category,item_group_id,gtin,mpn']
        );

        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        $item = $modelClass::where('meta_product_item_id', $productItemId)->firstOrFail();
        $item->update($this->mapApiDataToColumns($response));

        return $item->fresh();
    }

    /**
     * Sincroniza productos desde la API hacia la base de datos local.
     * Maneja paginación automáticamente.
     *
     * @return int Cantidad de productos sincronizados
     */
    public function syncFromApi(MetaCatalog $catalog): int
    {
        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);
        $count  = 0;
        $after  = null;

        do {
            $response = $this->getFromApi($catalog, 200, $after);
            $items    = $response['data'] ?? [];

            foreach ($items as $item) {
                $localItem = $modelClass::updateOrCreate(
                    [
                        'meta_catalog_id' => $catalog->id,
                        'retailer_id'     => $item['retailer_id'] ?? $item['id'],
                    ],
                    [
                        // Core
                        'meta_product_item_id'          => $item['id'] ?? null,
                        'title'                         => $item['name'] ?? null,
                        'description'                   => $item['description'] ?? null,
                        'rich_text_description'         => $item['rich_text_description'] ?? null,
                        'brand'                         => $item['brand'] ?? null,

                        // Categories
                        'category'                      => $item['category'] ?? null,
                        'product_type'                  => $item['product_type'] ?? null,
                        'fb_product_category'           => $item['fb_product_category'] ?? null,
                        'google_product_category'       => $item['google_product_category'] ?? null,

                        // Pricing
                        'price'                         => $item['price'] ?? null,
                        'sale_price'                    => $item['sale_price'] ?? null,
                        'sale_price_effective_date'     => $item['sale_price_effective_date'] ?? null,

                        // Availability
                        'availability'                  => $item['availability'] ?? 'in stock',
                        'condition'                     => $item['condition'] ?? 'new',
                        'quantity_to_sell_on_facebook'  => $item['quantity_to_sell_on_facebook'] ?? null,

                        // Images
                        'image_url'                     => $item['image_url'] ?? null,
                        'additional_image_urls'         => isset($item['additional_image_link'])
                            ? (is_array($item['additional_image_link'])
                                ? $item['additional_image_link']
                                : explode(',', $item['additional_image_link']))
                            : null,

                        // Links
                        'link'                          => $item['url'] ?? $item['link'] ?? null,
                        'mobile_link'                   => $item['mobile_link'] ?? null,

                        // Variants
                        'item_group_id'                 => $item['item_group_id'] ?? null,
                        'color'                         => $item['color'] ?? null,
                        'size'                          => $item['size'] ?? null,
                        'gender'                        => $item['gender'] ?? null,
                        'age_group'                     => $item['age_group'] ?? null,
                        'material'                      => $item['material'] ?? null,
                        'pattern'                       => $item['pattern'] ?? null,

                        // Labels
                        'custom_label_0'                => $item['custom_label_0'] ?? null,
                        'custom_label_1'                => $item['custom_label_1'] ?? null,
                        'custom_label_2'                => $item['custom_label_2'] ?? null,
                        'custom_label_3'                => $item['custom_label_3'] ?? null,
                        'custom_label_4'                => $item['custom_label_4'] ?? null,
                        'internal_label'                => $item['internal_label'] ?? null,

                        // Identifiers
                        'gtin'                          => $item['gtin'] ?? null,
                        'mpn'                           => $item['mpn'] ?? null,

                        // Status
                        'visibility'                    => $item['visibility'] ?? 'published',
                        'review_status'                 => $item['review_status'] ?? null,
                    ]
                );

                // Registrar cambio de inventario si la cantidad cambió o es un producto nuevo
                $newQty = $localItem->quantity_to_sell_on_facebook;
                if ($newQty !== null) {
                    $prevQty     = $localItem->wasRecentlyCreated ? null : $localItem->getOriginal('quantity_to_sell_on_facebook');
                    $qtyChanged  = $localItem->wasRecentlyCreated || $localItem->wasChanged('quantity_to_sell_on_facebook');

                    if ($qtyChanged) {
                        $logModelClass = config('meta-catalog.models.meta_inventory_log', MetaInventoryLog::class);
                        $logModelClass::create([
                            'meta_catalog_item_id' => $localItem->id,
                            'meta_catalog_id'      => $localItem->meta_catalog_id,
                            'previous_quantity'    => $prevQty,
                            'new_quantity'         => $newQty,
                            'delta'                => $prevQty !== null ? $newQty - $prevQty : null,
                            'source'               => InventoryChangeSource::SYSTEM->value,
                            'notes'                => 'sync_from_api',
                        ]);
                    }
                }

                $count++;
            }

            $after = $response['paging']['cursors']['after'] ?? null;
            $hasNext = isset($response['paging']['next']);

        } while ($hasNext && $after !== null);

        return $count;
    }

    /**
     * Busca un producto en la base de datos local por retailer_id dentro de un catálogo.
     */
    public function findLocal(string $retailerId, MetaCatalog $catalog): ?MetaCatalogItem
    {
        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        return $modelClass::where('meta_catalog_id', $catalog->id)
            ->where('retailer_id', $retailerId)
            ->first();
    }

    // =========================================================================
    // Localized Catalog — Override Details API
    // =========================================================================

    /**
     * Obtiene los detalles de reemplazos (overrides) de un artículo de catálogo.
     *
     * Endpoint: GET /{item_id}/override_details
     *
     * Retorna los valores localizados (por país/idioma) que están vinculados
     * al artículo. Útil para verificar qué datos localizados tiene configurado
     * un producto específico.
     *
     * @param string              $productItemId  ID del artículo en Meta (meta_product_item_id)
     * @param MetaBusinessAccount $account        Cuenta para autenticación
     * @param array               $keys           Filtrar por claves específicas (ej: ['US', 'en_XX', 'GB'])
     * @param FeedOverrideType|null $type         Filtrar por tipo de override
     *
     * Ejemplo de respuesta:
     * {
     *   "override_details": [
     *     { "type": "country", "key": "US", "values": { "price": "9.99 USD" } },
     *     { "type": "language", "key": "es_XX", "values": { "title": "Camiseta" } }
     *   ]
     * }
     *
     * @return array Respuesta cruda de la API
     */
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Mapea los campos de la API de Meta a las columnas de la base de datos.
     * La API usa 'name', 'url'; la DB usa 'title', 'link'.
     * El precio se normaliza a "AMOUNT CURRENCY" cuando se envían separados.
     */
    private function mapApiDataToColumns(array $data): array
    {
        $columns = [];

        $map = [
            'name'                    => 'title',
            'description'             => 'description',
            'rich_text_description'   => 'rich_text_description',
            'brand'                   => 'brand',
            'category'                => 'category',
            'product_type'            => 'product_type',
            'fb_product_category'     => 'fb_product_category',
            'google_product_category' => 'google_product_category',
            'sale_price'              => 'sale_price',
            'sale_price_effective_date' => 'sale_price_effective_date',
            'availability'            => 'availability',
            'condition'               => 'condition',
            'quantity_to_sell_on_facebook' => 'quantity_to_sell_on_facebook',
            'image_url'               => 'image_url',
            'additional_image_urls'   => 'additional_image_urls',
            'mobile_link'             => 'mobile_link',
            'item_group_id'           => 'item_group_id',
            'color'                   => 'color',
            'size'                    => 'size',
            'gender'                  => 'gender',
            'age_group'               => 'age_group',
            'material'                => 'material',
            'pattern'                 => 'pattern',
            'custom_label_0'          => 'custom_label_0',
            'custom_label_1'          => 'custom_label_1',
            'custom_label_2'          => 'custom_label_2',
            'custom_label_3'          => 'custom_label_3',
            'custom_label_4'          => 'custom_label_4',
            'gtin'                    => 'gtin',
            'mpn'                     => 'mpn',
            'visibility'              => 'visibility',
            'shipping'                => 'shipping',
            'shipping_weight'         => 'shipping_weight',
            'retailer_id'             => 'retailer_id',
            'item_type'               => 'item_type',
        ];

        foreach ($map as $apiKey => $dbKey) {
            if (array_key_exists($apiKey, $data)) {
                $columns[$dbKey] = $data[$apiKey];
            }
        }

        // 'url' o 'link' → 'link'
        if (array_key_exists('url', $data)) {
            $columns['link'] = $data['url'];
        } elseif (array_key_exists('link', $data)) {
            $columns['link'] = $data['link'];
        }

        // price: si viene separado (int + currency) → "1999 USD"; si ya viene combinado lo deja tal cual
        if (array_key_exists('price', $data)) {
            $columns['price'] = isset($data['currency'])
                ? $data['price'] . ' ' . $data['currency']
                : $data['price'];
        }

        return $columns;
    }

    public function getOverrideDetails(
        string $productItemId,
        MetaBusinessAccount $account,
        array $keys = [],
        ?FeedOverrideType $type = null
    ): array {
        $client = $this->accountService->getApiClient($account);

        $query = [];

        if (!empty($keys)) {
            $query['keys'] = implode(',', $keys);
        }

        if ($type !== null) {
            $query['type'] = $type->value;
        }

        return $client->request(
            'GET',
            Endpoints::GET_ITEM_OVERRIDE_DETAILS,
            Endpoints::item($productItemId),
            null,
            $query
        );
    }
}
