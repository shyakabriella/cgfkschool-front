<?php

namespace App\Http\Controllers\API;

use App\Models\Assessment;
use App\Models\AssessmentAssignment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AssessmentAssignmentController extends BaseController
{
    public function students(
        Request $request,
        Assessment $assessment
    ): JsonResponse {
        if ($response = $this->authorizeAssessment(
            $request,
            $assessment
        )) {
            return $response;
        }

        if (! $assessment->school_class_id) {
            return $this->sendError(
                'This assessment does not have an assigned class.',
                [],
                422
            );
        }

        $assignedStudentIds =
            AssessmentAssignment::query()
                ->where(
                    'assessment_id',
                    $assessment->id
                )
                ->pluck('student_id');

        $students = Student::query()
            ->where(
                'school_class_id',
                $assessment->school_class_id
            )
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'id',
                'student_id',
                'first_name',
                'last_name',
                'email',
                'gender',
                'user_id',
            ])
            ->map(function (
                Student $student
            ) use ($assignedStudentIds) {
                return [
                    'id' => $student->id,
                    'student_id' =>
                        $student->student_id,
                    'name' => trim(
                        $student->first_name
                        . ' '
                        . $student->last_name
                    ),
                    'email' => $student->email,
                    'gender' => $student->gender,
                    'has_account' =>
                        $student->user_id !== null,
                    'is_assigned' =>
                        $assignedStudentIds
                            ->contains($student->id),
                ];
            })
            ->values();

        return $this->sendResponse([
            'assessment' => [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'status' => $assessment->status,
                'school_class_id' =>
                    $assessment->school_class_id,
            ],
            'students' => $students,
            'assigned_count' =>
                $assignedStudentIds->count(),
        ], 'Class students retrieved successfully.');
    }

    public function assign(
        Request $request,
        Assessment $assessment
    ): JsonResponse {
        if ($response = $this->authorizeAssessment(
            $request,
            $assessment
        )) {
            return $response;
        }

        if (! $assessment->school_class_id) {
            return $this->sendError(
                'This assessment does not have an assigned class.',
                [],
                422
            );
        }

        $validator = Validator::make(
            $request->all(),
            [
                'assignment_scope' => [
                    'required',
                    Rule::in([
                        'all',
                        'selected',
                    ]),
                ],
                'student_ids' => [
                    'nullable',
                    'array',
                ],
                'student_ids.*' => [
                    'required',
                    'integer',
                    'distinct',
                    'exists:students,id',
                ],
                'due_at' => [
                    'nullable',
                    'date',
                    'after:now',
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->sendError(
                'The assignment information is invalid.',
                $validator->errors(),
                422
            );
        }

        $data = $validator->validated();

        if (
            $data['assignment_scope'] === 'selected'
            && empty($data['student_ids'])
        ) {
            return $this->sendError(
                'Select at least one student.',
                [
                    'student_ids' => [
                        'Select at least one student.',
                    ],
                ],
                422
            );
        }

        $classStudents = Student::query()
            ->where(
                'school_class_id',
                $assessment->school_class_id
            )
            ->where('status', 'active');

        if ($data['assignment_scope'] === 'all') {
            $studentIds = $classStudents
                ->pluck('id')
                ->all();
        } else {
            $requestedStudentIds = collect(
                $data['student_ids']
            )
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $studentIds = $classStudents
                ->whereIn(
                    'id',
                    $requestedStudentIds
                )
                ->pluck('id')
                ->all();

            if (
                count($studentIds)
                !== $requestedStudentIds->count()
            ) {
                return $this->sendError(
                    'One or more selected students do not belong to this class.',
                    [],
                    422
                );
            }
        }

        if (count($studentIds) === 0) {
            return $this->sendError(
                'The selected class does not have active students.',
                [],
                422
            );
        }

        DB::transaction(function () use (
            $request,
            $assessment,
            $studentIds,
            $data
        ) {
            AssessmentAssignment::query()
                ->where(
                    'assessment_id',
                    $assessment->id
                )
                ->where('status', 'assigned')
                ->whereNotIn(
                    'student_id',
                    $studentIds
                )
                ->delete();

            foreach ($studentIds as $studentId) {
                AssessmentAssignment::updateOrCreate(
                    [
                        'assessment_id' =>
                            $assessment->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'assigned_by' =>
                            $request->user()->id,
                        'status' => 'assigned',
                        'assigned_at' => now(),
                        'due_at' =>
                            $data['due_at'] ?? null,
                    ]
                );
            }

            DB::table('assessments')
                ->where('id', $assessment->id)
                ->update([
                    'status' => 'published',
                    'updated_at' => now(),
                ]);
        });

        $assignedStudents = Student::query()
            ->whereIn('id', $studentIds)
            ->orderBy('first_name')
            ->get([
                'id',
                'student_id',
                'first_name',
                'last_name',
            ])
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'student_id' =>
                    $student->student_id,
                'name' => trim(
                    $student->first_name
                    . ' '
                    . $student->last_name
                ),
            ])
            ->values();

        return $this->sendResponse([
            'assessment_id' => $assessment->id,
            'status' => 'published',
            'assignment_scope' =>
                $data['assignment_scope'],
            'assigned_count' =>
                $assignedStudents->count(),
            'students' => $assignedStudents,
            'due_at' => $data['due_at'] ?? null,
        ], 'Assessment assigned successfully.');
    }

    public function myWork(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (! $user || $user->role !== 'student') {
            return $this->sendError(
                'Only students can view assigned work.',
                [],
                403
            );
        }

        $student = $user->student;

        if (! $student) {
            return $this->sendError(
                'A student registration is not connected to this account.',
                [],
                404
            );
        }

        $work = AssessmentAssignment::query()
            ->with([
                'assessment:id,course_id,teacher_id,school_class_id,type,title,difficulty,instructions,duration_minutes,total_marks,question_count,status,created_at',
                'assessment.course:id,name,code',
                'assessment.teacher:id,name',
            ])
            ->where(
                'student_id',
                $student->id
            )
            ->whereHas(
                'assessment',
                fn ($query) =>
                    $query->where(
                        'status',
                        'published'
                    )
            )
            ->latest('assigned_at')
            ->get()
            ->map(function (
                AssessmentAssignment $assignment
            ) {
                $assessment =
                    $assignment->assessment;

                return [
                    'id' => $assignment->id,
                    'assessment_id' =>
                        $assessment->id,
                    'title' => $assessment->title,
                    'type' => $assessment->type,
                    'difficulty' =>
                        $assessment->difficulty,
                    'instructions' =>
                        $assessment->instructions,
                    'duration_minutes' =>
                        $assessment->duration_minutes,
                    'total_marks' =>
                        $assessment->total_marks,
                    'question_count' =>
                        $assessment->question_count,
                    'course' =>
                        $assessment->course,
                    'teacher' =>
                        $assessment->teacher,
                    'status' =>
                        $assignment->status,
                    'assigned_at' =>
                        $assignment->assigned_at,
                    'due_at' =>
                        $assignment->due_at,
                    'submitted_at' =>
                        $assignment->submitted_at,
                    'score' => $assignment->score,
                ];
            })
            ->values();

        return $this->sendResponse(
            $work,
            'Assigned student work retrieved successfully.'
        );
    }

    private function authorizeAssessment(
        Request $request,
        Assessment $assessment
    ): ?JsonResponse {
        $user = $request->user();

        if (! $user) {
            return $this->sendError(
                'Unauthenticated.',
                [],
                401
            );
        }

        if (
            $user->role === 'teacher'
            && $assessment->teacher_id !== $user->id
        ) {
            return $this->sendError(
                'You cannot manage this assessment.',
                [],
                403
            );
        }

        if (! in_array(
            $user->role,
            [
                'admin',
                'headmaster',
                'director_of_studies',
                'teacher',
            ],
            true
        )) {
            return $this->sendError(
                'You are not allowed to assign assessments.',
                [],
                403
            );
        }

        return null;
    }
}
