<?php

namespace App\Services\Daftra\Data;

class ProductCategoryData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?int $parent_id = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'ProductCategory';
    }
}
