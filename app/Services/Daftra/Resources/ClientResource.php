<?php

namespace App\Services\Daftra\Resources;

class ClientResource extends Resource
{
    protected function path(): string
    {
        return '/clients';
    }

    public function entityKey(): string
    {
        return 'Client';
    }
}
