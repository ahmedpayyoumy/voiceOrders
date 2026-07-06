<?php

namespace App\Services\Daftra\Resources;

class TaxResource extends Resource
{
    protected function path(): string
    {
        return '/taxes';
    }

    protected function entityKey(): string
    {
        return 'Tax';
    }
}
