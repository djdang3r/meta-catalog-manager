<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use ScriptDevelop\MetaCatalogManager\Enums\FeedOverrideType;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogItem;

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
     * Crea un producto individual en la Graph API.
     *
     * @return array Respuesta cruda de la API
     */
    public function createSingle(MetaCatalog $catalog, array $data): array
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        return $client->request(
            'POST',
            Endpoints::CREATE_PRODUCT,
            Endpoints::catalog($catalog->meta_catalog_id),
            $data
        );
    }

    /**
     * Actualiza un producto individual en la Graph API.
     *
     * @return array Respuesta cruda de la API
     */
    public function updateSingle(string $productItemId, MetaBusinessAccount $account, array $data): array
    {
        $client = $this->accountService->getApiClient($account);

        return $client->request(
            'POST',
            Endpoints::UPDATE_PRODUCT,
            Endpoints::product($productItemId),
            $data
        );
    }

    /**
     * Elimina un producto individual de la Graph API.
     *
     * @return array Respuesta cruda de la API
     */
    public function deleteSingle(string $productItemId, MetaBusinessAccount $account): array
    {
        $client = $this->accountService->getApiClient($account);

        return $client->request(
            'DELETE',
            Endpoints::DELETE_PRODUCT,
            Endpoints::product($productItemId)
        );
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
                $modelClass::updateOrCreate(
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
