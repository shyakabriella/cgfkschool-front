<?php

namespace App\Http\Controllers\API;

use App\Models\FeeItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FeeItemController extends BaseController
{
    private function allowed(Request $request): bool
    {
        return in_array($request->user()->role, [
            'headmaster',
            'accountant',
        ], true);
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to manage finance settings.',
                null,
                403
            );
        }

        $items = FeeItem::query()
            ->with('schoolClass:id,name,code')
            ->orderByDesc('academic_year')
            ->orderBy('term')
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            $items,
            'Fee items retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to create fee items.',
                null,
                403
            );
        }

        $validator = $this->validateItem($request);

        if ($validator->fails()) {
            return $this->sendError(
                'The fee item information is invalid.',
                $validator->errors(),
                422
            );
        }

        $item = FeeItem::create($validator->validated());
        $item->load('schoolClass:id,name,code');

        return $this->sendCreated(
            $item,
            'Fee item created successfully.'
        );
    }

    public function update(
        Request $request,
        FeeItem $feeItem
    ): JsonResponse {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to update fee items.',
                null,
                403
            );
        }

        $validator = $this->validateItem($request);

        if ($validator->fails()) {
            return $this->sendError(
                'The fee item information is invalid.',
                $validator->errors(),
                422
            );
        }

        $feeItem->update($validator->validated());
        $feeItem->load('schoolClass:id,name,code');

        return $this->sendResponse(
            $feeItem,
            'Fee item updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        FeeItem $feeItem
    ): JsonResponse {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to delete fee items.',
                null,
                403
            );
        }

        $feeItem->delete();

        return $this->sendResponse(
            null,
            'Fee item deleted successfully.'
        );
    }

    private function validateItem(Request $request)
    {
        return Validator::make($request->all(), [
            'school_class_id' => [
                'nullable',
                'integer',
                'exists:school_classes,id',
            ],
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'academic_year' => [
                'required',
                'string',
                'max:20',
            ],
            'term' => [
                'nullable',
                'integer',
                Rule::in([1, 2, 3]),
            ],
            'is_required' => [
                'required',
                'boolean',
            ],
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);
    }
}
