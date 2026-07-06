<?php

namespace App\Services\Daftra\Interpreter;

use App\Services\Daftra\Query\DaftraQuery;

class Intent
{
    public function __construct(
        public readonly IntentType $type,
        public readonly string $module,
        public readonly array $filters = [],
        public readonly ?array $data = null,
        public readonly ?string $originalQuery = null,
        public readonly bool $isComplete = true,
        public readonly ?string $clarification = null,
    ) {}

    public function isList(): bool
    {
        return $this->type === IntentType::List;
    }

    public function isGet(): bool
    {
        return $this->type === IntentType::Get;
    }

    public function isCreate(): bool
    {
        return $this->type === IntentType::Create;
    }

    public function isUpdate(): bool
    {
        return $this->type === IntentType::Update;
    }

    public function isDelete(): bool
    {
        return $this->type === IntentType::Delete;
    }

    public function toQuery(): DaftraQuery
    {
        $query = new DaftraQuery;

        foreach ($this->filters as $field => $value) {
            $query->where($field, $value);
        }

        return $query;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'module' => $this->module,
            'filters' => $this->filters,
            'data' => $this->data,
            'is_complete' => $this->isComplete,
            'clarification' => $this->clarification,
        ];
    }
}
