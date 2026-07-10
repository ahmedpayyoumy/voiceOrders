<?php

namespace App\Services\Daftra;

use Illuminate\Http\Client\Response;

class DaftraResponse
{
    public readonly int $code;

    public readonly string $result;

    public readonly ?int $id;

    public readonly ?array $data;

    public readonly ?array $pagination;

    public readonly array $raw;

    public function __construct(Response $response)
    {
        $this->raw = $response->json() ?? [];
        $this->code = $this->raw['code'] ?? $response->status();
        $this->result = $this->raw['result'] ?? 'unknown';
        $this->id = $this->raw['id'] ?? null;
        $this->data = $this->raw['data'] ?? null;
        $this->pagination = $this->raw['pagination'] ?? null;
    }

    public function successful(): bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    public function entity(string $key): ?array
    {
        return $this->data[$key] ?? null;
    }

    public function entityList(string $key): array
    {
        $list = $this->data ?? [];

        if (empty($list)) {
            return [];
        }

        $first = $list[0] ?? [];

        if (is_array($first) && isset($first[$key])) {
            return array_map(fn ($item) => $item[$key], $list);
        }

        if (is_array($first)) {
            $actualKey = array_key_first($first);
            if ($actualKey !== null && is_array($first[$actualKey])) {
                return array_map(fn ($item) => $item[$actualKey] ?? $item, $list);
            }
        }

        return $list;
    }
}
