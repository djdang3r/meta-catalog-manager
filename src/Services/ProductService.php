<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use ScriptDevelop\MetaCatalogManager\Enums\FeedOverrideType;
use ScriptDevelop\MetaCatalogManager\Enums\InventoryChangeSource;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogItem;
use ScriptDevelop\MetaCatalogManager\Models\MetaInventoryLog;
use Illuminate\Support\Facades\Log;

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

        // `fields` must list every field `syncFromApi()` reads, otherwise Meta
        // never returns it and the local columns stay empty. Meta's identifier
        // field is `mpn` (not `manufacturer_part_number`).
        $query = [
            'limit'  => $limit,
            'fields' => 'id,retailer_id,name,description,rich_text_description,url,price,sale_price,sale_price_effective_date,currency,availability,condition,quantity_to_sell_on_facebook,image_url,image_cdn_urls,additional_image_urls,images,brand,category,product_type,google_product_category,fb_product_category,item_group_id,color,size,gender,age_group,material,pattern,additional_variant_attribute,gtin,mpn,mobile_link,custom_label_0,custom_label_1,custom_label_2,custom_label_3,custom_label_4,internal_label,visibility,review_status',
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
        // Si el producto tiene campos de variante, usar items_batch (el endpoint /products no almacena item_group_id)
        if ($this->hasVariantFields($data)) {
            return $this->createViaItemsBatch($catalog, $data);
        }

        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        // Meta expects a decimal amount (e.g. 19.99); the local column stores
        // integer cents, so convert on the way out. The DB mapping keeps cents.
        $response = $client->request(
            'POST',
            Endpoints::CREATE_PRODUCT,
            Endpoints::catalog($catalog->meta_catalog_id),
            $this->withMetaPricesNumeric($data)
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
            $this->withMetaPrices($data)
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

        // Meta API single-product endpoint in v25.0 rejects certain fields
        // that the list endpoint accepts (item_group_id, additional_variant_attribute, etc).
        // Use only core fields that are known to work on the single endpoint.
        $coreFields = 'id,name,description,url,price,sale_price,currency,availability,condition,image_url,image_cdn_urls,images,brand,category,gtin';

        $response = $client->request(
            'GET',
            Endpoints::GET_PRODUCT,
            Endpoints::product($productItemId),
            null,
            ['fields' => $coreFields]
        );

        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        $item = $modelClass::where('meta_product_item_id', $productItemId)->firstOrFail();

        $columns = $this->mapApiDataToColumns($response);

        if (array_key_exists('image_cdn_urls', $response)) {
            $columns['image_cdn_url'] = $this->cdnUrlFrom($response['image_cdn_urls']);
        }

        // Clean prices to match syncFromApi's cents format
        if (array_key_exists('price', $columns)) {
            $cleaned = $this->cleanPrice($columns['price']);
            if ($cleaned !== null) {
                $columns['price'] = $cleaned;
            } else {
                unset($columns['price']);
            }
        }
        if (array_key_exists('sale_price', $columns)) {
            $cleaned = $this->cleanPrice($columns['sale_price']);
            if ($cleaned !== null) {
                $columns['sale_price'] = $cleaned;
            } else {
                unset($columns['sale_price']);
            }
        }

        // Strip null/empty values (same protection as syncFromApi)
        $columns = $this->filterFillData($columns);
        if (! empty($columns)) {
            $item->update($columns);
        }

        return $item->fresh();
    }

    /**
     * Sincroniza productos desde la API hacia la base de datos local.
     * Maneja paginación automáticamente.
     *
     * @return int Cantidad de productos sincronizados
     */

    /**
     * Soft-delete de los productos locales que ya no existen en Meta.
     *
     * @param  array<int, string>  $seenProductIds  ids de Meta vistos en la lectura
     * @return int  número de productos marcados como borrados
     */

    /**
     * Campos de imagen locales a partir de un item de la API de Meta.
     *
     * Si Meta devuelve `images` (aunque sea una lista vacía) es la fuente de
     * verdad y la lista local se actualiza, incluido el caso de haberse borrado
     * todas las imágenes: antes un `!empty()` saltaba el bloque y la lista local
     * se quedaba con imágenes que ya no existen en Meta.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    /**
     * Extrae la mejor URL del CDN de Meta desde `image_cdn_urls`
     * (lista de {key, value}; `full` es la de mayor calidad, `requested` la pedida).
     */
    private function cdnUrlFrom(mixed $cdnUrls): ?string
    {
        if (! is_array($cdnUrls)) {
            return null;
        }

        $byKey = [];
        foreach ($cdnUrls as $entry) {
            if (is_array($entry) && isset($entry['key'], $entry['value'])) {
                $byKey[(string) $entry['key']] = (string) $entry['value'];
            }
        }

        $url = $byKey['full'] ?? $byKey['requested'] ?? null;

        return ($url !== null && $url !== '') ? $url : null;
    }

    public function imageFieldsFromApiItem(array $item): array
    {
        $fields = [];

        if (array_key_exists('images', $item) && is_array($item['images'])) {
            $allUrls = [];

            foreach ($item['images'] as $imgJson) {
                $decoded = is_string($imgJson) ? json_decode($imgJson, true) : $imgJson;

                if (isset($decoded['url']) && !empty($decoded['url'])) {
                    $allUrls[] = $decoded['url'];
                }
            }

            $fields['image_url'] = $allUrls[0] ?? null;
            $fields['additional_image_urls'] = count($allUrls) > 1 ? array_slice($allUrls, 1) : [];

            return $fields;
        }

        // Fallback a los campos explícitos cuando `images` no viaja.
        if (array_key_exists('image_url', $item)) {
            $fields['image_url'] = $item['image_url'];
        }

        if (array_key_exists('additional_image_urls', $item)) {
            $fields['additional_image_urls'] = is_array($item['additional_image_urls'])
                ? $item['additional_image_urls']
                : explode(',', (string) $item['additional_image_urls']);
        } elseif (array_key_exists('additional_image_link', $item)) {
            $fields['additional_image_urls'] = is_array($item['additional_image_link'])
                ? $item['additional_image_link']
                : explode(',', (string) $item['additional_image_link']);
        }

        return $fields;
    }

    public function cleanupOrphans(MetaCatalog $catalog, array $seenProductIds): int
    {
        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        // Sólo se consideran huérfanos los items que estuvieron en Meta: un item
        // local todavía sin publicar no es un fantasma y no debe borrarse.
        $orphaned = $modelClass::where('meta_catalog_id', $catalog->id)
            ->whereNotNull('meta_product_item_id')
            ->whereNotIn('meta_product_item_id', $seenProductIds);

        $orphanedCount = $orphaned->count();

        if ($orphanedCount > 0) {
            $orphaned->delete();

            Log::info('ProductService::cleanupOrphans — cleaned up orphaned products', [
                'catalog_id' => $catalog->meta_catalog_id,
                'orphaned_count' => $orphanedCount,
            ]);
        }

        return $orphanedCount;
    }

    /**
     * Deduce el `item_group_id` de una variante a partir del patrón del
     * `retailer_id` ("KLAN-009-38" -> padre "KLAN-009").
     *
     * La búsqueda queda SIEMPRE acotada al catálogo: antes el `orWhere` sin
     * agrupar rompía el filtro y podía tomar el padre de otro catálogo (incluso de
     * otra empresa con el mismo patrón de SKU).
     */
    public function inferItemGroupId(MetaCatalog $catalog, $localItem): bool
    {
        $retailerId = (string) $localItem->retailer_id;

        if ($retailerId === '' || !empty($localItem->item_group_id)) {
            return false;
        }

        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        $parent = $modelClass::where('meta_catalog_id', $catalog->id)
            ->where(function ($query) use ($retailerId) {
                $query->where('item_group_id', $retailerId)
                    ->orWhere(function ($sub) use ($retailerId) {
                        $parts = explode('-', $retailerId);

                        while (count($parts) > 1) {
                            array_pop($parts);
                            $candidate = implode('-', $parts);
                            $sub->orWhere('retailer_id', $candidate)->orWhere('item_group_id', $candidate);
                        }
                    });
            })
            ->whereNotNull('item_group_id')
            ->limit(1)
            ->get()
            ->first(function ($parent) use ($localItem) {
                return $parent->item_group_id && $parent->retailer_id !== $localItem->retailer_id;
            });

        if (!$parent) {
            return false;
        }

        $localItem->update(['item_group_id' => $parent->item_group_id]);

        return true;
    }

    public function syncFromApi(MetaCatalog $catalog): int
    {
        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);
        $count  = 0;
        $after  = null;
        $seenProductIds = [];

        do {
            $response = $this->getFromApi($catalog, 200, $after);
            $items    = $response['data'] ?? [];

            foreach ($items as $item) {
                // Track product IDs from Meta for orphan cleanup
                if (!empty($item['id'])) {
                    $seenProductIds[] = $item['id'];
                }

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
                if (array_key_exists('price', $item)) {
                    $fillData['price'] = $this->cleanPrice($item['price']);
                }
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
                // Imágenes: `images` es la fuente de verdad cuando viene (aunque
                // venga vacío, que es cuando se han borrado todas en Meta).
                $fillData = array_merge($fillData, $this->imageFieldsFromApiItem($item));

                if (array_key_exists('image_cdn_urls', $item)) {
                    $fillData['image_cdn_url'] = $this->cdnUrlFrom($item['image_cdn_urls']);
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

                // Apply only the fields Meta actually returned, excluding null/empty values.
                // Prevents overwriting existing data when Meta returns blank fields
                // (e.g. price = "" would wipe the stored price).
                $fillData = $this->filterFillData($fillData);
                if (!empty($fillData)) {
                    $localItem->fill($fillData)->save();
                }

                // Repair: if critical fields are still null after sync (corrupted from
                // a previous buggy sync), force-fetch the individual product from Meta's
                // single-product endpoint which returns more complete data.
                if (! $localItem->wasRecentlyCreated
                    && ! empty($localItem->meta_product_item_id)
                    && $this->hasNullCriticalFields($localItem)
                ) {
                    try {
                        $this->getSingle($localItem->meta_product_item_id, $catalog->account);
                        Log::info('ProductService::syncFromApi — repaired critical fields from single endpoint', [
                            'product_id' => $localItem->meta_product_item_id,
                            'retailer_id' => $localItem->retailer_id,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('ProductService::syncFromApi — repair fetch failed', [
                            'product_id' => $localItem->meta_product_item_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Infer item_group_id from retailer_id pattern when Meta doesn't provide it.
                // e.g., "KLAN-009-38" is a variant of parent "KLAN-009"
                if (empty($localItem->item_group_id) && !empty($localItem->retailer_id)) {
                    $this->inferItemGroupId($catalog, $localItem);
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

        // Clean up orphaned products: soft-delete local products no longer present in
        // Meta. Se ejecuta SIEMPRE que la lectura termine correctamente, incluido el
        // caso de que Meta devuelva 0 productos (antes se saltaba y quedaban
        // fantasmas). Si la lectura falla, se lanza y no se borra nada.
        $this->cleanupOrphans($catalog, $seenProductIds);

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

    /**
     * Busca un producto local por retailer_id. Con un catálogo limita la
     * búsqueda a ese catálogo; sin él, busca en todos (primer match).
     * Permite resolver un producto desde Meta sin conocer el catálogo.
     */
    public function findByRetailerId(string $retailerId, ?MetaCatalog $catalog = null): ?MetaCatalogItem
    {
        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        $query = $modelClass::where('retailer_id', $retailerId);

        if ($catalog !== null) {
            $query->where('meta_catalog_id', $catalog->id);
        }

        return $query->first();
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
     * Filter out null, empty string, and empty array values from fill data.
     * Preserves numeric zero (valid for quantity_to_sell_on_facebook = 0).
     * Prevents blank Meta API responses from overwriting existing local data.
     */
    private function filterFillData(array $fillData): array
    {
        return array_filter($fillData, function ($v, $k) {
            if ($v === 0 || $v === '0') return true;
            if ($v === null || $v === '') return false;
            if (is_array($v) && empty($v)) return false;
            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Check if a local product has null critical fields that need repair.
     * These fields may have been corrupted by a previous buggy sync and
     * need a force-refetch from Meta's single-product endpoint.
     */
    private function hasNullCriticalFields(MetaCatalogItem $item): bool
    {
        $criticalFields = ['price', 'title', 'currency'];

        foreach ($criticalFields as $field) {
            if (empty($item->{$field})) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse Meta API price string to integer cents.
     * "COP6,000" → 600000 | "19.99" → 1999 | "6.000,00" → 600000
     */
    private function cleanPrice(?string $price): ?int
    {
        if ($price === null || $price === '') {
            return null;
        }

        $price = trim(preg_replace('/[\$\s\x{00A0}]/u', '', $price));
        $price = preg_replace('/^[A-Z]{3}\s*/', '', $price);
        $price = preg_replace('/\s*[A-Z]{3}$/', '', $price);
        $price = trim($price);

        if ($price === '' || $price === '0') {
            return 0;
        }

        if (preg_match('/^[0-9]+$/', $price)) {
            return (int) round((float) $price * 100);
        }

        $lastComma = strrpos($price, ',');
        $lastDot   = strrpos($price, '.');

        if ($lastComma > $lastDot) {
            $digitsAfterComma = strlen(substr($price, $lastComma + 1));
            if ($lastDot === false && $digitsAfterComma === 3) {
                $price = str_replace(',', '', $price);
            } else {
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            }
        } elseif ($lastDot > $lastComma) {
            $digitsAfterDot = strlen(substr($price, $lastDot + 1));
            if ($lastComma === false && $digitsAfterDot === 3) {
                $price = str_replace('.', '', $price);
            } else {
                $price = str_replace(',', '', $price);
            }
        } else {
            $price = str_replace(['.', ','], '', $price);
        }

        return (int) round((float) $price * 100);
    }

    /**
     * Convierte un monto en centavos (formato local) a un decimal de Meta
     * (ej: 1999 → "19.99"). Meta espera el precio en la moneda del catálogo,
     * NO en unidades menores.
     */
    private function centsToMetaPrice(mixed $amount): string
    {
        // Meta interpreta un número sin moneda como CENTAVOS: publicar
        // "600000.00" deja el precio en COP 6.000. Dividir entre 100 enviaba el
        // precio 100 veces más barato (el bug de las variantes, `products` y
        // WooCommerce). Con `prices.already_in_cents` el valor viaja tal cual
        // (unidad de la app); sin la opción se mantiene el comportamiento
        // anterior para no romper a quien lo compensaba por su cuenta.
        if (config('meta-catalog.prices.already_in_cents', false)) {
            return number_format((float) $amount, 2, '.', '');
        }

        return number_format(((float) $amount) / 100, 2, '.', '');
    }

    /**
     * Devuelve una copia de $data con price/sale_price convertidos de centavos
     * locales al decimal que espera Meta. La columna local sigue en centavos.
     */
    /**
     * Igual que `withMetaPrices()`, pero devolviendo el precio como **número**.
     *
     * El endpoint `/products` (crear producto) exige `price` numérico: enviar el texto
     * "2100000.00" devuelve `(#100) Param price must be a number`. Con la opción
     * `meta-catalog.prices.already_in_cents` el valor ya está en centavos y se envía el
     * entero; sin ella se mantiene el importe (comportamiento anterior).
     */
    private function withMetaPricesNumeric(array $data): array
    {
        foreach (['price', 'sale_price'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $data[$key] = config('meta-catalog.prices.already_in_cents', false)
                    ? (int) round((float) $data[$key])
                    : round(((float) $data[$key]) / 100, 2);
            }
        }

        return $data;
    }

    private function withMetaPrices(array $data): array
    {
        foreach (['price', 'sale_price'] as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                $data[$key] = $this->centsToMetaPrice($data[$key]);
            }
        }

        return $data;
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
            'additional_variant_attribute' => 'additional_variant_attribute',
            'custom_label_0'          => 'custom_label_0',
            'custom_label_1'          => 'custom_label_1',
            'custom_label_2'          => 'custom_label_2',
            'custom_label_3'          => 'custom_label_3',
            'custom_label_4'          => 'custom_label_4',
            'internal_label'          => 'internal_label',
            'gtin'                    => 'gtin',
            'manufacturer_part_number' => 'mpn',
            'mpn'                     => 'mpn',
            'visibility'              => 'visibility',
            'review_status'           => 'review_status',
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

    /**
     * Detecta si el producto tiene campos de variante que requieren items_batch.
     */
    private function hasVariantFields(array $data): bool
    {
        // El nombre documentado por Meta es el plural (`additional_variant_attributes`,
        // objeto JSON {nombre: valor}); se acepta también el singular antiguo.
        $variantKeys = ['item_group_id', 'color', 'size', 'gender', 'age_group', 'material', 'pattern', 'additional_variant_attributes', 'additional_variant_attribute'];
        foreach ($variantKeys as $key) {
            if (!empty($data[$key])) return true;
        }
        return false;
    }

    /**
     * Crea un producto vía items_batch (necesario para item_group_id y variantes).
     */

    /**
     * Payload de un item para `items_batch` (variantes incluidas).
     *
     * `items_batch` no comparte el contrato de `/products`: necesita el
     * identificador, los nombres de campo propios (title/image_link/link...) y el
     * importe **con su moneda ISO 4217** ("600000.00 COP"). Sin la moneda Meta
     * rechaza la entrada, y sin este mapeo el `item_group_id` de la variante se
     * perdía al publicarse por el endpoint de productos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function batchItemPayload(MetaCatalog $catalog, array $data): array
    {
        $batchData = $data;

        if (empty($batchData['id'])) {
            $batchData['id'] = $data['retailer_id'] ?? null;
        }

        if (isset($batchData['name']) && !isset($batchData['title'])) {
            $batchData['title'] = $batchData['name'];
        }
        if (isset($batchData['image_url']) && !isset($batchData['image_link'])) {
            $batchData['image_link'] = $batchData['image_url'];
        }
        if (isset($batchData['additional_image_urls']) && !isset($batchData['additional_image_link'])) {
            $batchData['additional_image_link'] = $batchData['additional_image_urls'];
        }
        if (isset($batchData['url']) && !isset($batchData['link'])) {
            $batchData['link'] = $batchData['url'];
        }

        $batchData = $this->withMetaPrices($batchData);

        // items_batch espera el objeto de atributos de variante como cadena JSON.
        if (isset($batchData['additional_variant_attributes']) && is_array($batchData['additional_variant_attributes'])) {
            $batchData['additional_variant_attributes'] = json_encode(
                $batchData['additional_variant_attributes'],
                JSON_UNESCAPED_UNICODE
            );
        }
        unset($batchData['additional_variant_attribute']);

        // Moneda ISO 4217 obligatoria en el importe. Se toma del propio item o del
        // catálogo; si no hay ninguna, se falla con un mensaje accionable en lugar
        // de publicar un precio sin moneda.
        $currency = trim((string) ($data['currency'] ?? $catalog->currency ?? ''));

        foreach (['price', 'sale_price'] as $priceKey) {
            if (!isset($batchData[$priceKey]) || !is_string($batchData[$priceKey])) {
                continue;
            }

            if (!str_contains($batchData[$priceKey], ' ')) {
                if ($currency === '') {
                    throw new \RuntimeException(
                        "Falta la moneda (ISO 4217) para publicar el precio de {$data['retailer_id']} por items_batch."
                    );
                }

                // Con la moneda explícita Meta espera el IMPORTE ("25000.00 COP"),
                // no centavos: verificado en producción (enviar "2500000.00 COP"
                // dejó el precio en COP 2.500.000). Si el valor viene en centavos
                // (opción already_in_cents) se convierte; si ya es importe, se deja.
                $valor = (float) $batchData[$priceKey];
                if (config('meta-catalog.prices.already_in_cents', false)) {
                    $valor /= 100;
                }

                $batchData[$priceKey] = number_format($valor, 2, '.', '').' '.strtoupper($currency);
            }
        }

        return $batchData;
    }

    private function createViaItemsBatch(MetaCatalog $catalog, array $data): MetaCatalogItem
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $batchData = $this->batchItemPayload($catalog, $data);

        $payload = [
            'item_type' => 'PRODUCT_ITEM',
            'requests' => [[
                'method'      => 'CREATE',
                'retailer_id' => $data['retailer_id'],
                'data'        => $batchData,
            ]],
        ];

        $response = $client->request(
            'POST',
            Endpoints::ITEMS_BATCH,
            Endpoints::catalog($catalog->meta_catalog_id),
            $payload
        );

        $handles = $response['handles'] ?? [];
        if (empty($handles)) {
            throw new \RuntimeException('items_batch: no handles returned — ' . json_encode($response));
        }

        $retailerId = $data['retailer_id'];
        $productId = $this->waitForBatchHandle($catalog, $handles[0], $retailerId);

        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);

        return $modelClass::updateOrCreate(
            ['meta_catalog_id' => $catalog->id, 'retailer_id' => $data['retailer_id']],
            array_merge(
                $this->mapApiDataToColumns($data),
                ['meta_product_item_id' => $productId]
            )
        );
    }

    /**
     * Polling del estado de un handle de items_batch hasta que se complete.
     */
    private function waitForBatchHandle(MetaCatalog $catalog, string $handle, string $retailerId, int $maxRetries = 20, int $delayMs = 3000): string
    {
        $client = $this->accountService->getApiClient($catalog->account);

        for ($i = 0; $i < $maxRetries; $i++) {
            usleep($delayMs * 1000);

            $status = $client->request(
                'GET',
                Endpoints::CHECK_BATCH_STATUS,
                Endpoints::catalog($catalog->meta_catalog_id),
                null,
                ['handle' => $handle]
            );

            $items = $status['data'] ?? [];
            foreach ($items as $item) {
                if (($item['handle'] ?? '') !== $handle) continue;

                $batchStatus = $item['status'] ?? '';
                if ($batchStatus === 'finished' || $batchStatus === 'SUCCESS') {
                    // Look up product by retailer_id via the products API
                    return $this->findProductByRetailerId($catalog, $retailerId);
                }
                if ($batchStatus === 'failed') {
                    $errors = $item['errors'] ?? [];
                    $msg = $errors[0]['message'] ?? 'unknown error';
                    throw new \RuntimeException('items_batch error: ' . $msg);
                }
            }
        }

        throw new \RuntimeException('items_batch timeout for handle: ' . $handle);
    }

    private function findProductByRetailerId(MetaCatalog $catalog, string $retailerId, int $maxRetries = 8): string
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            if ($i > 0) usleep(3000000); // 3s delay between retries
            $response = $this->getFromApi($catalog, 200);
            foreach ($response['data'] ?? [] as $item) {
                if (($item['retailer_id'] ?? '') === $retailerId) {
                    return $item['id'];
                }
            }
        }
        throw new \RuntimeException('items_batch: product not found for retailer_id: ' . $retailerId);
    }

    /**
     * Crea múltiples productos (variantes) en un solo request items_batch.
     * Retorna array con resultados individuales: ok/error por cada variante.
     */
    public function createBatch(MetaCatalog $catalog, array $items): array
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $requests = [];
        foreach ($items as $item) {
            // Mismo contrato que el resto del lote: `batchItemPayload()` aporta el
            // `id`, los nombres de campo propios de items_batch y el precio con su
            // moneda ISO 4217 (antes se duplicaba el mapeo y el precio viajaba sin
            // moneda, que Meta rechaza).
            $requests[] = [
                'method' => 'CREATE',
                'retailer_id' => $item['retailer_id'],
                'data' => $this->batchItemPayload($catalog, $item),
            ];
        }

        $payload = ['item_type' => 'PRODUCT_ITEM', 'requests' => $requests];
        $response = $client->request('POST', Endpoints::ITEMS_BATCH, Endpoints::catalog($catalog->meta_catalog_id), $payload);

        $handles = $response['handles'] ?? [];
        $results = [];

        foreach ($items as $i => $item) {
            $handle = $handles[$i] ?? null;
            try {
                if (!$handle) {
                    // Product might already exist from a previous attempt — try to find it
                    $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);
                    $existing = $modelClass::where('meta_catalog_id', $catalog->id)
                        ->where('retailer_id', $item['retailer_id'])->first();
                    if ($existing && $existing->meta_product_item_id) {
                        $results[] = ['success' => true, 'retailer_id' => $item['retailer_id'], 'id' => $existing->meta_product_item_id];
                        continue;
                    }
                    throw new \RuntimeException('No handle — posible retailer_id duplicado o ya existe en Meta');
                }
                $productId = $this->waitForBatchHandle($catalog, $handle, $item['retailer_id'], 20, 3000);

                $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);
                $modelClass::updateOrCreate(
                    ['meta_catalog_id' => $catalog->id, 'retailer_id' => $item['retailer_id']],
                    array_merge($this->mapApiDataToColumns($item), ['meta_product_item_id' => $productId])
                );

                $results[] = ['success' => true, 'retailer_id' => $item['retailer_id'], 'id' => $productId];
            } catch (\Exception $e) {
                // Fallback: product exists in Meta but not indexed yet.
                // Save locally with correct data (including item_group_id) even without meta_product_item_id.
                // Next sync will fill in the meta_product_item_id.
                $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);
                $modelClass::updateOrCreate(
                    ['meta_catalog_id' => $catalog->id, 'retailer_id' => $item['retailer_id']],
                    array_merge($this->mapApiDataToColumns($item), ['meta_product_item_id' => null])
                );

                // Now do a full sync which will fill in the missing meta_product_item_id from Meta
                try {
                    $this->syncFromApi($catalog);
                    $existing = $modelClass::where('meta_catalog_id', $catalog->id)
                        ->where('retailer_id', $item['retailer_id'])->first();
                    if ($existing && $existing->meta_product_item_id) {
                        $results[] = ['success' => true, 'retailer_id' => $item['retailer_id'], 'id' => $existing->meta_product_item_id];
                    } else {
                        $results[] = ['success' => true, 'retailer_id' => $item['retailer_id'], 'id' => 'pending_sync', 'note' => 'Guardado localmente. Sincronizá para obtener el ID de Meta.'];
                    }
                } catch (\Exception $fallbackError) {
                    $results[] = ['success' => true, 'retailer_id' => $item['retailer_id'], 'id' => 'pending_sync', 'note' => 'Guardado localmente. Error en sync: ' . $fallbackError->getMessage()];
                }
            }
        }

        return $results;
    }
}
