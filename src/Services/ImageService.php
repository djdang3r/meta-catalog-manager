<?php

namespace ScriptDevelop\MetaCatalogManager\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ScriptDevelop\MetaCatalogManager\Models\MetaBusinessAccount;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalog;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogImage;
use ScriptDevelop\MetaCatalogManager\Models\MetaCatalogItem;

class ImageService
{
    /**
     * Descarga y persiste todas las imágenes de un producto (main + additional).
     * Usa updateOrCreate por original_url para evitar duplicados.
     *
     * @return int Cantidad de imágenes procesadas
     */
    public function downloadForItem(MetaCatalogItem $item): int
    {
        $count = 0;

        // Imagen principal
        if (!empty($item->image_url)) {
            $this->syncImage($item, $item->image_url, 'product_main', 0);
            $count++;
        }

        // Imágenes adicionales
        $additionals = $item->additional_image_urls ?? [];
        foreach ($additionals as $position => $url) {
            if (!empty($url)) {
                $this->syncImage($item, $url, 'product_additional', $position);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Descarga imágenes de todos los productos de un catálogo.
     *
     * @return int Total de imágenes procesadas
     */
    public function downloadForCatalog(MetaCatalog $catalog): int
    {
        $modelClass = config('meta-catalog.models.meta_catalog_item', MetaCatalogItem::class);
        $total = 0;

        $modelClass::where('meta_catalog_id', $catalog->id)
            ->whereNotNull('image_url')
            ->each(function ($item) use (&$total) {
                $total += $this->downloadForItem($item);
            });

        return $total;
    }

    /**
     * Descarga imágenes de todos los catálogos de una cuenta.
     *
     * @return int Total de imágenes procesadas
     */
    public function downloadForAccount(MetaBusinessAccount $account): int
    {
        $catalogModel = config('meta-catalog.models.meta_catalog', MetaCatalog::class);
        $total = 0;

        $catalogModel::where('meta_business_account_id', $account->id)
            ->each(function ($catalog) use (&$total) {
                $total += $this->downloadForCatalog($catalog);
            });

        return $total;
    }

    /**
     * Sincroniza una imagen individual: crea o actualiza el registro y descarga el archivo.
     * Si la imagen ya fue descargada y el original_url no cambió, no la vuelve a bajar.
     */
    public function syncImage(
        MetaCatalogItem $item,
        string $originalUrl,
        string $type,
        int $position = 0
    ): MetaCatalogImage {
        $modelClass = config('meta-catalog.models.meta_catalog_image', MetaCatalogImage::class);

        $image = $modelClass::firstOrNew([
            'meta_catalog_item_id' => $item->id,
            'type'                 => $type,
            'position'             => $position,
        ]);

        $urlChanged = $image->original_url !== $originalUrl;

        // Si la URL cambió o aún no fue descargada, actualizar y descargar
        if ($urlChanged || !$image->isDownloaded()) {
            $image->original_url = $originalUrl;
            $image->local_path   = null;
            $image->local_url    = null;
            $image->downloaded_at = null;
            $image->save();

            $this->download($image);
        }

        return $image->fresh();
    }

    /**
     * Descarga el archivo de una imagen y lo guarda en storage.
     * Reintentos configurables via meta-catalog.media.retries.
     */
    public function download(MetaCatalogImage $image): bool
    {
        $retries = config('meta-catalog.media.retries', 3);
        $disk    = config('meta-catalog.media.disk', 'public');
        $basePath = config(
            "meta-catalog.media.paths.{$image->type}",
            "meta-catalog/products/{$image->type}"
        );

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->get($image->original_url);

                if (!$response->successful()) {
                    continue;
                }

                $binary   = $response->body();
                $mimeType = $this->detectMimeType($binary, $image->original_url);
                $ext      = $this->mimeToExtension($mimeType);
                $fileName = Str::ulid() . '.' . $ext;
                $path     = $basePath . '/' . $fileName;

                Storage::disk($disk)->put($path, $binary);

                $image->update([
                    'local_path'    => $path,
                    'local_url'     => Storage::disk($disk)->url($path),
                    'mime_type'     => $mimeType,
                    'file_size'     => strlen($binary),
                    'downloaded_at' => now(),
                ]);

                return true;

            } catch (\Throwable $e) {
                if ($attempt === $retries) {
                    \Illuminate\Support\Facades\Log::channel(
                        config('meta-catalog.logging.channel', 'stack')
                    )->warning('MetaCatalog: no se pudo descargar imagen.', [
                        'url'     => $image->original_url,
                        'error'   => $e->getMessage(),
                        'attempt' => $attempt,
                    ]);
                }
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function detectMimeType(string $binary, string $url): string
    {
        // Intentar detectar por contenido binario
        if (function_exists('finfo_buffer')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_buffer($finfo, $binary);
            finfo_close($finfo);

            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        // Fallback: deducir de la extensión en la URL
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/jpeg',
        };
    }

    private function mimeToExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg'    => 'jpg',
            'image/png'     => 'png',
            'image/webp'    => 'webp',
            'image/gif'     => 'gif',
            'image/svg+xml' => 'svg',
            default         => 'jpg',
        };
    }
}
