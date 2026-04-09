<?php

namespace App\Services;

use Illuminate\Support\Str;

class EmbeddingService
{
    private const DIMENSIONS = 1536;

    /**
     * @return array<int, float>
     */
    public function embedText(string $text): array
    {
        $tokens = preg_split('/\s+/', Str::of($text)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->value()) ?: [];

        return $this->embedTokens($tokens);
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, float>
     */
    public function embedTokens(array $tokens): array
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);

        foreach ($tokens as $token) {
            if (! is_string($token) || trim($token) === '') {
                continue;
            }

            $normalized = Str::lower(trim($token));
            $bucket = abs(crc32('bucket:'.$normalized)) % self::DIMENSIONS;
            $sign = (abs(crc32('sign:'.$normalized)) % 2) === 0 ? 1.0 : -1.0;
            $weight = 1 + min(strlen($normalized), 12) / 12;

            $vector[$bucket] += $sign * $weight;
        }

        return $this->normalize($vector);
    }

    /**
     * @param  array<int, float>  $left
     * @param  array<int, float>  $right
     */
    public function cosineSimilarity(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $left = $this->normalizeLength($left);
        $right = $this->normalizeLength($right);
        $length = min(count($left), count($right));
        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $dot += $left[$i] * $right[$i];
            $leftMagnitude += $left[$i] ** 2;
            $rightMagnitude += $right[$i] ** 2;
        }

        if ($leftMagnitude === 0.0 || $rightMagnitude === 0.0) {
            return 0.0;
        }

        return round($dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude)), 4);
    }

    /**
     * @param  array<int, float>  $vector
     */
    public function toVectorLiteral(array $vector): string
    {
        $vector = $this->normalizeLength($vector);

        return '['.implode(',', array_map(
            static fn (float $value): string => rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.'),
            $vector
        )).']';
    }

    public function dimensions(): int
    {
        return self::DIMENSIONS;
    }

    /**
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function normalize(array $vector): array
    {
        $magnitude = sqrt(array_sum(array_map(
            static fn (float $value): float => $value ** 2,
            $vector
        )));

        if ($magnitude === 0.0) {
            return $vector;
        }

        return array_map(
            static fn (float $value): float => round($value / $magnitude, 8),
            $vector
        );
    }

    /**
     * @param  array<int, float>  $vector
     * @return array<int, float>
     */
    private function normalizeLength(array $vector): array
    {
        $vector = array_values(array_map('floatval', $vector));

        if (count($vector) === self::DIMENSIONS) {
            return $vector;
        }

        if (count($vector) > self::DIMENSIONS) {
            return array_slice($vector, 0, self::DIMENSIONS);
        }

        return array_pad($vector, self::DIMENSIONS, 0.0);
    }
}
