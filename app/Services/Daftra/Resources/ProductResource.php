<?php

namespace App\Services\Daftra\Resources;

class ProductResource extends Resource
{
    protected function path(): string
    {
        return '/products';
    }

    public function entityKey(): string
    {
        return 'Product';
    }
}
