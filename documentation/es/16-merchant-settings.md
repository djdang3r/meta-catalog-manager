# Commerce Merchant Settings

## ¿Qué es la Commerce Merchant Settings API?

Permite gestionar la configuración del shop de un commerce merchant en Meta. Controla el estado del merchant, la política de privacidad, la URL de checkout y configuraciones específicas por país.

---

## ¿Cómo obtener el `commerce_merchant_settings_id`?

El `commerce_merchant_settings_id` es el ID del objeto `CommerceMerchantSettings` en la Graph API. Se obtiene a través de:

### Opción 1: Desde Commerce Manager
```
Meta Business Manager → Commerce Manager → [tu shop] → Configuración → ID del merchant
```

### Opción 2: Via Graph API
```
GET /{business_id}/commerce_merchant_settings
```

Con curl:
```bash
curl "https://graph.facebook.com/v22.0/{BUSINESS_ID}/commerce_merchant_settings?access_token={TOKEN}"
```

---

## MerchantSettingsService — métodos disponibles

### `get()` — Obtener configuración actual

```php
$settings = MetaCatalog::merchantSettings()->get(
    $account,
    'MERCHANT-SETTINGS-ID-123'
);

// Con campos específicos
$settings = MetaCatalog::merchantSettings()->get(
    $account,
    'MERCHANT-SETTINGS-ID-123',
    ['id', 'display_name', 'merchant_status', 'contact_email']
);
```

Campos disponibles (por defecto se retornan todos):
- `id`
- `display_name` — nombre público del shop
- `merchant_status` — estado actual: `enabled`, `externally_disabled`, `suspended`
- `contact_email` — email de contacto del merchant
- `terms` — términos y condiciones del shop
- `shops_ads_setup` — configuración de anuncios

---

### `update()` — Actualizar configuración

```php
$result = MetaCatalog::merchantSettings()->update(
    $account,
    'MERCHANT-SETTINGS-ID-123',
    [
        'merchant_status'          => 'enabled',
        'privacy_policy_localized' => [
            'url'    => 'https://tienda.com/privacidad',
            'locale' => 'es_LA',
        ],
    ]
);
```

Campos actualizables:

| Campo | Tipo | Descripción |
|---|---|---|
| `merchant_status` | string | `'enabled'` o `'externally_disabled'` |
| `privacy_policy_localized` | array | `{url, locale}` — política de privacidad por idioma |
| `korea_ftc_listing` | string | URL con el listado de negocio requerido en Korea |
| `checkout_config` | array | `{checkout_url, country_code}` — configuración de checkout |

---

### `enable()` — Habilitar el merchant

```php
MetaCatalog::merchantSettings()->enable($account, 'MERCHANT-SETTINGS-ID-123');
```

Equivale a:
```php
update($account, $id, ['merchant_status' => 'enabled'])
```

---

### `disable()` — Deshabilitar externamente

```php
MetaCatalog::merchantSettings()->disable($account, 'MERCHANT-SETTINGS-ID-123');
```

Equivale a:
```php
update($account, $id, ['merchant_status' => 'externally_disabled'])
```

> **Nota**: `externally_disabled` indica que la deshabilitación fue iniciada por el vendedor (no por Meta). Esto es diferente a `suspended`, que Meta aplica por violaciones de políticas.

---

### `setCheckoutConfig()` — Configurar checkout

```php
MetaCatalog::merchantSettings()->setCheckoutConfig(
    $account,
    'MERCHANT-SETTINGS-ID-123',
    'https://tienda.com/checkout',
    'US'
);
```

Útil cuando tenés checkout externo (no nativo de Meta) y querés que los botones de "Comprar" en Instagram/Facebook redirijan a tu propio checkout.

---

## Casos de uso comunes

### Mantenimiento programado

```php
// Antes del mantenimiento
MetaCatalog::merchantSettings()->disable($account, $merchantId);

// ... mantenimiento ...

// Después del mantenimiento
MetaCatalog::merchantSettings()->enable($account, $merchantId);
```

### Configurar política de privacidad multi-idioma

```php
// Español para Latinoamérica
MetaCatalog::merchantSettings()->update($account, $merchantId, [
    'privacy_policy_localized' => [
        'url'    => 'https://tienda.com/es/privacidad',
        'locale' => 'es_LA',
    ],
]);

// Inglés
MetaCatalog::merchantSettings()->update($account, $merchantId, [
    'privacy_policy_localized' => [
        'url'    => 'https://tienda.com/en/privacy',
        'locale' => 'en_US',
    ],
]);
```

### Korea FTC Listing (obligatorio para ventas en Corea del Sur)

```php
MetaCatalog::merchantSettings()->update($account, $merchantId, [
    'korea_ftc_listing' => 'https://tienda.com/korea-ftc-disclosure',
]);
```

Requerido por la ley coreana de comercio electrónico (FTC — Fair Trade Commission) para todos los merchants que venden en Corea.

---

## Estados del merchant

| Estado | Descripción | Visible en Shop |
|---|---|---|
| `enabled` | Activo y funcionando | SI |
| `externally_disabled` | Deshabilitado por el vendedor | NO |
| `suspended` | Suspendido por Meta (violación de políticas) | NO |

Un merchant en estado `suspended` no puede ser habilitado via API. Requiere resolución del proceso de apelación en Meta Business Support.

---

## Obtener el estado del merchant (verificación de salud)

```php
$settings = MetaCatalog::merchantSettings()->get($account, $merchantId, ['merchant_status']);

if ($settings['merchant_status'] !== 'enabled') {
    logger()->alert('Merchant no disponible', [
        'status' => $settings['merchant_status'],
        'id'     => $merchantId,
    ]);
}
```
