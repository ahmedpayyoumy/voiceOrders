<?php

namespace App\Services\Daftra\Data;

class IncomeData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?float $amount = null,
        public readonly ?string $date = null,
        public readonly ?int $category_id = null,
        public readonly ?int $store_id = null,
        public readonly ?int $client_id = null,
        public readonly ?string $notes = null,
        public readonly ?string $payment_method = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Income';
    }
}
