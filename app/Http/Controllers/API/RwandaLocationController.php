<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\RwandaLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RwandaLocationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => [
                'required',
                Rule::in([
                    'province',
                    'district',
                    'sector',
                    'cell',
                    'village',
                ]),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:rwanda_locations,id'],
        ]);

        $locations = RwandaLocation::query()
            ->select(['id', 'parent_id', 'name', 'type'])
            ->where('type', $validated['type'])
            ->when(
                array_key_exists('parent_id', $validated),
                fn ($query) => $query->where(
                    'parent_id',
                    $validated['parent_id']
                )
            )
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            $locations,
            'Locations retrieved successfully.'
        );
    }
}
