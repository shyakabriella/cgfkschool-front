<?php

namespace App\Http\Controllers\API;

use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CourseController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $courses = Course::query()
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where(
                    'status',
                    $request->status
                )
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(
                min(max($request->integer('per_page', 15), 5), 100)
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

    public function show(Course $course): JsonResponse
    {
        return $this->sendResponse(
            $course,
            'Course retrieved successfully.'
        );
    }

    public function update(
        Request $request,
        Course $course
    ): JsonResponse {
        if ($response = $this->authorizeManagement($request)) {
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

    public function destroy(
        Request $request,
        Course $course
    ): JsonResponse {
        if ($response = $this->authorizeManagement($request)) {
            return $response;
        }

        $course->delete();

        return $this->sendResponse(
            null,
            'Course archived successfully.'
        );
    }

    private function authorizeManagement(
        Request $request
    ): ?JsonResponse {
        $user = $request->user();

        if (! $user) {
            return $this->sendError(
                'Unauthenticated.',
                [],
                401
            );
        }

        $allowedRoles = [
            'admin',
            'headmaster',
            'director_of_studies',
        ];

        if (! in_array($user->role, $allowedRoles, true)) {
            return $this->sendError(
                'You are not allowed to manage courses.',
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
}
