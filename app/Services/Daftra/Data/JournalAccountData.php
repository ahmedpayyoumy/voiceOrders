<?php

namespace App\Services\Daftra\Data;

class JournalAccountData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $code = null,
        public readonly ?int $category_id = null,
        public readonly ?string $type = null,
        public readonly ?bool $is_active = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'JournalAccount';
    }
}
