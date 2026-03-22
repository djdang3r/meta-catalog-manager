# Calificaciones y Opiniones (Product Ratings & Reviews)

Las calificaciones y opiniones permiten mostrar la puntuación de los productos directamente en los anuncios de Meta, aumentando la confianza del comprador y la tasa de conversión.

## Países soportados

Las opiniones de productos en Meta Shopping están disponibles en:
- **Estados Unidos (US)**
- **Taiwan (TW)**
- **Corea del Sur (KR)**

> Si tu tienda opera fuera de estos mercados, este feature no estará disponible para los compradores, pero igualmente podés subir el feed para cuando Meta lo habilite en más regiones.

---

## Prerrequisito: Shop debe haber sido visible

El shop del catálogo debe haber estado visible al público durante los **últimos 2 días consecutivos** antes de que Meta empiece a mostrar las opiniones. Si el shop estuvo offline o en modo staging, las opiniones no se muestran aunque el feed sea correcto.

---

## Paso 1: Crear el feed de calificaciones

Crear un feed de tipo `PRODUCT_RATINGS_AND_REVIEWS` en el catálogo:

```php
$feed = MetaCatalog::feed()->createRatingsAndReviewsFeed(
    $catalog,
    'Calificaciones y Opiniones — Tienda Principal'
);
```

Esto crea un registro en `meta_product_feeds` y en Meta un feed con `feed_type = PRODUCT_RATINGS_AND_REVIEWS`.

---

## Paso 2: Subir el CSV de calificaciones

```php
// Desde URL pública
MetaCatalog::feed()->uploadRatingsAndReviews($feed, 'https://tienda.com/feeds/reviews.csv');

// Desde archivo local
MetaCatalog::feed()->uploadRatingsAndReviews($feed, '/tmp/reviews.csv', isFile: true);
```

---

## Esquema del CSV

El archivo debe ser CSV con codificación UTF-8. Los campos requeridos están marcados con *.

```csv
aggregator.name*,store.name*,store.id*,review_id*,title,content*,review_url*,reviewer.name*,review_origin_source,rating.value*,rating.min*,rating.max*,product.name*,product.url*,product.retailer_id,product.gtin,product.mpn,product.sku,submission_time*
"Tienda XYZ","Tienda XYZ","STORE-001","REVIEW-001","Excelente calidad","Las zapatillas son muy cómodas y de buena calidad.","https://tienda.com/productos/zapatillas-pro-x1#review-001","María G.","organic",5,1,5,"Zapatillas Running Pro X1","https://tienda.com/productos/zapatillas-pro-x1","SKU-ZAP-001","","","","2024-11-15T10:30:00Z"
```

### Detalle de campos

| Campo | Requerido | Descripción |
|---|---|---|
| `aggregator.name` | SI | Nombre de quien agregó la opinión (tu tienda) |
| `store.name` | SI | Nombre de la tienda |
| `store.id` | SI | ID único de la tienda |
| `review_id` | SI | ID único de la opinión (en tu sistema) |
| `title` | NO | Título de la opinión |
| `content` | SI | Texto de la opinión |
| `review_url` | SI | URL donde está publicada la opinión |
| `reviewer.name` | SI | Nombre del revisor (puede ser anonimizado: "Comprador verificado") |
| `review_origin_source` | NO | `organic` (espontáneo) o `incentivized` (incentivado) |
| `rating.value` | SI | Puntuación numérica del revisor |
| `rating.min` | SI | Valor mínimo de la escala (ej: 1) |
| `rating.max` | SI | Valor máximo de la escala (ej: 5) |
| `product.name` | SI | Nombre del producto |
| `product.url` | SI | URL de la página del producto |
| `product.retailer_id` | NO* | `retailer_id` del catálogo |
| `product.gtin` | NO* | GTIN del producto |
| `product.mpn` | NO* | MPN del producto |
| `product.sku` | NO* | SKU del producto |
| `submission_time` | SI | Fecha/hora en ISO 8601 (ej: `2024-11-15T10:30:00Z`) |

> * Al menos uno de `retailer_id`, `gtin`, `mpn` o `sku` debe estar presente para hacer el matching.

---

## Requisitos de matching (cómo Meta asocia la opinión con el producto)

Meta intenta hacer match de la opinión con el producto del catálogo usando (en orden de preferencia):

1. **`product.retailer_id`** → busca en `retailer_id` del catálogo
2. **`product.gtin`** → busca en el campo `gtin` del catálogo
3. **`product.mpn`** → busca en el campo `mpn` del catálogo
4. **`product.sku`** → como fallback
5. **`product.name` + `product.url`** → matching por nombre/URL (menos preciso)

La práctica recomendada es siempre incluir `retailer_id` para un matching preciso.

---

## Generar el CSV desde la base de datos (ejemplo)

```php
use League\Csv\Writer;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogItem;

// Obtener reviews de tu sistema
$reviews = Review::with('product')->where('catalog_id', $catalog->id)->get();

$csv = Writer::createFromString();
$csv->insertOne([
    'aggregator.name', 'store.name', 'store.id', 'review_id',
    'title', 'content', 'review_url', 'reviewer.name',
    'review_origin_source', 'rating.value', 'rating.min', 'rating.max',
    'product.name', 'product.url', 'product.retailer_id',
    'product.gtin', 'product.mpn', 'product.sku', 'submission_time',
]);

foreach ($reviews as $review) {
    $csv->insertOne([
        'Mi Tienda',
        'Mi Tienda',
        'MY-STORE-001',
        $review->id,
        $review->title ?? '',
        $review->body,
        url("/productos/{$review->product->slug}#review-{$review->id}"),
        $review->reviewer_name ?? 'Comprador verificado',
        'organic',
        $review->rating,
        1,
        5,
        $review->product->name,
        url("/productos/{$review->product->slug}"),
        $review->product->sku,
        $review->product->gtin ?? '',
        $review->product->mpn ?? '',
        $review->product->sku,
        $review->created_at->toIso8601String(),
    ]);
}

// Guardar temporalmente y subir
$path = storage_path("app/feeds/reviews-{$catalog->id}.csv");
file_put_contents($path, $csv->toString());

// Subir al feed
$feed = MetaCatalog::feed()->createRatingsAndReviewsFeed($catalog, 'Reviews Feed');
MetaCatalog::feed()->uploadRatingsAndReviews($feed, $path, isFile: true);
```

---

## Frecuencia de actualización

Meta recomienda actualizar el feed de opiniones **al menos una vez por semana**. Cada upload reemplaza el contenido anterior del feed.

```php
// Ejemplo con job programado
class UploadReviewsFeed extends Command
{
    protected $signature = 'meta:upload-reviews {catalogId}';

    public function handle()
    {
        $catalog = MetaCatalog::catalog()->find($this->argument('catalogId'));
        $feed    = MetaProductFeed::where('meta_catalog_id', $catalog->id)
            ->where('feed_type', 'PRODUCT_RATINGS_AND_REVIEWS')
            ->first();

        if (!$feed) {
            $feed = MetaCatalog::feed()->createRatingsAndReviewsFeed($catalog, 'Reviews');
        }

        // Generar CSV...
        $path = $this->generateCsv($catalog);

        MetaCatalog::feed()->uploadRatingsAndReviews($feed, $path, isFile: true);
        $this->info("Reviews subidas correctamente.");
    }
}
```
