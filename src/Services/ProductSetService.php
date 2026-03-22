<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaProductSet;

class ProductSetService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Obtiene los product sets de un catálogo desde la Graph API.
     *
     * @return array Respuesta cruda de la API
     */
    public function fetchFromApi(MetaCatalog $catalog): array
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        return $client->request(
            'GET',
            Endpoints::GET_PRODUCT_SETS,
            Endpoints::catalog($catalog->meta_catalog_id)
        );
    }

    /**
     * Sincroniza product sets desde la API hacia la base de datos.
     *
     * @return Collection<MetaProductSet>
     */
    public function syncFromApi(MetaCatalog $catalog): Collection
    {
        $response   = $this->fetchFromApi($catalog);
        $data       = $response['data'] ?? [];
        $modelClass = config('meta-catalog.models.meta_product_set', MetaProductSet::class);
        $synced     = collect();

        foreach ($data as $apiSet) {
            $set = $modelClass::updateOrCreate(
                ['meta_product_set_id' => $apiSet['id']],
                [
                    'meta_catalog_id' => $catalog->id,
                    'name'            => $apiSet['name'] ?? null,
                    'filter'          => isset($apiSet['filter']) ? json_decode($apiSet['filter'], true) : null,
                    'product_count'   => $apiSet['product_count'] ?? 0,
                ]
            );

            $synced->push($set);
        }

        return $synced;
    }

    /**
     * Crea un product set en la Graph API y lo guarda en la DB.
     */
    public function create(MetaCatalog $catalog, array $data): MetaProductSet
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $response = $client->request(
            'POST',
            Endpoints::CREATE_PRODUCT_SET,
            Endpoints::catalog($catalog->meta_catalog_id),
            $data
        );

        $modelClass = config('meta-catalog.models.meta_product_set', MetaProductSet::class);

        return $modelClass::create([
            'meta_catalog_id'    => $catalog->id,
            'meta_product_set_id' => $response['id'],
            'name'               => $data['name'] ?? null,
            'filter'             => $data['filter'] ?? null,
        ]);
    }

    /**
     * Actualiza un product set en la Graph API y sincroniza en DB.
     */
    public function update(MetaProductSet $productSet, array $data): MetaProductSet
    {
        $account = $productSet->catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $client->request(
            'POST',
            Endpoints::UPDATE_PRODUCT_SET,
            Endpoints::productSet($productSet->meta_product_set_id),
            $data
        );

        $productSet->update($data);

        return $productSet->fresh();
    }

    /**
     * Elimina un product set de la Graph API y la DB.
     */
    public function delete(MetaProductSet $productSet): bool
    {
        $account = $productSet->catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $client->request(
            'DELETE',
            Endpoints::DELETE_PRODUCT_SET,
            Endpoints::productSet($productSet->meta_product_set_id)
        );

        return (bool) $productSet->delete();
    }

    /**
     * Obtiene los productos de un product set desde la Graph API.
     *
     * @return array Respuesta cruda de la API
     */
    public function getProducts(MetaProductSet $productSet): array
    {
        $account = $productSet->catalog->account;
        $client  = $this->accountService->getApiClient($account);

        return $client->request(
            'GET',
            Endpoints::GET_PRODUCT_SET_PRODUCTS,
            Endpoints::productSet($productSet->meta_product_set_id)
        );
    }
}
