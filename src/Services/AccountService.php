<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\MetaCatalogManager\Enums\AccountStatus;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\ApiClient;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;

class AccountService
{
    public function __construct(
        protected ?CatalogService $catalogService = null
    ) {}

    public function setCatalogService(CatalogService $catalogService): void
    {
        $this->catalogService = $catalogService;
    }

    public function create(array $data): MetaBusinessAccount
    {
        $modelClass = config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class);

        $account = $modelClass::create($data);

        $logChannel = config('meta-catalog.logging.channel', 'stack');

        if (empty($data['name'])) {
            try {
                $this->syncBusinessInfo($account);
            } catch (\Exception $e) {
                Log::channel($logChannel)->warning('MetaCatalog: could not sync business info during account creation', [
                    'meta_business_id' => $account->meta_business_id,
                    'error'            => $e->getMessage(),
                ]);
            }
        }

        try {
            $this->syncCatalogsIfNeeded($account);
        } catch (\Exception $e) {
            Log::channel($logChannel)->warning('MetaCatalog: could not sync catalogs during account creation', [
                'meta_business_id' => $account->meta_business_id,
                'error'            => $e->getMessage(),
            ]);
        }

        return $account->fresh();
    }

    public function createFromEmbeddedSignup(array $data): MetaBusinessAccount
    {
        $modelClass = config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class);
        $logChannel = config('meta-catalog.logging.channel', 'stack');

        if (empty($data['code'])) {
            throw new \InvalidArgumentException('MetaCatalog: code is required for embedded signup. The frontend must provide a valid OAuth code.');
        }

        $accessToken = $this->exchangeCodeForToken($data['code']);

        if (empty($accessToken)) {
            throw new \RuntimeException('MetaCatalog: failed to exchange code for access token. The code may have expired (valid for 30s).');
        }

        $businessId = $data['business_id'] ?? $data['meta_business_id'] ?? '';

        if (empty($businessId)) {
            throw new \InvalidArgumentException('MetaCatalog: business_id is required for embedded signup.');
        }

        $account = $modelClass::create([
            'meta_business_id' => $businessId,
            'access_token'     => $accessToken,
        ]);

        try {
            $this->syncBusinessInfo($account);
        } catch (\Exception $e) {
            Log::channel($logChannel)->warning('MetaCatalog: could not sync business info during embedded signup', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
            ]);
        }

        try {
            $this->syncCatalogsIfNeeded($account);
        } catch (\Exception $e) {
            Log::channel($logChannel)->warning('MetaCatalog: could not sync catalogs during embedded signup', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
            ]);
        }

        return $account->fresh();
    }

    public function update(string $id, array $data): MetaBusinessAccount
    {
        $account = $this->findOrFail($id);
        $account->update($data);

        return $account->fresh();
    }

    public function delete(string $id): bool
    {
        $account = $this->findOrFail($id);

        return (bool) $account->delete();
    }

    public function find(string $id): ?MetaBusinessAccount
    {
        $modelClass = config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class);

        return $modelClass::find($id);
    }

    public function findByMetaBusinessId(string $metaBusinessId): ?MetaBusinessAccount
    {
        $modelClass = config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class);

        return $modelClass::where('meta_business_id', $metaBusinessId)->first();
    }

    public function all(): Collection
    {
        $modelClass = config('meta-catalog.models.meta_business_account', MetaBusinessAccount::class);

        return $modelClass::all();
    }

    public function getApiClient(MetaBusinessAccount $account): ApiClient
    {
        return new ApiClient(
            config('meta-catalog.api.base_url', 'https://graph.facebook.com'),
            config('meta-catalog.api.graph_version', 'v25.0'),
            $account->access_token,
            config('meta-catalog.api.timeout', 30)
        );
    }

    public function fetchBusinessInfo(MetaBusinessAccount $account): ?array
    {
        $client = $this->getApiClient($account);

        return $client->request(
            'GET',
            Endpoints::GET_BUSINESS,
            Endpoints::business($account->meta_business_id),
            query: ['fields' => 'name,id,vertical']
        );
    }

    public function syncBusinessInfo(MetaBusinessAccount $account): void
    {
        $info = $this->fetchBusinessInfo($account);

        if ($info) {
            $update = [];
            if (!empty($info['name'])) {
                $update['name'] = $info['name'];
            }
            if (!empty($update)) {
                $account->update($update);
            }
        }
    }

    public function getWhatsAppAccounts(string $businessId, string $accessToken): array
    {
        $client = new ApiClient(
            config('meta-catalog.api.base_url', 'https://graph.facebook.com'),
            config('meta-catalog.api.graph_version', 'v25.0'),
            $accessToken,
            config('meta-catalog.api.timeout', 30)
        );

        $response = $client->request(
            'GET',
            Endpoints::GET_CLIENT_WABAS,
            Endpoints::business($businessId)
        );

        return $response['data'] ?? [];
    }

    public function exchangeCodeForToken(string $code): string
    {
        $client = new ApiClient(
            config('meta-catalog.api.base_url', 'https://graph.facebook.com'),
            config('meta-catalog.api.graph_version', 'v25.0'),
            '',
            config('meta-catalog.api.timeout', 30)
        );

        $response = $client->request(
            'GET',
            Endpoints::OAUTH_ACCESS_TOKEN,
            [],
            query: [
                'client_id'     => config('meta-catalog.oauth.app_id'),
                'client_secret' => config('meta-catalog.oauth.app_secret'),
                'code'          => $code,
            ]
        );

        return $response['access_token'] ?? '';
    }

    public function markDisconnected(MetaBusinessAccount $account, ?string $reason = null): void
    {
        $account->update([
            'status'               => AccountStatus::DISCONNECTED,
            'disconnected_at'      => now(),
            'disconnection_reason' => $reason,
        ]);
    }

    public function markActive(MetaBusinessAccount $account): void
    {
        $account->update([
            'status'               => AccountStatus::ACTIVE,
            'disconnected_at'      => null,
            'disconnection_reason' => null,
        ]);
    }

    private function syncCatalogsIfNeeded(MetaBusinessAccount $account): void
    {
        if ($this->catalogService && $account->access_token) {
            $this->catalogService->syncFromApi($account);
        }
    }

    private function findOrFail(string $id): MetaBusinessAccount
    {
        $account = $this->find($id);

        if ($account === null) {
            throw new \RuntimeException("MetaBusinessAccount [{$id}] not found.");
        }

        return $account;
    }

    /**
     * Asigna el System User al catálogo para que tenga acceso de gestión.
     */
    public function assignSystemUserToCatalog(MetaBusinessAccount $account, string $catalogMetaId): void
    {
        $client = $this->getApiClient($account);
        $appId = config('meta-catalog.oauth.app_id');
        $appSecret = config('meta-catalog.oauth.app_secret');

        if (!$appId || !$appSecret || !$account->access_token) {
            return;
        }

        // Debug token to get system user ID
        $debugClient = new ApiClient(
            config('meta-catalog.api.base_url', 'https://graph.facebook.com'),
            config('meta-catalog.api.graph_version', 'v25.0'),
            "{$appId}|{$appSecret}",
            config('meta-catalog.api.timeout', 30)
        );

        $debugResp = $debugClient->request(
            'GET',
            Endpoints::DEBUG_TOKEN,
            [],
            null,
            ['input_token' => $account->access_token]
        );

        $systemUserId = $debugResp['data']['user_id'] ?? null;
        if (!$systemUserId) {
            return;
        }

        // Assign system user to catalog
        $client->request(
            'POST',
            Endpoints::ASSIGNED_USERS,
            Endpoints::catalog($catalogMetaId),
            null,
            [
                'user'     => $systemUserId,
                'tasks'    => json_encode(['MANAGE', 'ADVERTISE']),
                'business' => $account->meta_business_id,
            ]
        );

        Log::channel(config('meta-catalog.logging.channel', 'stack'))
            ->info('System user assigned to catalog', [
                'catalog_id'     => $catalogMetaId,
                'system_user_id' => $systemUserId,
            ]);
    }

    /**
     * Debug: inspecciona los permisos/scopes de un access token vía Meta API.
     */
    public function debugAccessToken(string $accessToken): array
    {
        $appId = config('meta-catalog.oauth.app_id');
        $appSecret = config('meta-catalog.oauth.app_secret');

        if (!$appId || !$appSecret) {
            return ['error' => 'App credentials not configured'];
        }

        $client = new ApiClient(
            config('meta-catalog.api.base_url', 'https://graph.facebook.com'),
            config('meta-catalog.api.graph_version', 'v25.0'),
            "{$appId}|{$appSecret}",
            config('meta-catalog.api.timeout', 30)
        );

        $response = $client->request(
            'GET',
            Endpoints::DEBUG_TOKEN,
            [],
            null,
            ['input_token' => $accessToken]
        );

        $data = $response['data'] ?? [];

        return [
            'is_valid'   => $data['is_valid'] ?? false,
            'app_id'     => $data['app_id'] ?? 'unknown',
            'type'       => $data['type'] ?? 'unknown',
            'scopes'     => $data['scopes'] ?? [],
            'expires_at' => isset($data['expires_at']) && $data['expires_at'] > 0
                ? date('Y-m-d H:i:s', $data['expires_at'])
                : 'never',
        ];
    }
}