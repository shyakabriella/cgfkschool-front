<?php

namespace App\Http\Controllers\API;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClassRepresentativeController extends BaseController
{
    public function index(): JsonResponse
    {
        $classes = SchoolClass::query()
            ->with([
                'representativeTeacher:id,name,email,phone,role,status',
                'program:id,department_id,name,code,type',
                'program.department:id,name,code',
            ])
            ->withCount('students')
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            $classes,
            'Class representatives retrieved successfully.'
        );
    }

    public function update(
        Request $request,
        SchoolClass $schoolClass
    ): JsonResponse {
        if ($response = $this->authorizeManagement($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'teacher_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique(
                    'school_classes',
                    'representative_teacher_id'
                )->ignore($schoolClass->id),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The representative information is invalid.',
                $validator->errors(),
                422
            );
        }

        $teacherId = $request->input('teacher_id');

        if ($teacherId !== null) {
            $teacher = User::query()
                ->whereKey($teacherId)
                ->where('role', 'teacher')
                ->where('status', 'active')
                ->first();

            if (! $teacher) {
                return $this->sendError(
                    'The selected user is not an active teacher.',
                    [],
                    422
                );
            }
        }

        $schoolClass->update([
            'representative_teacher_id' => $teacherId,
        ]);

        $schoolClass->load([
            'representativeTeacher:id,name,email,phone,role,status',
            'program:id,department_id,name,code,type',
            'program.department:id,name,code',
        ]);

        return $this->sendResponse(
            $schoolClass,
            $teacherId
                ? 'Class representative assigned successfully.'
                : 'Class representative removed successfully.'
        );
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
                'You are not allowed to manage class representatives.',
                [],
                403
            );
        }

        return null;
    }
}
