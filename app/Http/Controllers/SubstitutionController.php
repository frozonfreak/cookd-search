<?php

namespace App\Http\Controllers;

use App\Services\SubstitutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubstitutionController extends Controller
{
    public function suggest(Request $request, SubstitutionService $substitutionService): JsonResponse
    {
        $validated = $request->validate([
            'ingredient' => 'required|string|max:200',
            'dish_type'  => 'nullable|string|max:100',
        ]);

        $result = $substitutionService->suggest(
            $validated['ingredient'],
            $validated['dish_type'] ?? null,
        );

        return response()->json($result);
    }
}
