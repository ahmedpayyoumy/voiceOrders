<?php

namespace App\Services\Daftra\Data;

class StockTransactionData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $product_id = null,
        public readonly ?int $store_id = null,
        public readonly ?int $quantity = null,
        public readonly ?string $type = null,
        public readonly ?string $date = null,
        public readonly ?string $notes = null,
        public readonly ?float $unit_price = null,
        public readonly ?int $reference_id = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'StockTransaction';
    }
}
