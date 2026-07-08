<?php

namespace App\Services\Daftra\Data;

class ProductData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $sku = null,
        public readonly ?string $description = null,
        public readonly ?float $unit_price = null,
        public readonly ?float $cost = null,
        public readonly ?int $type = null,
        public readonly ?int $category_id = null,
        public readonly ?int $store_id = null,
        public readonly ?string $unit = null,
        public readonly ?int $tax1 = null,
        public readonly ?int $tax2 = null,
        public readonly ?bool $is_active = null,
        public readonly ?int $follow_up_status = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Product';
    }
}
