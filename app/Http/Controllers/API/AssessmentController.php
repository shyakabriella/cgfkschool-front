<?php

namespace App\Http\Controllers\API;

use App\Models\Assessment;
use App\Models\Course;
use App\Services\GeminiAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class AssessmentController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Assessment::query()
            ->with([
                'course:id,name,code',
                'teacher:id,name,email',
            ])
            ->withCount('questions');

        if ($user->role === 'teacher') {
            $query->where('teacher_id', $user->id);
        }

        if ($request->filled('course_id')) {
            $query->where(
                'course_id',
                $request->integer('course_id')
            );
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->sendResponse(
            $query->latest()->paginate(15),
            'Assessments retrieved successfully.'
        );
    }

    public function generate(
        Request $request,
        Course $course,
        GeminiAssessmentService $gemini
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
            'type' => [
                'required',
                Rule::in([
                    'assignment',
                    'quiz',
                    'exam',
                ]),
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'difficulty' => [
                'required',
                Rule::in([
                    'easy',
                    'medium',
                    'hard',
                    'mixed',
                ]),
            ],
            'question_count' => [
                'required',
                'integer',
                'min:1',
                'max:50',
            ],
            'question_types' => [
                'required',
                'array',
                'min:1',
            ],
            'question_types.*' => [
                'required',
                'distinct',
                Rule::in([
                    'multiple_choice',
                    'true_false',
                    'short_answer',
                    'essay',
                ]),
            ],
            'duration_minutes' => [
                'required',
                'integer',
                'min:5',
                'max:360',
            ],
            'total_marks' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],
            'use_curriculum' => [
                'sometimes',
                'boolean',
            ],
            'use_notes' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The assessment information is invalid.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        $learningUnit = \App\Models\CourseLearningUnit::query()
            ->where('course_id', $course->id)
            ->find($data['course_learning_unit_id']);

        if (! $learningUnit) {
            return $this->sendError(
                'The selected learning unit does not belong to this course.',
                [],
                422
            );
        }

        $indicativeContent =
            \App\Models\CourseIndicativeContent::query()
                ->where(
                    'course_learning_unit_id',
                    $learningUnit->id
                )
                ->find(
                    $data[
                        'course_indicative_content_id'
                    ]
                );

        if (! $indicativeContent) {
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

        $data['learning_unit_title'] =
            $learningUnit->title;

        $data['indicative_content_title'] =
            $indicativeContent->title;

        $useCurriculum = $data['use_curriculum'] ?? true;
        $useNotes = $data['use_notes'] ?? true;

        if (! $useCurriculum && ! $useNotes) {
            return $this->sendError(
                'Select the curriculum, notes, or both.',
                [],
                422
            );
        }

        if ($useCurriculum && ! $course->curriculum_path) {
            return $this->sendError(
                'This course does not have a curriculum.',
                [],
                422
            );
        }

        if ($useNotes && ! $course->notes_path) {
            return $this->sendError(
                'This course does not have course notes.',
                [],
                422
            );
        }

        try {
            $generated = $gemini->generate(
                $course,
                $data
            );

            $result = DB::transaction(
                function () use (
                    $request,
                    $course,
                    $data,
                    $generated,
                    $learningUnit,
                    $indicativeContent
                ) {
                    $aiAssessment =
                        $generated['assessment'];

                    $assessment = Assessment::create([
                        'course_id' => $course->id,
                        'teacher_id' =>
                            $request->user()->id,
                        'school_class_id' =>
                            $data['school_class_id'],
                        'course_learning_unit_id' =>
                            $learningUnit->id,
                        'course_indicative_content_id' =>
                            $indicativeContent->id,
                        'type' => $data['type'],
                        'title' =>
                            $aiAssessment['title']
                            ?? $data['title'],
                        'difficulty' =>
                            $data['difficulty'],
                        'instructions' =>
                            $aiAssessment['instructions']
                            ?? null,
                        'duration_minutes' =>
                            $aiAssessment['duration_minutes']
                            ?? $data['duration_minutes'],
                        'total_marks' =>
                            $aiAssessment['total_marks']
                            ?? $data['total_marks'],
                        'question_count' => count(
                            $aiAssessment['questions']
                        ),
                        'source_documents' =>
                            $generated['documents'],
                        'ai_model' => $generated['model'],
                        'status' => 'draft',
                    ]);

                    foreach (
                        $aiAssessment['questions']
                        as $index => $question
                    ) {
                        $assessment->questions()->create([
                            'type' => $question['type'],
                            'question' =>
                                trim($question['question']),
                            'options' =>
                                $question['options'] ?? [],
                            'correct_answer' =>
                                $question['correct_answer']
                                ?? null,
                            'explanation' =>
                                $question['explanation']
                                ?? null,
                            'marks' =>
                                max(
                                    1,
                                    (int) (
                                        $question['marks']
                                        ?? 1
                                    )
                                ),
                            'position' => $index + 1,
                        ]);
                    }

                    return $assessment->load([
                        'course:id,name,code',
                        'teacher:id,name,email',
                        'questions',
                    ]);
                }
            );

            return $this->sendCreated(
                $result,
                'Assessment generated and saved as a draft.'
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

    public function show(
        Request $request,
        Assessment $assessment
    ): JsonResponse {
        if ($response = $this->authorizeAssessment(
            $request,
            $assessment
        )) {
            return $response;
        }

        return $this->sendResponse(
            $assessment->load([
                'course:id,name,code',
                'teacher:id,name,email',
                'questions',
            ]),
            'Assessment retrieved successfully.'
        );
    }

    public function destroy(
        Request $request,
        Assessment $assessment
    ): JsonResponse {
        if ($response = $this->authorizeAssessment(
            $request,
            $assessment
        )) {
            return $response;
        }

        if ($assessment->status === 'published') {
            return $this->sendError(
                'A published assessment cannot be deleted.',
                [],
                422
            );
        }

        $assessment->delete();

        return $this->sendResponse(
            null,
            'Assessment deleted successfully.'
        );
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

    private function authorizeCourse(
        Request $request,
        Course $course
    ): ?JsonResponse {
        $user = $request->user();

        if (! $user) {
            return $this->sendError(
                'Unauthenticated.',
                [],
                401
            );
        }

        if (in_array($user->role, [
            'admin',
            'headmaster',
            'director_of_studies',
        ], true)) {
            return null;
        }

        if ($user->role !== 'teacher') {
            return $this->sendError(
                'You are not allowed to generate assessments.',
                [],
                403
            );
        }

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

        return null;
    }

    private function authorizeAssessment(
        Request $request,
        Assessment $assessment
    ): ?JsonResponse {
        $user = $request->user();

        if (in_array($user->role, [
            'admin',
            'headmaster',
            'director_of_studies',
        ], true)) {
            return null;
        }

        if (
            $user->role === 'teacher' &&
            $assessment->teacher_id === $user->id
        ) {
            return null;
        }

        return $this->sendError(
            'You are not allowed to manage this assessment.',
            [],
            403
        );
    }
}
