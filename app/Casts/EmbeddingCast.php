<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cast for the embedding column, which is vector(1536) when pgvector is
 * available and real[] otherwise. pgvector requires "[...]" notation while
 * PostgreSQL arrays require "{...}" notation.
 */
class EmbeddingCast implements CastsAttributes
{
    private static ?bool $useBrackets = null;

    public function get($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return array_map('floatval', $value);
        }

        $trimmed = trim((string) $value, '[]{}');

        if ($trimmed === '') {
            return [];
        }

        return array_map('floatval', explode(',', $trimmed));
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $floats = array_map(
            static fn ($item) => rtrim(rtrim(number_format((float) $item, 8, '.', ''), '0'), '.'),
            is_array($value) ? $value : [$value]
        );

        return $this->pgvectorActive()
            ? '['.implode(',', $floats).']'
            : '{'.implode(',', $floats).'}';
    }

    private function pgvectorActive(): bool
    {
        if (self::$useBrackets === null) {
            try {
                self::$useBrackets = DB::selectOne(
                    "SELECT 1 FROM pg_extension WHERE extname = 'vector'"
                ) !== null;
            } catch (Throwable) {
                self::$useBrackets = false;
            }
        }

        return self::$useBrackets;
    }
}
