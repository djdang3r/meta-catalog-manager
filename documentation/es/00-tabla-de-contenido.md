# Meta Catalog Manager — Documentación en Español

Paquete Laravel para integración con la Meta Marketing API (Graph API) para la gestión de catálogos de productos, feeds, inventario y publicidad dinámica.

## Tabla de Contenido

1. [Instalación](./01-instalacion.md)
   - Requisitos del sistema
   - Instalación via Composer
   - Wizard de instalación (`meta-catalog:install`)
   - Variables de entorno
   - Primera cuenta

2. [Configuración](./02-configuracion.md)
   - Archivo `config/meta-catalog.php`
   - Override de modelos
   - Canal de logging

3. [Cuentas de Negocio](./03-cuentas.md)
   - Multi-cuenta con `MetaBusinessAccount`
   - `AccountService` — métodos disponibles
   - Encriptación de credenciales

4. [Catálogos](./04-catalogos.md)
   - Verticales de catálogo
   - `CatalogService` — métodos disponibles
   - Sincronización con la API

5. [Productos](./05-productos.md)
   - Campos de producto
   - Categorías: Google y Facebook Product Category
   - Variantes y `item_group_id`
   - App Links / Deep Links
   - GTIN y MPN
   - Visibility: `published` vs `staging`
   - `ProductService` — métodos disponibles

6. [Inventario](./06-inventario.md)
   - Ciclo de vida del inventario
   - Estrategias de actualización
   - `quantity_to_sell_on_facebook`
   - Sobreventa y productos agotados

7. [Feeds](./07-feeds.md)
   - PRIMARY_FEED vs SUPPLEMENTARY_FEED
   - Replace schedule vs Update schedule
   - `FeedService` — métodos completos
   - Upload desde URL y desde archivo
   - Manejo de errores de upload
   - Autenticación HTTP/FTP básica

8. [Batch API](./08-batch-api.md)
   - Cuándo usar Batch vs Feed
   - `BatchService` — métodos disponibles
   - Actualización de inventario en tiempo real
   - Batch mixto

9. [Conjuntos de Productos](./09-conjuntos-de-productos.md)
   - Product Sets y targeting de ads
   - `ProductSetService` — métodos disponibles
   - Ejemplos de filtros

10. [Diagnósticos](./10-diagnosticos.md)
    - `DiagnosticsService` — métodos disponibles
    - `EventStatsService` — métodos disponibles
    - DA Checks para píxeles y apps
    - Interpretación de resultados

11. [Ofertas](./11-ofertas.md)
    - Tipos de oferta: SALE, AUTOMATIC_AT_CHECKOUT, BUYER_APPLIED
    - Tipos de descuento: FIXED_AMOUNT, PERCENTAGE
    - Shipping offers (envío gratuito)
    - Buy X Get Y
    - Coupon codes vs public_coupon_code
    - Productos elegibles: filtros, retailer IDs, product sets
    - `OfferService` — métodos disponibles
    - Limitaciones y reglas de stacking

12. [Diagnósticos Avanzados — Event Source Issues](./12-diagnostico-avanzado.md)
    - EVENT_SOURCE_ISSUES: todos los tipos
    - Estructura de respuesta de la API
    - `DiagnosticsService` métodos avanzados
    - Flujo de resolución por tipo de issue

13. [Microdatos](./13-microdatos.md)
    - OpenGraph tags
    - Schema.org microdata
    - JSON-LD (recomendado)
    - Cómo testear con la herramienta de Meta
    - Responsabilidades: paquete vs sitio web

14. [Calificaciones y Opiniones](./14-calificaciones-y-opiniones.md)
    - Países soportados: US, Taiwan, Korea
    - Crear feed con `createRatingsAndReviewsFeed()`
    - Subir CSV con `uploadRatingsAndReviews()`
    - Esquema del CSV completo
    - Requisitos de matching

15. [Feeds Genéricos](./15-feeds-genericos.md)
    - Generic Feed Files API vs Feed API estándar
    - Tipos: PROMOTIONS, SHIPPING_PROFILES, NAVIGATION_MENU, PRODUCT_RATINGS_AND_REVIEWS
    - `GenericFeedService` — métodos disponibles
    - Perfiles de envío: schema completo
    - Menú de navegación: estructura JSON
    - Manejo de errores de upload

16. [Merchant Settings](./16-merchant-settings.md)
    - Commerce Merchant Settings API
    - Cómo obtener el `commerce_merchant_settings_id`
    - `MerchantSettingsService` — métodos disponibles
    - Casos de uso: checkout, privacidad, Korea FTC

17. [Catálogos Localizados](./17-catalogos-localizados.md)
    - Feeds de idioma, país e idioma+país
    - `FeedOverrideType` enum
    - Campos localizables por tipo de artículo
    - Override Details API (`getOverrideDetails()`)
    - Orden de prioridad al mostrar datos localizados
    - Recomendaciones y mejores prácticas
