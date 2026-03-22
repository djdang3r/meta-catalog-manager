<?php

namespace ScriptDevelop\MetaCatalogManager\MetaCatalogApi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use ScriptDevelop\MetaCatalogManager\MetaCatalogApi\Exceptions\ApiException;

/**
 * ApiClient para interactuar con la Meta Graph API (Catalog endpoints).
 * El access_token se pasa como query param ?access_token=TOKEN en cada request,
 * que es el mecanismo estándar de la Graph API de Meta.
 */
class ApiClient
{
    protected Client $client;
    protected string $baseUrl;
    protected string $version;
    protected string $accessToken;

    /**
     * @param string $baseUrl    URL base de la API (ej: https://graph.facebook.com)
     * @param string $version    Versión del API (ej: v22.0)
     * @param string $accessToken Token de acceso del System User de Meta
     * @param int    $timeout    Tiempo de espera en segundos
     */
    public function __construct(
        string $baseUrl,
        string $version,
        string $accessToken,
        int $timeout = 30
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->version = $version;
        $this->accessToken = $accessToken;

        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout'  => $timeout,
        ]);
    }

    /**
     * Ejecuta una petición genérica a la API.
     *
     * @param string     $method   Método HTTP (GET, POST, DELETE, etc.)
     * @param string     $endpoint Endpoint con placeholders (ej: '{catalog_id}/products')
     * @param array      $params   Valores para reemplazar los placeholders del endpoint
     * @param mixed      $data     Datos del body (array para JSON, resource para stream)
     * @param array      $query    Query params adicionales (access_token se agrega automáticamente)
     * @param array      $headers  Headers HTTP adicionales
     * @return mixed     Array decodificado de la respuesta JSON
     * @throws ApiException
     */
    public function request(
        string $method,
        string $endpoint,
        array $params = [],
        mixed $data = null,
        array $query = [],
        array $headers = []
    ): mixed {
        // Agregar access_token automáticamente a todos los requests
        $query['access_token'] = $this->accessToken;

        try {
            $url = $this->buildUrl($endpoint, $params, $query);

            $options = [
                'headers' => array_merge([
                    'Accept' => 'application/json',
                ], $headers),
            ];

            if (isset($data['multipart'])) {
                $options['multipart'] = $data['multipart'];
            } elseif (is_resource($data)) {
                $options['body'] = $data;
            } elseif (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->client->request($method, $url, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                Log::channel(config('meta-catalog.logging.channel', 'meta-catalog'))
                    ->info('Respuesta exitosa de la Meta Graph API.', [
                        'url'         => $url,
                        'status_code' => $statusCode,
                    ]);

                return json_decode($response->getBody(), true) ?: [];
            }

            Log::channel(config('meta-catalog.logging.channel', 'meta-catalog'))
                ->warning('Respuesta no exitosa de la Meta Graph API.', [
                    'status_code'   => $statusCode,
                    'response_body' => $response->getBody()->getContents(),
                ]);

            throw new ApiException('Respuesta no exitosa de la API.', $statusCode);

        } catch (GuzzleException $e) {
            Log::channel(config('meta-catalog.logging.channel', 'meta-catalog'))
                ->error('Meta Graph API Error', [
                    'url'   => $url ?? $endpoint,
                    'error' => $e->getMessage(),
                ]);

            throw $this->handleException($e);
        }
    }

    /**
     * Construye la URL final reemplazando placeholders e inyectando query params.
     *
     * @param string $endpoint Endpoint con placeholders como {catalog_id}
     * @param array  $params   Mapa placeholder → valor
     * @param array  $query    Query params (incluye access_token)
     * @return string URL construida
     */
    public function buildUrl(string $endpoint, array $params, array $query = []): string
    {
        $url = str_replace(
            array_map(fn($k) => '{' . $k . '}', array_keys($params)),
            array_values($params),
            $this->version . '/' . $endpoint
        );

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        Log::channel(config('meta-catalog.logging.channel', 'meta-catalog'))
            ->info('Meta Catalog URL construida:', ['url' => $url]);

        return $url;
    }

    /**
     * Convierte una GuzzleException en ApiException extrayendo el error de la Graph API.
     */
    public function handleException(GuzzleException $e): ApiException
    {
        $statusCode = 500;
        $body = [];
        $message = $e->getMessage();

        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $response   = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body       = json_decode($response->getBody(), true) ?? [];
            $message    = $body['error']['message'] ?? $message;

            Log::channel(config('meta-catalog.logging.channel', 'meta-catalog'))
                ->error('Error en la respuesta de la Meta Graph API.', [
                    'status_code'   => $statusCode,
                    'response_body' => $body,
                    'headers'       => $response->getHeaders(),
                ]);
        }

        return new ApiException($message, $statusCode, $body);
    }
}
