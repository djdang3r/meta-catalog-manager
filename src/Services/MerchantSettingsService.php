<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Endpoints;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;

class MerchantSettingsService
{
    public function __construct(
        protected AccountService $accountService
    ) {}

    /**
     * Obtiene la configuración del commerce merchant.
     *
     * GET /{commerce_merchant_settings_id}
     *
     * @param MetaBusinessAccount $account
     * @param string $commerceMerchantSettingsId ID del merchant settings en Meta
     * @param array $fields Campos a retornar (vacío = campos por defecto de la API)
     * @return array Respuesta cruda de la API
     */
    public function get(MetaBusinessAccount $account, string $commerceMerchantSettingsId, array $fields = []): array
    {
        $client = $this->accountService->getApiClient($account);

        $params = [];
        if (!empty($fields)) {
            $params['fields'] = implode(',', $fields);
        } else {
            $params['fields'] = 'id,display_name,merchant_status,contact_email,terms,shops_ads_setup';
        }

        return $client->request(
            'GET',
            Endpoints::MERCHANT_SETTINGS,
            Endpoints::merchantSettings($commerceMerchantSettingsId),
            null,
            $params
        );
    }

    /**
     * Actualiza la configuración del commerce merchant.
     *
     * POST /{commerce_merchant_settings_id}
     *
     * $data puede incluir:
     * - merchant_status: 'enabled' | 'externally_disabled'
     * - privacy_policy_localized: ['url' => '...', 'locale' => '...']
     * - korea_ftc_listing: string URL
     * - checkout_config: ['checkout_url' => '...', 'country_code' => '...']
     *
     * @return array Respuesta cruda de la API
     */
    public function update(MetaBusinessAccount $account, string $commerceMerchantSettingsId, array $data): array
    {
        $client = $this->accountService->getApiClient($account);

        return $client->request(
            'POST',
            Endpoints::MERCHANT_SETTINGS,
            Endpoints::merchantSettings($commerceMerchantSettingsId),
            $data
        );
    }

    /**
     * Habilita el merchant (merchant_status = 'enabled').
     *
     * @return array Respuesta cruda de la API
     */
    public function enable(MetaBusinessAccount $account, string $commerceMerchantSettingsId): array
    {
        return $this->update($account, $commerceMerchantSettingsId, [
            'merchant_status' => 'enabled',
        ]);
    }

    /**
     * Deshabilita el merchant externamente (merchant_status = 'externally_disabled').
     *
     * @return array Respuesta cruda de la API
     */
    public function disable(MetaBusinessAccount $account, string $commerceMerchantSettingsId): array
    {
        return $this->update($account, $commerceMerchantSettingsId, [
            'merchant_status' => 'externally_disabled',
        ]);
    }

    /**
     * Configura la URL de checkout del merchant.
     *
     * @param MetaBusinessAccount $account
     * @param string $commerceMerchantSettingsId
     * @param string $checkoutUrl URL del checkout del vendedor
     * @param string $countryCode Código ISO del país (e.g. 'US', 'AR')
     * @return array Respuesta cruda de la API
     */
    public function setCheckoutConfig(
        MetaBusinessAccount $account,
        string $commerceMerchantSettingsId,
        string $checkoutUrl,
        string $countryCode
    ): array {
        return $this->update($account, $commerceMerchantSettingsId, [
            'checkout_config' => [
                'checkout_url' => $checkoutUrl,
                'country_code' => $countryCode,
            ],
        ]);
    }
}
