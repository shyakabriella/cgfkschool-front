<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherLookupController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, [
            'headmaster',
            'director_of_studies',
        ], true)) {
            return $this->sendError(
                'You are not allowed to view the teacher list.',
                null,
                403
            );
        }

        $teachers = User::query()
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'role',
                'status',
            ])
            ->where('role', 'teacher')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            $teachers,
            'Teachers retrieved successfully.'
        );
    }
}
