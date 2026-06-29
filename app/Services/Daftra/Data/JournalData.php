<?php

namespace App\Services\Daftra\Data;

class JournalData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $description = null,
        public readonly ?string $date = null,
        public readonly ?int $account_id = null,
        public readonly ?float $debit = null,
        public readonly ?float $credit = null,
        public readonly ?int $store_id = null,
        public readonly ?string $notes = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Journal';
    }
}
