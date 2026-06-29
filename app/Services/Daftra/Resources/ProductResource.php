<?php

namespace App\Services\Daftra\Resources;

class ProductResource extends Resource
{
    protected function path(): string { return '/products'; }
    protected function entityKey(): string { return 'Product'; }
}
