<?php

namespace App\Services\Daftra\Data;

class TaxData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?float $rate = null,
        public readonly ?bool $is_active = null,
        public readonly ?int $type = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Tax';
    }
}
