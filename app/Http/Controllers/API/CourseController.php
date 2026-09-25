<?php

namespace App\Http\Controllers\API;

use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CourseController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->sendError(
                'Unauthenticated.',
                [],
                401
            );
        }

        $query = Course::query();

        if ($user->role === 'teacher') {
            $query
                ->where('status', 'active')
                ->whereHas(
                    'teacherAssignments',
                    function ($assignmentQuery) use ($user) {
                        $assignmentQuery
                            ->where('teacher_id', $user->id)
                            ->where('status', 'active');
                    }
                )
                ->with([
                    'teacherAssignments' => function (
                        $assignmentQuery
                    ) use ($user) {
                        $assignmentQuery
                            ->where('teacher_id', $user->id)
                            ->where('status', 'active')
                            ->with([
                                'schoolClass:id,name,code,level',
                            ]);
                    },
                ]);
        }

        $courses = $query
            ->when(
                $request->filled('status') &&
                $user->role !== 'teacher',
                fn ($query) => $query->where(
                    'status',
                    $request->status
                )
            )
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = trim(
                        (string) $request->search
                    );

                    $query->where(
                        function ($query) use ($search) {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'description',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->orderBy('name')
            ->paginate(
                min(
                    max(
                        $request->integer('per_page', 15),
                        5
                    ),
                    100
                )
            );

        return $this->sendResponse(
            $courses,
            'Courses retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeManagement($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:150',
                'unique:courses,name',
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'unique:courses,code',
            ],
            'hours' => [
                'required',
                'integer',
                'min:1',
                'max:10000',
            ],
            'periods' => [
                'required',
                'integer',
                'min:1',
                'max:10000',
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

        if ($validator->fails()) {
            return $this->sendError(
                'The provided course information is invalid.',
                $validator->errors(),
                422
            );
        }

        $course = Course::create([
            'name' => trim($request->name),
            'code' => strtoupper(trim($request->code)),
            'hours' => $request->integer('hours'),
            'periods' => $request->integer('periods'),
            'description' => $this->nullableText(
                $request->description
            ),
            'status' => $request->status ?: 'active',
        ]);

        return $this->sendCreated(
            $course,
            'Course created successfully.'
        );
    }

    public function show(
        Request $request,
        Course $course
    ): JsonResponse {
        $user = $request->user();

        if (! $user) {
            return $this->sendError(
                'Unauthenticated.',
                [],
                401
            );
        }

        if ($user->role === 'teacher') {
            $isAssigned = $course
                ->teacherAssignments()
                ->where('teacher_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (! $isAssigned) {
                return $this->sendError(
                    'This course is not assigned to you.',
                    [],
                    403
                );
            }

            $course->load([
                'teacherAssignments' => function (
                    $assignmentQuery
                ) use ($user) {
                    $assignmentQuery
                        ->where('teacher_id', $user->id)
                        ->where('status', 'active')
                        ->with([
                            'schoolClass:id,name,code,level',
                        ]);
                },
            ]);
        }

        return $this->sendResponse(
            $course,
            'Course retrieved successfully.'
        );
    }

    public function update(
        Request $request,
        Course $course
    ): JsonResponse {
        if (
            $response = $this->authorizeManagement(
                $request,
                $course
            )
        ) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('courses', 'name')
                    ->ignore($course->id),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('courses', 'code')
                    ->ignore($course->id),
            ],
            'hours' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:10000',
            ],
            'periods' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:10000',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'status' => [
                'sometimes',
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The provided course information is invalid.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }

        if (array_key_exists('code', $data)) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        if (array_key_exists('description', $data)) {
            $data['description'] = $this->nullableText(
                $data['description']
            );
        }

        $course->update($data);

        return $this->sendResponse(
            $course->fresh(),
            'Course updated successfully.'
        );
    }

    public function uploadMaterials(
        Request $request,
        Course $course
    ): JsonResponse {
        if (
            $response = $this->authorizeManagement(
                $request,
                $course
            )
        ) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'curriculum' => [
                'nullable',
                'required_without:notes',
                'file',
                'mimes:pdf,doc,docx',
                'max:20480',
            ],
            'notes' => [
                'nullable',
                'required_without:curriculum',
                'file',
                'mimes:pdf,doc,docx',
                'max:20480',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The course material is invalid.',
                $validator->errors(),
                422
            );
        }

        $updates = [];

        if ($request->hasFile('curriculum')) {
            if ($course->curriculum_path) {
                Storage::disk('public')->delete(
                    $course->curriculum_path
                );
            }

            $updates['curriculum_path'] = $request
                ->file('curriculum')
                ->store(
                    "course-materials/{$course->id}/curriculum",
                    'public'
                );

            // The old Gemini file must not be reused.
            $updates['gemini_curriculum_file'] = null;
        }

        if ($request->hasFile('notes')) {
            if ($course->notes_path) {
                Storage::disk('public')->delete(
                    $course->notes_path
                );
            }

            $updates['notes_path'] = $request
                ->file('notes')
                ->store(
                    "course-materials/{$course->id}/notes",
                    'public'
                );

            // The old Gemini file must not be reused.
            $updates['gemini_notes_file'] = null;
        }

        $course->update($updates);

        return $this->sendResponse(
            $course->fresh(),
            'Course materials uploaded successfully.'
        );
    }

    public function destroy(
        Request $request,
        Course $course
    ): JsonResponse {
        if (
            $response = $this->authorizeManagement(
                $request,
                $course
            )
        ) {
            return $response;
        }

        $course->delete();

        return $this->sendResponse(
            null,
            'Course archived successfully.'
        );
    }

    private function authorizeManagement(
        Request $request,
        ?Course $course = null
    ): ?JsonResponse {
        $user = $request->user();

        if (! $user) {
            return $this->sendError(
                'Unauthenticated.',
                [],
                401
            );
        }

        $managementRoles = [
            'admin',
            'headmaster',
            'director_of_studies',
        ];

        if (
            in_array(
                $user->role,
                $managementRoles,
                true
            )
        ) {
            return null;
        }

        if ($user->role === 'teacher' && $course) {
            $isAssigned = $course
                ->teacherAssignments()
                ->where('teacher_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if ($isAssigned) {
                return null;
            }

            return $this->sendError(
                'You can only manage courses assigned to you.',
                [],
                403
            );
        }

        return $this->sendError(
            'You are not allowed to manage courses.',
            [],
            403
        );
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
