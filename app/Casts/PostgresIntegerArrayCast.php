<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class PostgresIntegerArrayCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return array_map('intval', $value);
        }

        $trimmed = trim((string) $value, '{}');

        if ($trimmed === '') {
            return [];
        }

        return array_map('intval', str_getcsv($trimmed, ',', '"', '\\'));
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $values = array_map(
            fn ($item) => (int) $item,
            is_array($value) ? $value : [$value]
        );

        return '{'.implode(',', $values).'}';
    }
}
