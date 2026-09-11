<?php

namespace App\Http\Controllers\API;

use App\Models\Department;
use App\Models\DepartmentProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentProgramController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);

        $programs = DepartmentProgram::query()
            ->with('department:id,name,code')
            ->withCount('classes')
            ->when(
                $request->filled('department_id'),
                fn ($query) => $query->where(
                    'department_id',
                    $request->integer('department_id')
                )
            )
            ->when(
                $request->filled('type'),
                fn ($query) => $query->where(
                    'type',
                    $request->string('type')->value()
                )
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->string('status')->value()
                )
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return $this->sendResponse(
            $programs,
            'Trades and options retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);

        $departmentId = $request->integer('department_id');

        $validated = $request->validate(
            $this->rules($departmentId)
        );

        $this->ensureDepartmentIsActive($departmentId);

        $program = DepartmentProgram::create([
            ...$validated,
            'name' => trim($validated['name']),
            'code' => Str::upper(trim($validated['code'])),
            'status' => $validated['status'] ?? 'active',
        ]);

        return $this->sendCreated(
            $program->load('department:id,name,code'),
            ucfirst($program->type).' created successfully.'
        );
    }

    public function show(
        Request $request,
        DepartmentProgram $departmentProgram
    ): JsonResponse {
        $this->authorizeManagement($request);

        $departmentProgram->load([
            'department:id,name,code,status',
            'classes' => fn ($query) => $query
                ->orderBy('level')
                ->orderBy('name'),
        ]);

        return $this->sendResponse(
            $departmentProgram,
            'Trade or option retrieved successfully.'
        );
    }

    public function update(
        Request $request,
        DepartmentProgram $departmentProgram
    ): JsonResponse {
        $this->authorizeManagement($request);

        $departmentId = $request->integer('department_id');

        $validated = $request->validate(
            $this->rules(
                $departmentId,
                $departmentProgram->id
            )
        );

        $this->ensureDepartmentIsActive($departmentId);

        $departmentProgram->update([
            ...$validated,
            'name' => trim($validated['name']),
            'code' => Str::upper(trim($validated['code'])),
        ]);

        return $this->sendResponse(
            $departmentProgram
                ->fresh()
                ->load('department:id,name,code'),
            'Trade or option updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        DepartmentProgram $departmentProgram
    ): JsonResponse {
        $this->authorizeManagement($request);

        if ($departmentProgram->classes()->exists()) {
            return $this->sendError(
                'This trade or option cannot be archived because it still contains classes.',
                null,
                422
            );
        }

        $departmentProgram->delete();

        return $this->sendResponse(
            null,
            'Trade or option archived successfully.'
        );
    }

    private function rules(
        int $departmentId,
        ?int $ignoreId = null
    ): array {
        return [
            'department_id' => [
                'required',
                'integer',
                'exists:departments,id',
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('department_programs', 'name')
                    ->where(
                        fn ($query) => $query->where(
                            'department_id',
                            $departmentId
                        )
                    )
                    ->ignore($ignoreId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('department_programs', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'department_id',
                            $departmentId
                        )
                    )
                    ->ignore($ignoreId),
            ],
            'type' => [
                'required',
                Rule::in(['trade', 'option']),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }

    private function ensureDepartmentIsActive(
        int $departmentId
    ): void {
        $departmentIsActive = Department::query()
            ->whereKey($departmentId)
            ->where('status', 'active')
            ->exists();

        abort_unless(
            $departmentIsActive,
            422,
            'The selected department is inactive.'
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
            'You are not allowed to manage trades or options.'
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
