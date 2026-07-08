<?php

namespace App\Services\Daftra\Resources;

use App\Services\Daftra\DaftraClient;
use App\Services\Daftra\DaftraResponse;
use App\Services\Daftra\Data\Data;

abstract class Resource
{
    protected DaftraClient $client;

    abstract protected function path(): string;

    abstract public function entityKey(): string;

    public function __construct(DaftraClient $client)
    {
        $this->client = $client;
    }

    public function list(array $query = []): DaftraResponse
    {
        return $this->client->get($this->path(), $query);
    }

    public function get(int $id): DaftraResponse
    {
        return $this->client->get("{$this->path()}/{$id}");
    }

    public function create(Data $data): DaftraResponse
    {
        return $this->client->post($this->path(), $data->toRequestBody());
    }

    public function update(int $id, Data $data): DaftraResponse
    {
        return $this->client->put("{$this->path()}/{$id}", $data->toRequestBody());
    }

    public function delete(int $id): DaftraResponse
    {
        return $this->client->delete("{$this->path()}/{$id}");
    }
}
