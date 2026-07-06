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
        $constructor = (new \ReflectionClass(static::class))->getConstructor();

        if ($constructor && $constructor->getParameters()) {
            $validKeys = [];

            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                $validKeys[] = $name;

                if (! array_key_exists($name, $data) || $data[$name] === null) {
                    continue;
                }

                $type = $param->getType();

                if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                    $value = $data[$name];
                    $typeName = $type->getName();

                    $data[$name] = match ($typeName) {
                        'int' => (int) $value,
                        'float' => (float) $value,
                        'string' => (string) $value,
                        'bool' => (bool) $value,
                        default => $value,
                    };
                }
            }

            $data = array_intersect_key($data, array_flip($validKeys));
        }

        return new static(...$data);
    }
}
