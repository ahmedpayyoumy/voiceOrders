<?php

namespace App\Services\Daftra;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DaftraClient
{
    private PendingRequest $http;

    public function __construct(
        public readonly string $domain,
        public readonly string $apiKey,
    ) {
        $baseUrl = rtrim("https://{$domain}.daftra.com/api2", '/');

        $this->http = Http::withHeaders([
            'APIKEY' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->baseUrl($baseUrl)->timeout(30);
    }

    public function get(string $path, array $query = []): DaftraResponse
    {
        $response = $this->http->get($path, $query);
        $this->logExchange('GET', $path, $query, $response);

        return $this->handleResponse($response);
    }

    public function post(string $path, array $data = []): DaftraResponse
    {
        $response = $this->http->post($path, $data);
        $this->logExchange('POST', $path, $data, $response);

        return $this->handleResponse($response);
    }

    public function put(string $path, array $data = []): DaftraResponse
    {
        $response = $this->http->put($path, $data);
        $this->logExchange('PUT', $path, $data, $response);

        return $this->handleResponse($response);
    }

    public function delete(string $path): DaftraResponse
    {
        $response = $this->http->delete($path);
        $this->logExchange('DELETE', $path, [], $response);

        return $this->handleResponse($response);
    }

    private function logExchange(string $method, string $path, array $data, Response $response): void
    {
        Log::channel('daftra')->info("{$method} {$path}", [
            'request' => $data,
            'response_status' => $response->status(),
            'response' => $response->json(),
        ]);
    }

    private function handleResponse(Response $response): DaftraResponse
    {
        $wrapped = new DaftraResponse($response);

        if ($response->failed()) {
            throw match ($response->status()) {
                401 => DaftraException::unauthorized($wrapped->raw),
                404 => DaftraException::notFound(),
                422 => DaftraException::validationError($wrapped->raw),
                default => new DaftraException(
                    ($wrapped->raw['message'] ?? 'Daftra API error').' | Response: '.json_encode($wrapped->raw),
                    $response->status(),
                    null,
                    $wrapped->raw,
                ),
            };
        }

        return $wrapped;
    }

    public function module(string $name): Resources\Resource
    {
        $name = Str::singular($name);
        $resourceClass = 'App\\Services\\Daftra\\Resources\\'.ucfirst($name).'Resource';

        if (! class_exists($resourceClass)) {
            throw new \InvalidArgumentException("Unknown Daftra module: {$name}");
        }

        return new $resourceClass($this);
    }

    public function __call(string $name, array $arguments): Resources\Resource
    {
        return $this->module($name);
    }
}
