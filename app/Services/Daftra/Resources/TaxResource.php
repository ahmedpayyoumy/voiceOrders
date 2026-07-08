<?php

namespace App\Services\Daftra\Resources;

class TaxResource extends Resource
{
    protected function path(): string
    {
        return '/taxes';
    }

    public function entityKey(): string
    {
        return 'Tax';
    }
}
