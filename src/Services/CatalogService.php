<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\ApiClient;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;

class CatalogService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Obtiene los catálogos del Business Manager desde la Graph API.
     *
     * @return array Datos crudos de la API
     */
    public function fetchFromApi(MetaBusinessAccount $account): array
    {
        $client = $this->accountService->getApiClient($account);

        return $client->request(
            'GET',
            Endpoints::GET_CATALOGS,
            Endpoints::business($account->meta_business_id)
        );
    }

    /**
     * Sincroniza los catálogos de la API con la base de datos local.
     *
     * @return Collection<MetaCatalog>
     */
    public function syncFromApi(MetaBusinessAccount $account): Collection
    {
        $response = $this->fetchFromApi($account);
        $data     = $response['data'] ?? [];
        $modelClass = config('meta-catalog.models.meta_catalog', MetaCatalog::class);
        $synced = collect();

        foreach ($data as $apiCatalog) {
            $catalog = $modelClass::updateOrCreate(
                ['meta_catalog_id' => $apiCatalog['id']],
                [
                    'meta_business_account_id' => $account->id,
                    'name'                     => $apiCatalog['name'] ?? null,
                    'vertical'                 => $apiCatalog['vertical'] ?? 'commerce',
                ]
            );

            $synced->push($catalog);
        }

        return $synced;
    }

    /**
     * Crea un catálogo en la Graph API y lo guarda en la base de datos.
     */
    public function create(MetaBusinessAccount $account, array $data): MetaCatalog
    {
        $client = $this->accountService->getApiClient($account);

        $response = $client->request(
            'POST',
            Endpoints::CREATE_CATALOG,
            Endpoints::business($account->meta_business_id),
            $data
        );

        $modelClass = config('meta-catalog.models.meta_catalog', MetaCatalog::class);

        return $modelClass::create([
            'meta_business_account_id' => $account->id,
            'meta_catalog_id'          => $response['id'],
            'name'                     => $data['name'] ?? null,
            'vertical'                 => $data['vertical'] ?? 'commerce',
        ]);
    }

    /**
     * Busca un catálogo por su ULID interno.
     */
    public function find(string $id): ?MetaCatalog
    {
        $modelClass = config('meta-catalog.models.meta_catalog', MetaCatalog::class);

        return $modelClass::find($id);
    }

    /**
     * Busca un catálogo por el Meta Catalog ID.
     */
    public function findByMetaCatalogId(string $metaCatalogId): ?MetaCatalog
    {
        $modelClass = config('meta-catalog.models.meta_catalog', MetaCatalog::class);

        return $modelClass::where('meta_catalog_id', $metaCatalogId)->first();
    }

    /**
     * Retorna todos los catálogos de una cuenta.
     *
     * @return Collection<MetaCatalog>
     */
    public function forAccount(MetaBusinessAccount $account): Collection
    {
        $modelClass = config('meta-catalog.models.meta_catalog', MetaCatalog::class);

        return $modelClass::where('meta_business_account_id', $account->id)->get();
    }

    /**
     * Obtiene los detalles de un catálogo específico desde la Graph API y actualiza la DB local.
     *
     * GET /{catalog_id}
     */
    public function getDetail(MetaBusinessAccount $account, string $metaCatalogId): MetaCatalog
    {
        $client = $this->accountService->getApiClient($account);

        $response = $client->request(
            'GET',
            Endpoints::GET_CATALOG,
            Endpoints::catalog($metaCatalogId),
            ['fields' => 'id,name,vertical,country,currency,timezone_id,product_count,is_catalog_segment']
        );

        $modelClass = config('meta-catalog.models.meta_catalog', MetaCatalog::class);

        $catalog = $modelClass::updateOrCreate(
            ['meta_catalog_id' => $response['id']],
            [
                'meta_business_account_id' => $account->id,
                'name'                     => $response['name'] ?? null,
                'vertical'                 => $response['vertical'] ?? 'commerce',
                'country'                  => $response['country'] ?? null,
                'currency'                 => $response['currency'] ?? null,
                'timezone_id'              => $response['timezone_id'] ?? null,
                'product_count'            => $response['product_count'] ?? null,
                'is_catalog_segment'       => $response['is_catalog_segment'] ?? false,
            ]
        );

        return $catalog;
    }

    /**
     * Actualiza un catálogo en la Graph API y en la base de datos local.
     *
     * POST /{catalog_id}   (Meta API usa POST para updates)
     *
     * @param array $data  Campos editables: name, vertical, country, currency, timezone_id
     */
    public function update(MetaCatalog $catalog, array $data): MetaCatalog
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $client->request(
            'POST',
            Endpoints::UPDATE_CATALOG,
            Endpoints::catalog($catalog->meta_catalog_id),
            $data
        );

        $allowed = ['name', 'vertical', 'country', 'currency', 'timezone_id'];
        $catalog->update(array_intersect_key($data, array_flip($allowed)));

        return $catalog->fresh();
    }

    /**
     * Elimina un catálogo de la Graph API y soft-elimina el registro local.
     *
     * DELETE /{catalog_id}
     */
    public function delete(MetaCatalog $catalog): bool
    {
        $account = $catalog->account;
        $client  = $this->accountService->getApiClient($account);

        $client->request(
            'DELETE',
            Endpoints::DELETE_CATALOG,
            Endpoints::catalog($catalog->meta_catalog_id)
        );

        return (bool) $catalog->delete();
    }

    /**
     * Crea un catálogo asociado a una Facebook Page.
     *
     * POST /pages/{page_id}/owned_product_catalogs
     *
     * @param MetaBusinessAccount $account   Cuenta de negocio que realiza la request
     * @param string              $pageId    ID de la Facebook Page
     * @param array               $data      Campos: name (required), vertical (optional, default 'commerce'),
     *                                       business_id (optional), business_metadata (optional)
     * @return MetaCatalog
     */
    public function createForPage(MetaBusinessAccount $account, string $pageId, array $data): MetaCatalog
    {
        $client = $this->accountService->getApiClient($account);

        $payload = array_merge([
            'vertical' => 'commerce',
        ], $data);

        $response = $client->request(
            'POST',
            Endpoints::CREATE_PAGE_CATALOG,
            Endpoints::page($pageId),
            $payload
        );

        $modelClass = config('meta-catalog.models.meta_catalog', MetaCatalog::class);

        return $modelClass::create([
            'meta_business_account_id' => $account->id,
            'meta_catalog_id'          => $response['id'],
            'name'                     => $data['name'] ?? null,
            'vertical'                 => $data['vertical'] ?? 'commerce',
        ]);
    }
}
