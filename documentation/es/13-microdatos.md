# Microdatos para Meta Catalog

## ¿Qué son los microdatos y para qué sirven?

Los microdatos son marcado semántico en el HTML de tu sitio web que le dice a Meta (y otros motores de scraping) qué significan los datos de la página. Cuando Meta crawlea tu sitio para el catálogo, usa estos datos para:

1. **Verificar que el producto existe** y está disponible
2. **Asociar las páginas con los ítems del catálogo** via `content_id`/`retailer_id`
3. **Actualizar precios y disponibilidad** sin necesidad de re-subir el feed

> **IMPORTANTE**: Los microdatos son **client-side (HTML)**. Este paquete Laravel **NO los genera** — son responsabilidad del sitio web del vendedor. El paquete solo gestiona la comunicación con la API de Meta.

---

## Formato 1: OpenGraph Tags

El más simple. Meta lee estas etiquetas `<meta>` del `<head>` de tu página.

```html
<head>
    <!-- Básico -->
    <meta property="og:title" content="Zapatillas Running Pro X1" />
    <meta property="og:description" content="Zapatillas de running con tecnología de amortiguación avanzada." />
    <meta property="og:url" content="https://tienda.com/productos/zapatillas-running-pro-x1" />
    <meta property="og:image" content="https://tienda.com/images/zapatillas-pro-x1.jpg" />
    <meta property="og:type" content="product" />

    <!-- Producto específico -->
    <meta property="product:retailer_item_id" content="SKU-ZAP-001" />
    <meta property="product:price:amount" content="89.99" />
    <meta property="product:price:currency" content="USD" />
    <meta property="product:availability" content="in stock" />
    <meta property="product:condition" content="new" />
    <meta property="product:brand" content="RunTech" />
    <meta property="product:category" content="Ropa y accesorios > Calzado > Zapatillas" />
</head>
```

### Valores válidos para `product:availability`

| Valor | Descripción |
|---|---|
| `in stock` | Disponible |
| `out of stock` | Sin stock |
| `preorder` | Preventa |
| `available for order` | Disponible para pedido |
| `discontinued` | Descontinuado |

---

## Formato 2: Schema.org (Microdata HTML)

Más verboso pero muy reconocido. Se agrega directamente en el HTML del producto:

```html
<div itemscope itemtype="https://schema.org/Product">
    <h1 itemprop="name">Zapatillas Running Pro X1</h1>
    <img itemprop="image" src="https://tienda.com/images/zapatillas-pro-x1.jpg" />
    <p itemprop="description">Zapatillas de running con tecnología de amortiguación avanzada.</p>

    <span itemprop="brand" itemscope itemtype="https://schema.org/Brand">
        <span itemprop="name">RunTech</span>
    </span>

    <div itemprop="offers" itemscope itemtype="https://schema.org/Offer">
        <span itemprop="priceCurrency" content="USD">$</span>
        <span itemprop="price" content="89.99">89.99</span>
        <link itemprop="availability" href="https://schema.org/InStock" />
        <link itemprop="itemCondition" href="https://schema.org/NewCondition" />
    </div>

    <!-- ID del producto para Meta -->
    <meta itemprop="productID" content="SKU-ZAP-001" />
    <meta itemprop="sku" content="SKU-ZAP-001" />
    <meta itemprop="gtin13" content="0123456789012" />
</div>
```

---

## Formato 3: JSON-LD (recomendado)

El más moderno y el que Google/Meta prefieren. Va en un `<script>` en el `<head>` o al final del `<body>`. No contamina el HTML visual.

```html
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "Zapatillas Running Pro X1",
    "description": "Zapatillas de running con tecnología de amortiguación avanzada.",
    "sku": "SKU-ZAP-001",
    "productID": "SKU-ZAP-001",
    "gtin13": "0123456789012",
    "brand": {
        "@type": "Brand",
        "name": "RunTech"
    },
    "image": [
        "https://tienda.com/images/zapatillas-pro-x1.jpg",
        "https://tienda.com/images/zapatillas-pro-x1-lateral.jpg"
    ],
    "offers": {
        "@type": "Offer",
        "url": "https://tienda.com/productos/zapatillas-running-pro-x1",
        "priceCurrency": "USD",
        "price": "89.99",
        "priceValidUntil": "2025-12-31",
        "availability": "https://schema.org/InStock",
        "itemCondition": "https://schema.org/NewCondition"
    },
    "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.5",
        "reviewCount": "127"
    }
}
</script>
```

### JSON-LD para variantes

Si tenés una página de grupo de variantes, incluí `variesBy` e `hasVariant`:

```html
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "ProductGroup",
    "name": "Zapatillas Running Pro X1",
    "productGroupID": "GROUP-ZAP-PRO-X1",
    "variesBy": ["https://schema.org/size", "https://schema.org/color"],
    "hasVariant": [
        {
            "@type": "Product",
            "name": "Zapatillas Running Pro X1 - Talle 42 - Negro",
            "sku": "SKU-ZAP-001-42-NEG",
            "offers": {
                "@type": "Offer",
                "price": "89.99",
                "priceCurrency": "USD",
                "availability": "https://schema.org/InStock"
            }
        },
        {
            "@type": "Product",
            "name": "Zapatillas Running Pro X1 - Talle 43 - Negro",
            "sku": "SKU-ZAP-001-43-NEG",
            "offers": {
                "@type": "Offer",
                "price": "89.99",
                "priceCurrency": "USD",
                "availability": "https://schema.org/OutOfStock"
            }
        }
    ]
}
</script>
```

---

## Cómo testear con la herramienta de Meta

1. Ir a: **https://developers.facebook.com/tools/debug/**
2. Pegar la URL del producto
3. Click en "Obtener nueva información"
4. Verificar que los campos de producto se detecten correctamente

### Herramientas adicionales

- **Rich Results Test de Google**: https://search.google.com/test/rich-results
  Valida JSON-LD / Schema.org (Meta también lee estos formatos)

- **Facebook Sharing Debugger**: https://developers.facebook.com/tools/debug/
  Para verificar OpenGraph tags

- **Schema.org Validator**: https://validator.schema.org/

---

## Relación con el Píxel de Meta

El microdato en la página web trabaja en conjunto con el Píxel. Cuando el usuario visita la página:

1. El **Píxel** dispara `ViewContent` con `content_ids: ['SKU-ZAP-001']`
2. Meta busca ese `content_id` en el catálogo
3. El **microdato** confirma el precio y disponibilidad actual

Si el `sku`/`productID` del microdato no coincide con el `retailer_id` del catálogo, vas a ver el error `INVALID_CONTENT_ID` en los diagnósticos (ver [12-diagnostico-avanzado.md](./12-diagnostico-avanzado.md)).

---

## Responsabilidades

| Tarea | Responsable |
|---|---|
| Implementar microdatos en páginas de producto | Sitio web del vendedor |
| Instalar y configurar el Píxel de Meta | Sitio web del vendedor |
| Subir y sincronizar el catálogo | Este paquete Laravel |
| Verificar que `retailer_id` coincida con `content_id` del píxel | Equipo de integración |
| Diagnosticar issues de event source | Este paquete (DiagnosticsService) |
