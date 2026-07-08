<?php

namespace App\Services\Daftra\Resources;

use App\Services\Daftra\DaftraResponse;

class SiteResource extends Resource
{
    protected function path(): string
    {
        return '/site';
    }

    public function entityKey(): string
    {
        return 'Site';
    }

    public function info(): DaftraResponse
    {
        return $this->client->get($this->path());
    }
}
