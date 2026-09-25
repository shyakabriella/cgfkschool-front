<?php

namespace App\Http\Controllers\API;

use App\Models\Assessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MarksheetController extends BaseController
{
    public function index(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (
            ! $user
            || ! in_array(
                $user->role,
                [
                    'admin',
                    'headmaster',
                    'director_of_studies',
                    'teacher',
                ],
                true
            )
        ) {
            return $this->sendError(
                'You are not allowed to view marksheets.',
                [],
                403
            );
        }

        $validator = Validator::make(
            $request->all(),
            [
                'course_id' => [
                    'nullable',
                    'integer',
                    'exists:courses,id',
                ],
                'type' => [
                    'nullable',
                    Rule::in([
                        'quiz',
                        'exam',
                        'assignment',
                    ]),
                ],
                'assessment_id' => [
                    'nullable',
                    'integer',
                    'exists:assessments,id',
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->sendError(
                'The marksheet filters are invalid.',
                $validator->errors(),
                422
            );
        }

        $assessmentQuery = Assessment::query()
            ->with([
                'course:id,name,code',
                'schoolClass:id,name,code,level',
            ])
            ->withCount([
                'questions',
            ])
            ->where('status', 'published');

        if ($user->role === 'teacher') {
            $assessmentQuery->where(
                'teacher_id',
                $user->id
            );
        }

        if ($request->filled('course_id')) {
            $assessmentQuery->where(
                'course_id',
                $request->integer('course_id')
            );
        }

        if ($request->filled('type')) {
            $assessmentQuery->where(
                'type',
                $request->type
            );
        }

        $assessments = $assessmentQuery
            ->latest()
            ->get([
                'id',
                'course_id',
                'teacher_id',
                'school_class_id',
                'title',
                'type',
                'total_marks',
                'question_count',
                'created_at',
            ]);

        $marksheet = null;

        if ($request->filled('assessment_id')) {
            $assessment = Assessment::query()
                ->with([
                    'course:id,name,code',
                    'teacher:id,name',
                    'schoolClass:id,name,code,level',
                ])
                ->whereKey(
                    $request->integer(
                        'assessment_id'
                    )
                )
                ->when(
                    $user->role === 'teacher',
                    fn ($query) => $query->where(
                        'teacher_id',
                        $user->id
                    )
                )
                ->first();

            if (! $assessment) {
                return $this->sendError(
                    'The selected assessment is not available to you.',
                    [],
                    404
                );
            }

            $students = DB::table(
                'assessment_assignments as assignments'
            )
                ->join(
                    'students',
                    'students.id',
                    '=',
                    'assignments.student_id'
                )
                ->leftJoin(
                    'assessment_attempts as attempts',
                    'attempts.assessment_assignment_id',
                    '=',
                    'assignments.id'
                )
                ->where(
                    'assignments.assessment_id',
                    $assessment->id
                )
                ->orderBy('students.first_name')
                ->orderBy('students.last_name')
                ->select([
                    'assignments.id as assignment_id',
                    'students.id as student_record_id',
                    'students.student_id',
                    'students.first_name',
                    'students.last_name',
                    'assignments.status',
                    'assignments.submitted_at',
                    'assignments.score as assignment_score',
                    'attempts.score as attempt_score',
                    'attempts.status as attempt_status',
                ])
                ->get()
                ->map(function ($record) use (
                    $assessment
                ) {
                    $score =
                        $record->attempt_score
                        ?? $record->assignment_score;

                    $percentage =
                        $score !== null
                        && $assessment->total_marks > 0
                            ? round(
                                (
                                    (float) $score
                                    / $assessment->total_marks
                                ) * 100,
                                1
                            )
                            : null;

                    return [
                        'assignment_id' =>
                            $record->assignment_id,
                        'student_id' =>
                            $record->student_id,
                        'student_name' => trim(
                            $record->first_name
                            . ' '
                            . $record->last_name
                        ),
                        'status' =>
                            $record->attempt_status
                            ?? $record->status,
                        'score' =>
                            $score !== null
                                ? (float) $score
                                : null,
                        'total_marks' =>
                            (float) $assessment
                                ->total_marks,
                        'percentage' => $percentage,
                        'submitted_at' =>
                            $record->submitted_at,
                    ];
                })
                ->values();

            $marksheet = [
                'assessment' => [
                    'id' => $assessment->id,
                    'title' =>
                        $assessment->title,
                    'type' =>
                        $assessment->type,
                    'course' =>
                        $assessment->course,
                    'teacher' =>
                        $assessment->teacher,
                    'school_class' =>
                        $assessment->schoolClass,
                    'total_marks' =>
                        $assessment->total_marks,
                ],
                'students' => $students,
                'summary' => [
                    'students' =>
                        $students->count(),
                    'submitted' =>
                        $students
                            ->where(
                                'status',
                                'submitted'
                            )
                            ->count(),
                    'pending' =>
                        $students
                            ->where(
                                'status',
                                '!=',
                                'submitted'
                            )
                            ->count(),
                    'average' =>
                        round(
                            (float) $students
                                ->whereNotNull(
                                    'percentage'
                                )
                                ->avg(
                                    'percentage'
                                ),
                            1
                        ),
                ],
            ];
        }

        return $this->sendResponse([
            'assessments' => $assessments,
            'marksheet' => $marksheet,
        ], 'Marksheet information retrieved successfully.');
    }
}
