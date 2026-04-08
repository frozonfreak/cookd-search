<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class PostgresTextArrayCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null || is_array($value)) {
            return $value;
        }

        return $this->parseArrayLiteral((string) $value);
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->toArrayLiteral(array_map(
            fn ($item) => (string) $item,
            is_array($value) ? $value : [$value]
        ));
    }

    /**
     * @return array<int, string>
     */
    private function parseArrayLiteral(string $value): array
    {
        $trimmed = trim($value, '{}');

        if ($trimmed === '') {
            return [];
        }

        return str_getcsv($trimmed, ',', '"', '\\');
    }

    /**
     * @param  array<int, string>  $values
     */
    private function toArrayLiteral(array $values): string
    {
        $escaped = array_map(
            fn (string $value) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"',
            $values
        );

        return '{'.implode(',', $escaped).'}';
    }
}
