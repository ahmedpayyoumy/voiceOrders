<?php

namespace App\Services\Daftra\Data;

class TreasuryData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?float $balance = null,
        public readonly ?string $type = null,
        public readonly ?bool $is_active = null,
        public readonly ?string $notes = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Treasury';
    }
}
