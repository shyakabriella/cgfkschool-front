<?php

namespace App\Http\Controllers\API;

use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceLookupController extends BaseController
{
    private function allowed(Request $request): bool
    {
        return in_array($request->user()->role, [
            'headmaster',
            'accountant',
        ], true);
    }

    public function classes(Request $request): JsonResponse
    {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to access finance classes.',
                null,
                403
            );
        }

        $classes = SchoolClass::query()
            ->select([
                'id',
                'department_program_id',
                'name',
                'code',
                'level',
                'status',
            ])
            ->with([
                'program:id,department_id,name,code,type',
                'program.department:id,name,code',
            ])
            ->where('status', 'active')
            ->withCount([
                'students' => fn ($query) =>
                    $query->where('status', 'active'),
            ])
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            $classes,
            'Finance classes retrieved successfully.'
        );
    }

    public function students(
        Request $request,
        SchoolClass $schoolClass
    ): JsonResponse {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to access finance students.',
                null,
                403
            );
        }

        $students = $schoolClass->students()
            ->select([
                'id',
                'student_id',
                'school_class_id',
                'first_name',
                'last_name',
                'parent_contact',
                'status',
            ])
            ->where('status', 'active')
            ->orderBy('student_id')
            ->get();

        return $this->sendResponse(
            [
                'school_class' => $schoolClass->load([
                    'program:id,department_id,name,code,type',
                    'program.department:id,name,code',
                ]),
                'students' => $students,
            ],
            'Finance class students retrieved successfully.'
        );
    }
}
