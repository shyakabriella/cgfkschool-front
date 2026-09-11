<?php

namespace App\Http\Controllers\API;

use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);

        $departments = Department::query()
            ->withCount('programs')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->value()
                )
            )
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return $this->sendResponse(
            $departments,
            'Departments retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                'unique:departments,name',
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'unique:departments,code',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $department = Department::create([
            ...$validated,
            'name' => trim($validated['name']),
            'code' => Str::upper(trim($validated['code'])),
            'status' => $validated['status'] ?? 'active',
        ]);

        return $this->sendCreated(
            $department,
            'Department created successfully.'
        );
    }

    public function show(
        Request $request,
        Department $department
    ): JsonResponse {
        $this->authorizeManagement($request);

        $department->load([
            'programs' => fn ($query) => $query
                ->withCount('classes')
                ->orderBy('type')
                ->orderBy('name'),
        ]);

        return $this->sendResponse(
            $department,
            'Department retrieved successfully.'
        );
    }

    public function update(
        Request $request,
        Department $department
    ): JsonResponse {
        $this->authorizeManagement($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('departments', 'name')
                    ->ignore($department->id),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('departments', 'code')
                    ->ignore($department->id),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        $department->update([
            ...$validated,
            'name' => trim($validated['name']),
            'code' => Str::upper(trim($validated['code'])),
        ]);

        return $this->sendResponse(
            $department->fresh(),
            'Department updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        Department $department
    ): JsonResponse {
        $this->authorizeManagement($request);

        if ($department->programs()->exists()) {
            return $this->sendError(
                'This department cannot be archived because it still contains trades or options.',
                null,
                422
            );
        }

        $department->delete();

        return $this->sendResponse(
            null,
            'Department archived successfully.'
        );
    }

    private function authorizeManagement(Request $request): void
    {
        abort_unless(
            in_array(
                $request->user()?->role,
                ['headmaster', 'director_of_studies'],
                true
            ),
            403,
            'You are not allowed to manage departments.'
        );
    }

    private function perPage(Request $request): int
    {
        return min(
            max($request->integer('per_page', 15), 5),
            100
        );
    }
}
