<?php

namespace App\Services\Daftra\Data;

class RequisitionItemData extends Data
{
    public function __construct(
        public readonly ?int $product_id = null,
        public readonly ?float $quantity = null,
        public readonly ?float $unit_price = null,
        public readonly ?int $cost_center_id = null,
        public readonly ?string $lot = null,
        public readonly ?string $expiry_date = null,
        public readonly ?array $serials = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'RequisitionItem';
    }
}
