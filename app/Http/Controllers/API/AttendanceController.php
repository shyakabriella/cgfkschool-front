<?php

namespace App\Http\Controllers\API;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\TeacherAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AttendanceController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Attendance::query()
            ->with([
                'teacher:id,name,email',
                'schoolClass:id,name,code',
                'course:id,name,code',
                'records.student:id,student_id,first_name,last_name',
            ])
            ->latest('attendance_date');

        if ($user->role === 'teacher') {
            $query->where('teacher_id', $user->id);
        }

        if ($request->filled('school_class_id')) {
            $query->where(
                'school_class_id',
                $request->integer('school_class_id')
            );
        }

        if ($request->filled('course_id')) {
            $query->where(
                'course_id',
                $request->integer('course_id')
            );
        }

        if ($request->filled('attendance_date')) {
            $query->whereDate(
                'attendance_date',
                $request->input('attendance_date')
            );
        }

        return $this->sendResponse(
            $query->paginate(
                min($request->integer('per_page', 20), 100)
            ),
            'Attendance records retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'teacher_assignment_id' => [
                'required',
                'integer',
                'exists:teacher_assignments,id',
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
            ],
            'attendance_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'students' => [
                'required',
                'array',
                'min:1',
            ],
            'students.*.student_id' => [
                'required',
                'integer',
                'distinct',
                'exists:students,id',
            ],
            'students.*.status' => [
                'required',
                Rule::in([
                    'present',
                    'absent',
                    'late',
                    'excused',
                ]),
            ],
            'students.*.remarks' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The attendance information is invalid.',
                $validator->errors(),
                422
            );
        }

        $user = $request->user();

        if ($user->role !== 'teacher') {
            return $this->sendError(
                'Only teachers can submit student attendance.',
                null,
                403
            );
        }

        $assignment = TeacherAssignment::query()
            ->whereKey($request->integer('teacher_assignment_id'))
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            return $this->sendError(
                'The selected teaching assignment is not active.',
                null,
                422
            );
        }

        if ($assignment->teacher_id !== $user->id) {
            return $this->sendError(
                'You cannot take attendance for another teacher.',
                null,
                403
            );
        }

        if (
            $assignment->school_class_id !==
            $request->integer('school_class_id')
        ) {
            return $this->sendError(
                'The selected class does not belong to this assignment.',
                null,
                422
            );
        }

        if (
            $assignment->course_id !==
            $request->integer('course_id')
        ) {
            return $this->sendError(
                'The selected course does not belong to this assignment.',
                null,
                422
            );
        }

        $studentIds = collect($request->input('students'))
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $validStudentIds = Student::query()
            ->where('school_class_id', $assignment->school_class_id)
            ->whereIn('id', $studentIds)
            ->pluck('id');

        if ($validStudentIds->count() !== $studentIds->count()) {
            return $this->sendError(
                'One or more students do not belong to the selected class.',
                null,
                422
            );
        }

        $attendance = DB::transaction(function () use (
            $request,
            $assignment,
            $user
        ) {
            $attendance = Attendance::updateOrCreate(
                [
                    'teacher_assignment_id' => $assignment->id,
                    'attendance_date' => $request->input(
                        'attendance_date'
                    ),
                ],
                [
                    'school_class_id' => $assignment->school_class_id,
                    'course_id' => $assignment->course_id,
                    'teacher_id' => $user->id,
                ]
            );

            $attendance->records()->delete();

            foreach ($request->input('students') as $student) {
                $attendance->records()->create([
                    'student_id' => $student['student_id'],
                    'status' => $student['status'],
                    'remarks' => filled($student['remarks'] ?? null)
                        ? trim($student['remarks'])
                        : null,
                ]);
            }

            return $attendance;
        });

        $attendance->load([
            'teacher:id,name,email',
            'schoolClass:id,name,code',
            'course:id,name,code',
            'records.student:id,student_id,first_name,last_name',
        ]);

        return $this->sendResponse(
            $attendance,
            'Attendance saved successfully.'
        );
    }

    public function show(
        Request $request,
        Attendance $attendance
    ): JsonResponse {
        $user = $request->user();

        if (
            $user->role === 'teacher' &&
            $attendance->teacher_id !== $user->id
        ) {
            return $this->sendError(
                'You cannot view another teacher\'s attendance.',
                null,
                403
            );
        }

        $attendance->load([
            'teacher:id,name,email',
            'schoolClass:id,name,code',
            'course:id,name,code',
            'records.student:id,student_id,first_name,last_name',
        ]);

        return $this->sendResponse(
            $attendance,
            'Attendance retrieved successfully.'
        );
    }
}
