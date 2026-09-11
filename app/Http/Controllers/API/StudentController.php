<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class StudentController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if ($response = $this->authorizeManagement($request)) {
            return $response;
        }

        $students = Student::query()
            ->with([
                'schoolClass:id,department_program_id,name,code,level,status',
                'schoolClass.program:id,department_id,name,code,type',
                'schoolClass.program.department:id,name,code',
            ])
            ->when(
                $request->filled('school_class_id'),
                fn ($query) => $query->where(
                    'school_class_id',
                    $request->integer('school_class_id')
                )
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->status)
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('student_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('parent_contact', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(
                min(max($request->integer('per_page', 15), 5), 100)
            );

        return $this->sendResponse(
            $students,
            'Students retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeManagement($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'mother_name' => ['nullable', 'string', 'max:150'],
            'parent_contact' => ['required', 'string', 'max:30'],

            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_contact' => ['nullable', 'string', 'max:30'],
            'guardian_relationship' => ['nullable', 'string', 'max:100'],

            'date_of_birth' => ['required', 'date', 'before:today'],
            'school_class_id' => [
                'required',
                'integer',
                'exists:school_classes,id',
            ],

            'province' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'sector' => ['required', 'string', 'max:100'],
            'cell' => ['required', 'string', 'max:100'],
            'village' => ['required', 'string', 'max:100'],

            'previous_school_name' => ['nullable', 'string', 'max:200'],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:3072',
            ],

            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => [
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
                'max:5120',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'Validation failed.',
                $validator->errors(),
                422
            );
        }

        $schoolClass = SchoolClass::query()
            ->whereKey($request->integer('school_class_id'))
            ->where('status', 'active')
            ->whereHas('program', function ($query) {
                $query
                    ->where('status', 'active')
                    ->whereHas('department', function ($departmentQuery) {
                        $departmentQuery->where('status', 'active');
                    });
            })
            ->first();

        if (! $schoolClass) {
            return $this->sendError(
                'The selected class, trade/option or department is inactive.',
                [],
                422
            );
        }

        $imagePath = null;
        $documentPaths = [];

        try {
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')
                    ->store('students/images', 'public');
            }

            foreach ($request->file('documents', []) as $document) {
                $documentPaths[] = $document
                    ->store('students/documents', 'public');
            }

            $student = DB::transaction(function () use (
                $request,
                $imagePath,
                $documentPaths
            ) {
                $student = Student::create([
                    'first_name' => trim($request->first_name),
                    'last_name' => trim($request->last_name),
                    'father_name' => $this->nullableText(
                        $request->father_name
                    ),
                    'mother_name' => $this->nullableText(
                        $request->mother_name
                    ),
                    'parent_contact' => trim($request->parent_contact),
                    'guardian_name' => $this->nullableText(
                        $request->guardian_name
                    ),
                    'guardian_contact' => $this->nullableText(
                        $request->guardian_contact
                    ),
                    'guardian_relationship' => $this->nullableText(
                        $request->guardian_relationship
                    ),
                    'date_of_birth' => $request->date_of_birth,
                    'school_class_id' => $request->school_class_id,
                    'province' => trim($request->province),
                    'district' => trim($request->district),
                    'sector' => trim($request->sector),
                    'cell' => trim($request->cell),
                    'village' => trim($request->village),
                    'previous_school_name' => $this->nullableText(
                        $request->previous_school_name
                    ),
                    'documents' => $documentPaths,
                    'image' => $imagePath,
                    'status' => 'active',
                ]);

                $student->update([
                    'student_id' => sprintf(
                        'CGFK-%s-%06d',
                        now()->format('Y'),
                        $student->id
                    ),
                ]);

                return $student;
            });

            $student->load([
                'schoolClass:id,department_program_id,name,code,level,status',
                'schoolClass.program:id,department_id,name,code,type',
                'schoolClass.program.department:id,name,code',
            ]);

            return $this->sendResponse(
                $student,
                'Student registered successfully.'
            );
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            if ($documentPaths) {
                Storage::disk('public')->delete($documentPaths);
            }

            report($exception);

            return $this->sendError(
                'Student registration failed.',
                [],
                500
            );
        }
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        if ($response = $this->authorizeManagement($request)) {
            return $response;
        }

        $student->load([
            'schoolClass:id,department_program_id,name,code,level,status',
            'schoolClass.program:id,department_id,name,code,type',
            'schoolClass.program.department:id,name,code',
        ]);

        return $this->sendResponse(
            $student,
            'Student retrieved successfully.'
        );
    }

    private function authorizeManagement(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        $allowedRoles = [
            'admin',
            'headmaster',
            'director_of_studies',
            'registrar',
        ];

        $role = is_string($user->role)
            ? $user->role
            : $user->role?->slug;

        if (! in_array($role, $allowedRoles, true)) {
            return $this->sendError(
                'You are not allowed to manage students.',
                [],
                403
            );
        }

        return null;
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    public function update(
        \Illuminate\Http\Request $request,
        \App\Models\Student $student
    ): \Illuminate\Http\JsonResponse {
        $user = $request->user();

        if (!in_array($user->role, [
            'headmaster',
            'director_of_studies',
            'accountant',
        ], true)) {
            return $this->sendError(
                'You are not allowed to update students.',
                null,
                403
            );
        }

        $validator = \Illuminate\Support\Facades\Validator::make(
            $request->all(),
            [
                'first_name' => [
                    'required',
                    'string',
                    'max:100',
                ],
                'last_name' => [
                    'required',
                    'string',
                    'max:100',
                ],
                'parent_contact' => [
                    'required',
                    'string',
                    'max:30',
                ],
                'school_class_id' => [
                    'required',
                    'integer',
                    'exists:school_classes,id',
                ],
                'status' => [
                    'required',
                    \Illuminate\Validation\Rule::in([
                        'active',
                        'inactive',
                    ]),
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->sendError(
                'The student information is invalid.',
                $validator->errors(),
                422
            );
        }

        $student->update([
            'first_name' => trim($request->first_name),
            'last_name' => trim($request->last_name),
            'parent_contact' => trim($request->parent_contact),
            'school_class_id' => $request->integer(
                'school_class_id'
            ),
            'status' => $request->status,
        ]);

        $student->load('schoolClass');

        return $this->sendResponse(
            $student,
            'Student updated successfully.'
        );
    }

    public function destroy(
        \Illuminate\Http\Request $request,
        \App\Models\Student $student
    ): \Illuminate\Http\JsonResponse {
        $user = $request->user();

        if (!in_array($user->role, [
            'headmaster',
            'director_of_studies',
            'accountant',
        ], true)) {
            return $this->sendError(
                'You are not allowed to delete students.',
                null,
                403
            );
        }

        $student->delete();

        return $this->sendResponse(
            null,
            'Student deleted successfully.'
        );
    }

}
