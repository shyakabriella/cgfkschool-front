<?php

namespace App\Http\Controllers\API;

use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TeacherAssignmentController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $assignments = TeacherAssignment::query()
            ->with([
                'teacher:id,name,email,phone,role,status',
                'schoolClass:id,department_program_id,name,code,level,status',
                'schoolClass.program:id,department_id,name,code,type',
                'course:id,name,code,hours,periods,status',
            ])
            ->when(
                $request->filled('teacher_id'),
                fn ($query) => $query->where(
                    'teacher_id',
                    $request->integer('teacher_id')
                )
            )
            ->when(
                $request->filled('school_class_id'),
                fn ($query) => $query->where(
                    'school_class_id',
                    $request->integer('school_class_id')
                )
            )
            ->when(
                $request->filled('course_id'),
                fn ($query) => $query->where(
                    'course_id',
                    $request->integer('course_id')
                )
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->status)
            )
            ->latest()
            ->paginate(
                min(max($request->integer('per_page', 15), 5), 100)
            );

        return $this->sendResponse(
            $assignments,
            'Teacher assignments retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeManagement($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'teacher_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'school_class_id' => [
                'required',
                'integer',
                'exists:school_classes,id',
            ],
            'course_id' => [
                'required',
                'integer',
                'exists:courses,id',
                Rule::unique('teacher_assignments', 'course_id')
                    ->where(
                        fn ($query) => $query->where(
                            'school_class_id',
                            $request->school_class_id
                        )
                    ),
            ],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The assignment information is invalid.',
                $validator->errors(),
                422
            );
        }

        if (! $this->validTeacher($request->integer('teacher_id'))) {
            return $this->sendError(
                'The selected user is not an active teacher.',
                [],
                422
            );
        }

        if (! SchoolClass::query()
            ->whereKey($request->integer('school_class_id'))
            ->where('status', 'active')
            ->exists()) {
            return $this->sendError(
                'The selected class is inactive.',
                [],
                422
            );
        }

        if (! Course::query()
            ->whereKey($request->integer('course_id'))
            ->where('status', 'active')
            ->exists()) {
            return $this->sendError(
                'The selected course is inactive.',
                [],
                422
            );
        }

        $assignment = TeacherAssignment::create([
            'teacher_id' => $request->teacher_id,
            'school_class_id' => $request->school_class_id,
            'course_id' => $request->course_id,
            'status' => $request->status ?: 'active',
        ]);

        return $this->sendCreated(
            $this->loadAssignment($assignment),
            'Teacher assigned successfully.'
        );
    }

    public function show(
        TeacherAssignment $teacherAssignment
    ): JsonResponse {
        return $this->sendResponse(
            $this->loadAssignment($teacherAssignment),
            'Teacher assignment retrieved successfully.'
        );
    }

    public function update(
        Request $request,
        TeacherAssignment $teacherAssignment
    ): JsonResponse {
        if ($response = $this->authorizeManagement($request)) {
            return $response;
        }

        $classId = $request->integer(
            'school_class_id',
            $teacherAssignment->school_class_id
        );

        $validator = Validator::make($request->all(), [
            'teacher_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:users,id',
            ],
            'school_class_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:school_classes,id',
            ],
            'course_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:courses,id',
                Rule::unique('teacher_assignments', 'course_id')
                    ->where(
                        fn ($query) => $query->where(
                            'school_class_id',
                            $classId
                        )
                    )
                    ->ignore($teacherAssignment->id),
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The assignment information is invalid.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        $teacherId = $data['teacher_id']
            ?? $teacherAssignment->teacher_id;

        if (! $this->validTeacher((int) $teacherId)) {
            return $this->sendError(
                'The selected user is not an active teacher.',
                [],
                422
            );
        }

        $teacherAssignment->update($data);

        return $this->sendResponse(
            $this->loadAssignment($teacherAssignment->fresh()),
            'Teacher assignment updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        TeacherAssignment $teacherAssignment
    ): JsonResponse {
        if ($response = $this->authorizeManagement($request)) {
            return $response;
        }

        $teacherAssignment->delete();

        return $this->sendResponse(
            null,
            'Teacher assignment removed successfully.'
        );
    }

    private function validTeacher(int $teacherId): bool
    {
        return User::query()
            ->whereKey($teacherId)
            ->where('role', 'teacher')
            ->where('status', 'active')
            ->exists();
    }

    private function loadAssignment(
        TeacherAssignment $assignment
    ): TeacherAssignment {
        return $assignment->load([
            'teacher:id,name,email,phone,role,status',
            'schoolClass:id,department_program_id,name,code,level,status',
            'schoolClass.program:id,department_id,name,code,type',
            'course:id,name,code,hours,periods,status',
        ]);
    }

    private function authorizeManagement(
        Request $request
    ): ?JsonResponse {
        $user = $request->user();

        if (! $user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        if (! in_array($user->role, [
            'admin',
            'headmaster',
            'director_of_studies',
        ], true)) {
            return $this->sendError(
                'You are not allowed to manage teacher assignments.',
                [],
                403
            );
        }

        return null;
    }
}
