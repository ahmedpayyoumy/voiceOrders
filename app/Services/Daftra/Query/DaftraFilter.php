<?php

namespace App\Services\Daftra\Query;

class DaftraFilter
{
    public function __construct(
        public readonly string $field,
        public readonly mixed $value,
        public readonly string $operator = '=',
    ) {}
}
