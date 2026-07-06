<?php

namespace App\Services\Daftra\Resources;

class ProductCategoryResource extends Resource
{
    protected function path(): string
    {
        return '/product_categories';
    }

    protected function entityKey(): string
    {
        return 'ProductCategory';
    }
}
