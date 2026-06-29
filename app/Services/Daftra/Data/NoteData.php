<?php

namespace App\Services\Daftra\Data;

class NoteData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $text = null,
        public readonly ?string $model = null,
        public readonly ?int $model_id = null,
        public readonly ?int $staff_id = null,
        public readonly ?string $created = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Note';
    }
}
