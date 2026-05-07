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

        $query = [
            'limit'  => $limit,
            'fields' => 'id,retailer_id,name,description,url,price,sale_price,currency,availability,condition,image_url,additional_image_urls,images,brand,category,item_group_id,gtin,manufacturer_part_number',
        ];
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
            ['fields' => 'id,retailer_id,name,description,url,price,sale_price,currency,availability,condition,image_url,additional_image_urls,images,brand,category,item_group_id,gtin,manufacturer_part_number']
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
                $localItem = $modelClass::firstOrNew(
                    [
                        'meta_catalog_id' => $catalog->id,
                        'retailer_id'     => $item['retailer_id'] ?? $item['id'],
                    ],
                    // Defaults only for new records
                    [
                        'meta_product_item_id' => $item['id'] ?? null,
                        'availability'         => 'in stock',
                        'condition'            => 'new',
                        'visibility'           => 'published',
                    ]
                );

                // Build fill data ONLY from fields present in the API response.
                // Fields NOT returned by Meta are preserved (not overwritten with null).
                $fillData = [];

                // Core (always present via fields param)
                if (array_key_exists('id', $item))                      $fillData['meta_product_item_id'] = $item['id'];
                if (array_key_exists('name', $item))                    $fillData['title'] = $item['name'];
                if (array_key_exists('description', $item))             $fillData['description'] = $item['description'];
                if (array_key_exists('rich_text_description', $item))   $fillData['rich_text_description'] = $item['rich_text_description'];
                if (array_key_exists('brand', $item))                   $fillData['brand'] = $item['brand'];

                // Categories
                if (array_key_exists('category', $item))                $fillData['category'] = $item['category'];
                if (array_key_exists('product_type', $item))            $fillData['product_type'] = $item['product_type'];
                if (array_key_exists('fb_product_category', $item))     $fillData['fb_product_category'] = $item['fb_product_category'];
                if (array_key_exists('google_product_category', $item)) $fillData['google_product_category'] = $item['google_product_category'];

                // Pricing
                if (array_key_exists('price', $item))                   $fillData['price'] = $this->cleanPrice($item['price']);
                if (array_key_exists('sale_price', $item))              $fillData['sale_price'] = $this->cleanPrice($item['sale_price']);
                if (array_key_exists('sale_price_effective_date', $item)) $fillData['sale_price_effective_date'] = $item['sale_price_effective_date'];
                if (array_key_exists('currency', $item))                $fillData['currency'] = $item['currency'];

                // Availability / Condition
                if (array_key_exists('availability', $item))            $fillData['availability'] = $item['availability'];
                if (array_key_exists('condition', $item))               $fillData['condition'] = $item['condition'];
                if (array_key_exists('quantity_to_sell_on_facebook', $item)) $fillData['quantity_to_sell_on_facebook'] = $item['quantity_to_sell_on_facebook'];

                // Images — use the `images` field (array of JSON strings) as canonical source.
                // It contains ALL product images (main + additional) regardless of how they were added.
                // Fall back to explicit image_url/additional_image_urls fields if `images` not present.
                if (array_key_exists('images', $item) && is_array($item['images']) && !empty($item['images'])) {
                    $allUrls = [];
                    foreach ($item['images'] as $imgJson) {
                        $decoded = is_string($imgJson) ? json_decode($imgJson, true) : $imgJson;
                        if (isset($decoded['url']) && !empty($decoded['url'])) {
                            $allUrls[] = $decoded['url'];
                        }
                    }
                    if (!empty($allUrls)) {
                        $fillData['image_url'] = $allUrls[0];
                        $fillData['additional_image_urls'] = count($allUrls) > 1 ? array_slice($allUrls, 1) : [];
                    }
                } else {
                    // Fallback to explicit fields
                    if (array_key_exists('image_url', $item)) {
                        $fillData['image_url'] = $item['image_url'];
                    }
                    if (array_key_exists('additional_image_urls', $item)) {
                        $fillData['additional_image_urls'] = is_array($item['additional_image_urls'])
                            ? $item['additional_image_urls']
                            : explode(',', $item['additional_image_urls']);
                    } elseif (array_key_exists('additional_image_link', $item)) {
                        $fillData['additional_image_urls'] = is_array($item['additional_image_link'])
                            ? $item['additional_image_link']
                            : explode(',', $item['additional_image_link']);
                    }
                }

                // Links
                if (array_key_exists('url', $item) || array_key_exists('link', $item))
                    $fillData['link'] = $item['url'] ?? $item['link'] ?? null;
                if (array_key_exists('mobile_link', $item))             $fillData['mobile_link'] = $item['mobile_link'];

                // Variants
                if (array_key_exists('item_group_id', $item))           $fillData['item_group_id'] = $item['item_group_id'];
                if (array_key_exists('color', $item))                   $fillData['color'] = $item['color'];
                if (array_key_exists('size', $item))                    $fillData['size'] = $item['size'];
                if (array_key_exists('gender', $item))                  $fillData['gender'] = $item['gender'];
                if (array_key_exists('age_group', $item))               $fillData['age_group'] = $item['age_group'];
                if (array_key_exists('material', $item))                $fillData['material'] = $item['material'];
                if (array_key_exists('pattern', $item))                 $fillData['pattern'] = $item['pattern'];

                // Labels
                if (array_key_exists('custom_label_0', $item))          $fillData['custom_label_0'] = $item['custom_label_0'];
                if (array_key_exists('custom_label_1', $item))          $fillData['custom_label_1'] = $item['custom_label_1'];
                if (array_key_exists('custom_label_2', $item))          $fillData['custom_label_2'] = $item['custom_label_2'];
                if (array_key_exists('custom_label_3', $item))          $fillData['custom_label_3'] = $item['custom_label_3'];
                if (array_key_exists('custom_label_4', $item))          $fillData['custom_label_4'] = $item['custom_label_4'];
                if (array_key_exists('internal_label', $item))          $fillData['internal_label'] = $item['internal_label'];

                // Identifiers
                if (array_key_exists('gtin', $item))                    $fillData['gtin'] = $item['gtin'];
                if (array_key_exists('mpn', $item))                     $fillData['mpn'] = $item['mpn'];

                // Status
                if (array_key_exists('visibility', $item))              $fillData['visibility'] = $item['visibility'];
                if (array_key_exists('review_status', $item))           $fillData['review_status'] = $item['review_status'];

                // Apply only the fields Meta actually returned
                if (!empty($fillData)) {
                    $localItem->fill($fillData)->save();
                }

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
     * Clean price string from Meta API (removes currency symbols, spaces, etc).
     */
    private function cleanPrice(?string $price): ?string
    {
        if ($price === null || $price === '') {
            return null;
        }

        // Strip currency symbols, spaces, non-breaking spaces
        $price = trim(preg_replace('/[\$\s\x{00A0}]/u', '', $price));

        // Detect format:
        // - "100.000,00" (LatAm: dot=thousands, comma=decimal) → 10000000
        // - "100,000.00" (US: comma=thousands, dot=decimal) → 10000000  
        // - "100.000"    (Colombian: dot=thousands, no decimals) → 10000000
        // - "1999"       (already in cents/smallest unit) → 1999

        // If already a plain integer without any separators, return as-is
        if (preg_match('/^[0-9]+$/', $price)) {
            return $price;
        }

        // Remove thousand separators (dots or commas)
        // Detect which is decimal separator by last occurrence
        $lastComma = strrpos($price, ',');
        $lastDot = strrpos($price, '.');

        if ($lastComma > $lastDot) {
            // Comma is decimal: "100.000,00" → remove dots, replace comma with dot
            $price = str_replace('.', '', $price);
            $price = str_replace(',', '.', $price);
        } elseif ($lastDot > $lastComma) {
            // Dot is decimal: "100,000.00" → remove commas
            // BUT if no comma exists and digits after dot = 3, it's a thousand separator (e.g. "120.000")
            $digitsAfterDot = strlen(substr($price, $lastDot + 1));
            if ($lastComma === false && $digitsAfterDot === 3) {
                // Thousand separator: "120.000" → "120000" → ×100
                $price = str_replace('.', '', $price);
            } else {
                // Decimal: "19.99" → keep dot
                $price = str_replace(',', '', $price);
            }
        } else {
            // No decimal separator found, just thousand separators: "100.000" → "100000"
            $price = str_replace(['.', ','], '', $price);
        }

        // Convert to float and multiply by 100 to get cents
        $cents = (int) round(((float) $price) * 100);

        return (string) $cents;
    }

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
            'manufacturer_part_number' => 'mpn',
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

        // price y currency: almacenar por separado
        if (array_key_exists('price', $data)) {
            $columns['price'] = $data['price'];
        }
        if (array_key_exists('currency', $data)) {
            $columns['currency'] = $data['currency'];
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
