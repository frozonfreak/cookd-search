<?php

namespace App\Services;

use App\Models\IngredientPrice;
use Illuminate\Support\Facades\Schema;

class IngredientAvailabilityService
{
    /**
     * @return array{status:string, price_per_unit:?float, unit:?string, vendor:?string}
     */
    public function check(int $ingredientId, ?string $location = null): array
    {
        if (! Schema::hasTable('ingredient_prices')) {
            return [
                'status' => 'available',
                'price_per_unit' => null,
                'unit' => null,
                'vendor' => null,
            ];
        }

        $query = IngredientPrice::query()
            ->where('ingredient_id', $ingredientId)
            ->latest('updated_at');

        if ($location !== null && trim($location) !== '') {
            $query->where(function ($builder) use ($location): void {
                $builder->where('location', $location)->orWhereNull('location');
            });
        }

        $price = $query->first();

        if ($price === null) {
            return [
                'status' => 'available',
                'price_per_unit' => null,
                'unit' => null,
                'vendor' => null,
            ];
        }

        return [
            'status' => (string) $price->availability_status,
            'price_per_unit' => $price->price_per_unit,
            'unit' => $price->unit,
            'vendor' => $price->vendor,
        ];
    }

    public function isAvailable(int $ingredientId, ?string $location = null): bool
    {
        return $this->check($ingredientId, $location)['status'] !== 'unavailable';
    }
}
