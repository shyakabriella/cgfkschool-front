<?php

namespace App\Http\Controllers\API;

use App\Models\DepartmentProgram;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SchoolClassController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);

        $classes = SchoolClass::query()
            ->with([
                'program:id,department_id,name,code,type',
                'program.department:id,name,code',
            ])
            ->when(
                $request->filled('department_program_id'),
                fn ($query) => $query->where(
                    'department_program_id',
                    $request->integer('department_program_id')
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
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('level', 'like', "%{$search}%");
                });
            })
            ->orderBy('level')
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return $this->sendResponse(
            $classes,
            'Classes retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);

        $programId = $request->integer(
            'department_program_id'
        );

        $validated = $request->validate(
            $this->rules($programId)
        );

        $this->ensureProgramIsActive($programId);

        $schoolClass = SchoolClass::create([
            ...$validated,
            'name' => trim($validated['name']),
            'code' => Str::upper(trim($validated['code'])),
            'status' => $validated['status'] ?? 'active',
        ]);

        return $this->sendCreated(
            $schoolClass->load(
                'program.department:id,name,code'
            ),
            'Class created successfully.'
        );
    }

    public function show(
        Request $request,
        SchoolClass $schoolClass
    ): JsonResponse {
        $this->authorizeManagement($request);

        $schoolClass->load([
            'program:id,department_id,name,code,type,status',
            'program.department:id,name,code,status',
        ]);

        return $this->sendResponse(
            $schoolClass,
            'Class retrieved successfully.'
        );
    }

    public function update(
        Request $request,
        SchoolClass $schoolClass
    ): JsonResponse {
        $this->authorizeManagement($request);

        $programId = $request->integer(
            'department_program_id'
        );

        $validated = $request->validate(
            $this->rules(
                $programId,
                $schoolClass->id
            )
        );

        $this->ensureProgramIsActive($programId);

        $schoolClass->update([
            ...$validated,
            'name' => trim($validated['name']),
            'code' => Str::upper(trim($validated['code'])),
        ]);

        return $this->sendResponse(
            $schoolClass
                ->fresh()
                ->load('program.department:id,name,code'),
            'Class updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        SchoolClass $schoolClass
    ): JsonResponse {
        $this->authorizeManagement($request);

        $schoolClass->delete();

        return $this->sendResponse(
            null,
            'Class archived successfully.'
        );
    }

    private function rules(
        int $programId,
        ?int $ignoreId = null
    ): array {
        return [
            'department_program_id' => [
                'required',
                'integer',
                'exists:department_programs,id',
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('school_classes', 'name')
                    ->where(
                        fn ($query) => $query->where(
                            'department_program_id',
                            $programId
                        )
                    )
                    ->ignore($ignoreId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('school_classes', 'code')
                    ->where(
                        fn ($query) => $query->where(
                            'department_program_id',
                            $programId
                        )
                    )
                    ->ignore($ignoreId),
            ],
            'level' => [
                'nullable',
                'string',
                'max:50',
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

    private function ensureProgramIsActive(
        int $programId
    ): void {
        $program = DepartmentProgram::with(
            'department:id,status'
        )->find($programId);

        abort_unless(
            $program &&
                $program->status === 'active' &&
                $program->department?->status === 'active',
            422,
            'The selected trade, option, or department is inactive.'
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
            'You are not allowed to manage classes.'
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
