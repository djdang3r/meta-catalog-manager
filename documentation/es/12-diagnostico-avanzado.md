# Diagnósticos Avanzados — Event Source Issues

Este documento amplía el capítulo [10-diagnosticos.md](./10-diagnosticos.md) con el diagnóstico específico de fuentes de eventos (píxeles, apps).

## EVENT_SOURCE_ISSUES

Los Event Source Issues ocurren cuando hay problemas entre el catálogo y las fuentes de eventos (Píxel, App Events) que lo alimentan con datos de comportamiento del usuario. Estos errores afectan directamente la capacidad de hacer **retargeting dinámico** y **Dynamic Ads**.

---

## Tipos de issue (`EventSourceIssueType`)

### `APP_HAS_NO_AEM_SETUP`

**Severity**: MUST_FIX

La app vinculada al catálogo no tiene configurado Aggregated Event Measurement (AEM). Requerido desde iOS 14.

**Cómo resolver**:
1. Ir a Events Manager en Meta Business
2. Configurar AEM para la app móvil
3. Verificar que los eventos estén priorizados correctamente

---

### `CATALOG_NOT_CONNECTED_TO_EVENT_SOURCE`

**Severity**: MUST_FIX

El catálogo no tiene ninguna fuente de eventos (Píxel o App) conectada.

**Cómo resolver**:
```
Meta Business Manager → Catálogos → [tu catálogo] → Event Sources → Agregar Píxel / App
```

---

### `DELETED_ITEM`

**Severity**: WARNING

Los eventos del píxel/app referencian productos (content_ids) que ya fueron eliminados del catálogo.

**Cómo resolver**:
- Revisar si el producto fue eliminado intencionalmente
- Si fue eliminado por error, restaurarlo desde la DB con `restore()` (soft delete)
- Actualizar el píxel para no seguir enviando eventos de ese producto

---

### `INVALID_CONTENT_ID`

**Severity**: MUST_FIX

Los eventos envían `content_ids` que no corresponden a ningún `retailer_id` en el catálogo. Causa típica: el sitio envía IDs de base de datos interna en lugar del `retailer_id` del feed.

**Cómo resolver**:
1. Verificar qué `content_id` envía el píxel (ver Events Manager → Actividad del Píxel)
2. Comparar contra `retailer_id` en el catálogo
3. Corregir el píxel para enviar el valor correcto

---

### `MISSING_EVENT`

**Severity**: WARNING

El catálogo tiene productos pero no recibe eventos de `ViewContent`, `AddToCart` o `Purchase`. Afecta la calidad de los Dynamic Ads.

**Cómo resolver**:
1. Verificar que el píxel esté instalado correctamente en las páginas de producto
2. Verificar que se disparen los eventos con el parámetro `content_ids`
3. Usar el Meta Pixel Helper para debug

---

### `NO_CONTENT_ID`

**Severity**: MUST_FIX

Los eventos se disparan pero sin el campo `content_ids`. El píxel no puede asociar las acciones del usuario con productos del catálogo.

**Cómo resolver**:
```javascript
// Ejemplo correcto de evento con content_id
fbq('track', 'ViewContent', {
    content_ids: ['SKU-001'],   // DEBE estar presente
    content_type: 'product',
    value: 29.99,
    currency: 'USD'
});
```

---

## Estructura de respuesta de la API

```json
{
    "data": [
        {
            "type": "EVENT_SOURCE_ISSUES",
            "severity": "MUST_FIX",
            "title": "Invalid Content IDs",
            "subtitle": "Some content IDs from your event source don't match products in your catalog",
            "error_code": 1815203,
            "number_of_affected_items": 142,
            "diagnostics": [
                {
                    "affected_channels": ["catalog_ads"],
                    "issue_type": "INVALID_CONTENT_ID",
                    "samples": [
                        {
                            "retailer_id": "ID-123-NOTFOUND",
                            "type": "product"
                        }
                    ]
                }
            ]
        }
    ]
}
```

---

## Severity: MUST_FIX vs WARNING

| Severity | Impacto | Acción requerida |
|---|---|---|
| `MUST_FIX` | Dynamic Ads **no funcionan** para los productos afectados | Corregir antes de lanzar campañas |
| `WARNING` | Dynamic Ads funcionan pero con **menor efectividad** | Corregir para mejorar performance |

---

## DiagnosticsService — métodos nuevos

### `getEventSourceIssues()`

Obtiene los issues directamente desde la API:

```php
$issues = MetaCatalog::diagnostics()->getEventSourceIssues($catalog);

foreach ($issues as $issue) {
    echo $issue['type'];       // 'EVENT_SOURCE_ISSUES'
    echo $issue['severity'];   // 'MUST_FIX' o 'WARNING'
    echo $issue['title'];
    echo $issue['number_of_affected_items'];
}
```

### `hasCriticalEventSourceIssues()`

Verificación rápida antes de lanzar campañas:

```php
if (MetaCatalog::diagnostics()->hasCriticalEventSourceIssues($catalog)) {
    // Bloquear creación de campañas hasta resolver
    throw new \RuntimeException('El catálogo tiene issues críticos de event source.');
}
```

### `getEventSourceIssuesByType()`

Filtrar por tipo específico:

```php
use ScriptDevelop\MetaCatalogManager\Enums\EventSourceIssueType;

$invalidIds = MetaCatalog::diagnostics()->getEventSourceIssuesByType(
    $catalog,
    EventSourceIssueType::INVALID_CONTENT_ID->value
);
```

### `syncEventSourceIssues()`

Guarda los issues en la tabla `meta_catalog_diagnostics` para consultas locales:

```php
$diagnostics = MetaCatalog::diagnostics()->syncEventSourceIssues($catalog);

// Ahora podés consultar localmente sin llamar a la API
$criticals = MetaCatalogDiagnostic::where('meta_catalog_id', $catalog->id)
    ->where('severity', 'error')
    ->get();
```

---

## Flujo de trabajo recomendado

```php
// 1. Sincronizar issues a la DB (cron job diario)
MetaCatalog::diagnostics()->syncEventSourceIssues($catalog);

// 2. Verificar si hay críticos antes de operaciones importantes
if (MetaCatalog::diagnostics()->hasCriticalEventSourceIssues($catalog)) {
    // Notificar al equipo
    Notification::send($admins, new CriticalCatalogIssue($catalog));
    return;
}

// 3. Para debugging detallado, obtener issues por tipo
$missingEvents = MetaCatalog::diagnostics()->getEventSourceIssuesByType(
    $catalog,
    EventSourceIssueType::MISSING_EVENT->value
);

foreach ($missingEvents as $issue) {
    // Ver muestras de productos afectados
    foreach ($issue['diagnostics'] ?? [] as $detail) {
        foreach ($detail['samples'] ?? [] as $sample) {
            logger()->warning('Producto sin eventos', ['retailer_id' => $sample['retailer_id']]);
        }
    }
}
```
