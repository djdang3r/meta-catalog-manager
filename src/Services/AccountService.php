<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use ScriptDevelop\MetaCatalogManager\Enums\AccountStatus;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\ApiClient;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;

class AccountService
{
    /**
     * Crea una nueva cuenta de negocio en la base de datos.
     */
    public function create(array $data): MetaBusinessAccount
    {
        $modelClass = config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class);

        return $modelClass::create($data);
    }

    /**
     * Actualiza una cuenta de negocio existente.
     */
    public function update(string $id, array $data): MetaBusinessAccount
    {
        $account = $this->findOrFail($id);
        $account->update($data);

        return $account->fresh();
    }

    /**
     * Soft-delete de una cuenta de negocio.
     */
    public function delete(string $id): bool
    {
        $account = $this->findOrFail($id);

        return (bool) $account->delete();
    }

    /**
     * Busca una cuenta por su ULID interno.
     */
    public function find(string $id): ?MetaBusinessAccount
    {
        $modelClass = config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class);

        return $modelClass::find($id);
    }

    /**
     * Busca una cuenta por el Meta Business Manager ID.
     */
    public function findByMetaBusinessId(string $metaBusinessId): ?MetaBusinessAccount
    {
        $modelClass = config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class);

        return $modelClass::where('meta_business_id', $metaBusinessId)->first();
    }

    /**
     * Retorna todas las cuentas (sin soft-deleted).
     */
    public function all(): Collection
    {
        $modelClass = config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class);

        return $modelClass::all();
    }

    /**
     * Crea un ApiClient configurado con el access_token desencriptado del account.
     */
    public function getApiClient(MetaBusinessAccount $account): ApiClient
    {
        return new ApiClient(
            config('meta-catalog.api.base_url', 'https://graph.facebook.com'),
            config('meta-catalog.api.graph_version', 'v22.0'),
            $account->access_token,
            config('meta-catalog.api.timeout', 30)
        );
    }

    /**
     * Marca la cuenta como desconectada.
     */
    public function markDisconnected(MetaBusinessAccount $account, ?string $reason = null): void
    {
        $account->update([
            'status'               => AccountStatus::DISCONNECTED,
            'disconnected_at'      => now(),
            'disconnection_reason' => $reason,
        ]);
    }

    /**
     * Marca la cuenta como activa (reconexión).
     */
    public function markActive(MetaBusinessAccount $account): void
    {
        $account->update([
            'status'               => AccountStatus::ACTIVE,
            'disconnected_at'      => null,
            'disconnection_reason' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Privados
    // -------------------------------------------------------------------------

    private function findOrFail(string $id): MetaBusinessAccount
    {
        $account = $this->find($id);

        if ($account === null) {
            throw new \RuntimeException("MetaBusinessAccount [{$id}] not found.");
        }

        return $account;
    }
}
