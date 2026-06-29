<?php

namespace App\Services\Daftra\Data;

class JournalCatData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $type = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'JournalCat';
    }
}
