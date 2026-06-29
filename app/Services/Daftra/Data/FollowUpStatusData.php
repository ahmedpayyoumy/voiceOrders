<?php

namespace App\Services\Daftra\Data;

class FollowUpStatusData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $model = null,
        public readonly ?string $color = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'FollowUpStatus';
    }
}
