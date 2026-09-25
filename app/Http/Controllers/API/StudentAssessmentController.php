<?php

namespace App\Http\Controllers\API;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentAssessmentController extends BaseController
{
    private const QUESTION_SECONDS = 30;

    private const NETWORK_TOLERANCE_SECONDS = 5;

    public function start(
        Request $request,
        AssessmentAssignment $assignment
    ): JsonResponse {
        $student = $this->authenticatedStudent(
            $request
        );

        if ($student instanceof JsonResponse) {
            return $student;
        }

        if ($assignment->student_id !== $student->id) {
            return $this->sendError(
                'This assessment is not assigned to you.',
                [],
                403
            );
        }

        $assignment->load([
            'assessment.course:id,name,code',
            'assessment.teacher:id,name',
        ]);

        $assessment = $assignment->assessment;

        if (
            ! $assessment
            || $assessment->status !== 'published'
        ) {
            return $this->sendError(
                'This assessment is not available.',
                [],
                422
            );
        }

        if (
            $assignment->due_at
            && now()->isAfter($assignment->due_at)
            && $assignment->status !== 'submitted'
        ) {
            return $this->sendError(
                'The submission deadline has passed.',
                [],
                422
            );
        }

        $questionCount =
            AssessmentQuestion::query()
                ->where(
                    'assessment_id',
                    $assessment->id
                )
                ->count();

        if ($questionCount === 0) {
            return $this->sendError(
                'This assessment does not have questions.',
                [],
                422
            );
        }

        $attempt = DB::table(
            'assessment_attempts'
        )
            ->where(
                'assessment_assignment_id',
                $assignment->id
            )
            ->first();

        if (! $attempt) {
            $attemptId = DB::table(
                'assessment_attempts'
            )->insertGetId([
                'assessment_assignment_id' =>
                    $assignment->id,
                'student_id' => $student->id,
                'current_position' => 1,
                'question_started_at' => now(),
                'started_at' => now(),
                'status' => 'in_progress',
                'score' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $attempt = DB::table(
                'assessment_attempts'
            )->find($attemptId);
        }

        if ($attempt->status === 'submitted') {
            return $this->result(
                $request,
                $assignment
            );
        }

        if (! $attempt->question_started_at) {
            DB::table('assessment_attempts')
                ->where('id', $attempt->id)
                ->update([
                    'question_started_at' => now(),
                    'updated_at' => now(),
                ]);

            $attempt = DB::table(
                'assessment_attempts'
            )->find($attempt->id);
        }

        return $this->sendResponse(
            $this->attemptPayload(
                $assignment,
                $attempt
            ),
            'Assessment attempt started.'
        );
    }

    public function answer(
        Request $request,
        AssessmentAssignment $assignment
    ): JsonResponse {
        $student = $this->authenticatedStudent(
            $request
        );

        if ($student instanceof JsonResponse) {
            return $student;
        }

        if ($assignment->student_id !== $student->id) {
            return $this->sendError(
                'This assessment is not assigned to you.',
                [],
                403
            );
        }

        $validator = Validator::make(
            $request->all(),
            [
                'question_id' => [
                    'required',
                    'integer',
                    'exists:assessment_questions,id',
                ],
                'answer' => [
                    'nullable',
                    'string',
                    'max:20000',
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->sendError(
                'The answer is invalid.',
                $validator->errors(),
                422
            );
        }

        $response = DB::transaction(
            function () use (
                $request,
                $assignment,
                $student
            ) {
                $attempt = DB::table(
                    'assessment_attempts'
                )
                    ->where(
                        'assessment_assignment_id',
                        $assignment->id
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $attempt
                    || $attempt->status !== 'in_progress'
                ) {
                    return $this->sendError(
                        'There is no active assessment attempt.',
                        [],
                        422
                    );
                }

                $question =
                    AssessmentQuestion::query()
                        ->where(
                            'assessment_id',
                            $assignment->assessment_id
                        )
                        ->where(
                            'position',
                            $attempt->current_position
                        )
                        ->first();

                if (
                    ! $question
                    || $question->id
                        !== $request->integer(
                            'question_id'
                        )
                ) {
                    return $this->sendError(
                        'This is not the current question.',
                        [],
                        409
                    );
                }

                $elapsed = now()->diffInSeconds(
                    $attempt->question_started_at
                );

                $timedOut =
                    $elapsed
                    > (
                        self::QUESTION_SECONDS
                        + self::NETWORK_TOLERANCE_SECONDS
                    );

                $answer = $timedOut
                    ? null
                    : trim(
                        (string) $request->input(
                            'answer',
                            ''
                        )
                    );

                [
                    $isCorrect,
                    $marksAwarded,
                ] = $this->markObjectiveQuestion(
                    $question,
                    $answer,
                    $timedOut
                );

                DB::table('assessment_answers')
                    ->updateOrInsert(
                        [
                            'assessment_attempt_id' =>
                                $attempt->id,
                            'assessment_question_id' =>
                                $question->id,
                        ],
                        [
                            'answer' =>
                                $answer !== ''
                                    ? $answer
                                    : null,
                            'timed_out' => $timedOut,
                            'is_correct' => $isCorrect,
                            'marks_awarded' =>
                                $marksAwarded,
                            'shown_at' =>
                                $attempt
                                    ->question_started_at,
                            'answered_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                $nextPosition =
                    $attempt->current_position + 1;

                $nextQuestion =
                    AssessmentQuestion::query()
                        ->where(
                            'assessment_id',
                            $assignment->assessment_id
                        )
                        ->where(
                            'position',
                            $nextPosition
                        )
                        ->exists();

                if ($nextQuestion) {
                    DB::table('assessment_attempts')
                        ->where('id', $attempt->id)
                        ->update([
                            'current_position' =>
                                $nextPosition,
                            'question_started_at' =>
                                now(),
                            'updated_at' => now(),
                        ]);
                } else {
                    $score = (float) DB::table(
                        'assessment_answers'
                    )
                        ->where(
                            'assessment_attempt_id',
                            $attempt->id
                        )
                        ->sum('marks_awarded');

                    DB::table('assessment_attempts')
                        ->where('id', $attempt->id)
                        ->update([
                            'status' => 'submitted',
                            'score' => $score,
                            'submitted_at' => now(),
                            'question_started_at' =>
                                null,
                            'updated_at' => now(),
                        ]);

                    $assignment->update([
                        'status' => 'submitted',
                        'score' => $score,
                        'submitted_at' => now(),
                    ]);
                }

                return null;
            }
        );

        if ($response instanceof JsonResponse) {
            return $response;
        }

        $attempt = DB::table(
            'assessment_attempts'
        )
            ->where(
                'assessment_assignment_id',
                $assignment->id
            )
            ->first();

        if ($attempt->status === 'submitted') {
            return $this->result(
                $request,
                $assignment->fresh()
            );
        }

        return $this->sendResponse(
            $this->attemptPayload(
                $assignment->fresh(),
                $attempt
            ),
            'Answer saved successfully.'
        );
    }

    public function result(
        Request $request,
        AssessmentAssignment $assignment
    ): JsonResponse {
        $student = $this->authenticatedStudent(
            $request
        );

        if ($student instanceof JsonResponse) {
            return $student;
        }

        if ($assignment->student_id !== $student->id) {
            return $this->sendError(
                'This assessment is not assigned to you.',
                [],
                403
            );
        }

        $assignment->load([
            'assessment.course:id,name,code',
        ]);

        $attempt = DB::table(
            'assessment_attempts'
        )
            ->where(
                'assessment_assignment_id',
                $assignment->id
            )
            ->first();

        if (
            ! $attempt
            || $attempt->status !== 'submitted'
        ) {
            return $this->sendError(
                'This assessment has not been submitted.',
                [],
                422
            );
        }

        $manualPending = DB::table(
            'assessment_answers'
        )
            ->where(
                'assessment_attempt_id',
                $attempt->id
            )
            ->whereNull('marks_awarded')
            ->count();

        return $this->sendResponse([
            'submitted' => true,
            'assignment_id' => $assignment->id,
            'assessment' => [
                'title' =>
                    $assignment->assessment->title,
                'type' =>
                    $assignment->assessment->type,
                'course' =>
                    $assignment->assessment->course,
                'total_marks' =>
                    $assignment->assessment
                        ->total_marks,
            ],
            'score' => (float) $attempt->score,
            'manual_marking_pending' =>
                $manualPending > 0,
            'submitted_at' =>
                $attempt->submitted_at,
        ], 'Assessment result retrieved.');
    }

    private function attemptPayload(
        AssessmentAssignment $assignment,
        object $attempt
    ): array {
        $assignment->loadMissing([
            'assessment.course:id,name,code',
        ]);

        $assessment = $assignment->assessment;

        $question =
            AssessmentQuestion::query()
                ->where(
                    'assessment_id',
                    $assessment->id
                )
                ->where(
                    'position',
                    $attempt->current_position
                )
                ->first();

        return [
            'submitted' => false,
            'assignment_id' => $assignment->id,
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'type' => $assessment->type,
                'instructions' =>
                    $assessment->instructions,
                'question_count' =>
                    $assessment->question_count,
                'total_marks' =>
                    $assessment->total_marks,
                'course' => $assessment->course,
            ],
            'progress' => [
                'current' =>
                    (int) $attempt->current_position,
                'total' =>
                    (int) $assessment->question_count,
            ],
            'question' => $question
                ? [
                    'id' => $question->id,
                    'type' => $question->type,
                    'question' =>
                        $question->question,
                    'options' =>
                        $question->options ?? [],
                    'marks' => $question->marks,
                ]
                : null,
            'question_seconds' =>
                self::QUESTION_SECONDS,
            'question_started_at' =>
                $attempt->question_started_at,
            'expires_at' => now()
                ->parse(
                    $attempt->question_started_at
                )
                ->addSeconds(
                    self::QUESTION_SECONDS
                ),
        ];
    }

    private function markObjectiveQuestion(
        AssessmentQuestion $question,
        ?string $answer,
        bool $timedOut
    ): array {
        if (
            $timedOut
            || ! in_array(
                $question->type,
                [
                    'multiple_choice',
                    'true_false',
                ],
                true
            )
        ) {
            return [null, null];
        }

        $normalize = fn (?string $value) =>
            mb_strtolower(
                trim((string) $value)
            );

        $correct = $normalize(
            $question->correct_answer
        ) === $normalize($answer);

        return [
            $correct,
            $correct
                ? (float) $question->marks
                : 0,
        ];
    }

    private function authenticatedStudent(
        Request $request
    ): mixed {
        $user = $request->user();

        if (! $user || $user->role !== 'student') {
            return $this->sendError(
                'Only students can take assessments.',
                [],
                403
            );
        }

        $student = $user->student;

        if (! $student) {
            return $this->sendError(
                'No student registration is connected to this account.',
                [],
                404
            );
        }

        return $student;
    }
}
