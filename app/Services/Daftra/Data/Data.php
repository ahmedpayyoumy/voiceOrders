<?php

namespace App\Services\Daftra\Data;

abstract class Data
{
    abstract protected function moduleKey(): string;

    public function toArray(): array
    {
        $data = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value instanceof Data
                    ? $value->toArray()
                    : (is_array($value)
                        ? array_map(fn ($v) => $v instanceof Data ? $v->toArray() : $v, $value)
                        : $value);
            }
        }

        return [$this->moduleKey() => $data];
    }

    public function toRequestBody(): array
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): static
    {
        $instance = new static;
        foreach ($data as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->{$key} = $value;
            }
        }

        return $instance;
    }
}
