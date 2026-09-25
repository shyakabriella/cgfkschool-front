<?php

namespace App\Http\Controllers\API;

use App\Models\Course;
use App\Models\CourseIndicativeContent;
use App\Models\CourseLearningUnit;
use App\Models\TeachingMaterial;
use App\Services\GeminiTeachingMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TeachingMaterialController extends BaseController
{
    public function analyzeSyllabus(
        Request $request,
        Course $course,
        GeminiTeachingMaterialService $gemini
    ): JsonResponse {
        if ($response = $this->authorizeCourse(
            $request,
            $course
        )) {
            return $response;
        }

        try {
            $result = $gemini->analyzeSyllabus($course);

            DB::transaction(function () use (
                $course,
                $result
            ) {
                $course->learningUnits()->delete();

                foreach (
                    $result['learning_units'] ?? []
                    as $unitPosition => $unitData
                ) {
                    $unit = $course
                        ->learningUnits()
                        ->create([
                            'code' =>
                                $unitData['code'] ?: null,
                            'title' =>
                                trim($unitData['title']),
                            'description' =>
                                $unitData['description']
                                ?: null,
                            'learning_outcomes' =>
                                $unitData[
                                    'learning_outcomes'
                                ] ?? [],
                            'source_page' =>
                                $unitData['source_page']
                                ?: null,
                            'position' =>
                                $unitPosition + 1,
                        ]);

                    foreach (
                        $unitData[
                            'indicative_contents'
                        ] ?? []
                        as $contentPosition => $content
                    ) {
                        $unit->indicativeContents()
                            ->create([
                                'title' =>
                                    trim($content['title']),
                                'details' =>
                                    $content['details']
                                    ?: null,
                                'source_page' =>
                                    $content[
                                        'source_page'
                                    ] ?: null,
                                'position' =>
                                    $contentPosition + 1,
                            ]);
                    }
                }
            });

            return $this->sendResponse(
                $course->learningUnits()
                    ->with('indicativeContents')
                    ->orderBy('position')
                    ->get(),
                'Syllabus analyzed successfully.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->sendError(
                $exception->getMessage(),
                [],
                502
            );
        }
    }

    public function learningUnits(
        Request $request,
        Course $course
    ): JsonResponse {
        if ($response = $this->authorizeCourse(
            $request,
            $course
        )) {
            return $response;
        }

        return $this->sendResponse(
            $course->learningUnits()
                ->with('indicativeContents')
                ->orderBy('position')
                ->get(),
            'Learning units retrieved successfully.'
        );
    }

    public function generate(
        Request $request,
        Course $course,
        GeminiTeachingMaterialService $gemini
    ): JsonResponse {
        if ($response = $this->authorizeCourse(
            $request,
            $course
        )) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'school_class_id' => [
                'required',
                'integer',
                'exists:school_classes,id',
            ],
            'course_learning_unit_id' => [
                'required',
                'integer',
                'exists:course_learning_units,id',
            ],
            'course_indicative_content_id' => [
                'required',
                'integer',
                'exists:course_indicative_contents,id',
            ],
            'lesson_date' => [
                'required',
                'date',
            ],
            'duration_minutes' => [
                'required',
                'integer',
                'min:10',
                'max:480',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The lesson information is invalid.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        $unit = CourseLearningUnit::query()
            ->where('course_id', $course->id)
            ->find($data['course_learning_unit_id']);

        if (! $unit) {
            return $this->sendError(
                'The selected learning unit does not belong to this course.',
                [],
                422
            );
        }

        $content = CourseIndicativeContent::query()
            ->where(
                'course_learning_unit_id',
                $unit->id
            )
            ->find(
                $data[
                    'course_indicative_content_id'
                ]
            );

        if (! $content) {
            return $this->sendError(
                'The selected indicative content does not belong to this learning unit.',
                [],
                422
            );
        }

        if (
            ! $this->canUseClass(
                $request,
                $course,
                (int) $data['school_class_id']
            )
        ) {
            return $this->sendError(
                'This class is not assigned to you for this course.',
                [],
                403
            );
        }

        try {
            $generated =
                $gemini->generateTeachingMaterial(
                    $course,
                    $unit,
                    $content,
                    $data
                );

            $contentResult = $generated['content'];

            $status =
                ($contentResult['status'] ?? null)
                === 'insufficient_material'
                    ? 'insufficient_material'
                    : 'draft';

            $material = TeachingMaterial::create([
                'course_id' => $course->id,
                'teacher_id' =>
                    $request->user()->id,
                'school_class_id' =>
                    $data['school_class_id'],
                'course_learning_unit_id' =>
                    $unit->id,
                'course_indicative_content_id' =>
                    $content->id,
                'title' =>
                    $contentResult['title']
                    ?: $content->title,
                'lesson_date' =>
                    $data['lesson_date'],
                'duration_minutes' =>
                    $data['duration_minutes'],
                'generated_content' =>
                    $contentResult,
                'source_documents' =>
                    $generated['sources'],
                'ai_model' =>
                    $generated['model'],
                'status' => $status,
            ]);

            return $this->sendCreated(
                $material->load([
                    'course:id,name,code',
                    'teacher:id,name,email',
                    'schoolClass:id,name,code',
                    'learningUnit',
                    'indicativeContent',
                ]),
                $status === 'insufficient_material'
                    ? 'The uploaded materials do not contain enough information.'
                    : 'Teaching material generated and saved as a draft.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->sendError(
                $exception->getMessage(),
                [],
                502
            );
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = TeachingMaterial::query()
            ->with([
                'course:id,name,code',
                'teacher:id,name,email',
                'schoolClass:id,name,code',
                'learningUnit',
                'indicativeContent',
            ]);

        if ($request->user()->role === 'teacher') {
            $query->where(
                'teacher_id',
                $request->user()->id
            );
        }

        return $this->sendResponse(
            $query->latest()->paginate(15),
            'Teaching materials retrieved successfully.'
        );
    }

    public function show(
        Request $request,
        TeachingMaterial $teachingMaterial
    ): JsonResponse {
        if (
            $request->user()->role === 'teacher' &&
            $teachingMaterial->teacher_id
                !== $request->user()->id
        ) {
            return $this->sendError(
                'You are not allowed to view this teaching material.',
                [],
                403
            );
        }

        return $this->sendResponse(
            $teachingMaterial->load([
                'course:id,name,code',
                'teacher:id,name,email',
                'schoolClass:id,name,code',
                'learningUnit',
                'indicativeContent',
            ]),
            'Teaching material retrieved successfully.'
        );
    }

    private function authorizeCourse(
        Request $request,
        Course $course
    ): ?JsonResponse {
        $user = $request->user();

        if (in_array($user->role, [
            'admin',
            'headmaster',
            'director_of_studies',
        ], true)) {
            return null;
        }

        if ($user->role !== 'teacher') {
            return $this->sendError(
                'You are not allowed to manage teaching materials.',
                [],
                403
            );
        }

        $assigned = $course->teacherAssignments()
            ->where('teacher_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $assigned) {
            return $this->sendError(
                'This course is not assigned to you.',
                [],
                403
            );
        }

        return null;
    }

    private function canUseClass(
        Request $request,
        Course $course,
        int $classId
    ): bool {
        if (in_array($request->user()->role, [
            'admin',
            'headmaster',
            'director_of_studies',
        ], true)) {
            return true;
        }

        return $course->teacherAssignments()
            ->where(
                'teacher_id',
                $request->user()->id
            )
            ->where('school_class_id', $classId)
            ->where('status', 'active')
            ->exists();
    }
}
