<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class PostgresFloatArrayCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return array_map('floatval', $value);
        }

        $trimmed = trim((string) $value, '{}');

        if ($trimmed === '') {
            return [];
        }

        return array_map('floatval', str_getcsv($trimmed, ',', '"', '\\'));
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $values = array_map(
            static fn ($item) => rtrim(rtrim(number_format((float) $item, 8, '.', ''), '0'), '.'),
            is_array($value) ? $value : [$value]
        );

        return '{'.implode(',', $values).'}';
    }
}
