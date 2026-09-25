<?php

namespace App\Http\Controllers\API;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class StudentAccountController extends BaseController
{
    public function checkStudentId(
        Request $request
    ): JsonResponse {
        $validator = Validator::make(
            $request->all(),
            [
                'student_id' => [
                    'required',
                    'string',
                    'max:50',
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->sendError(
                'Enter a valid student ID.',
                $validator->errors(),
                422
            );
        }

        $studentId = strtoupper(
            trim((string) $request->student_id)
        );

        $student = Student::query()
            ->select([
                'id',
                'student_id',
                'first_name',
                'last_name',
                'school_class_id',
                'status',
                'user_id',
            ])
            ->with('schoolClass:id,name,code,level')
            ->where('student_id', $studentId)
            ->first();

        if (! $student) {
            return $this->sendError(
                'The student ID was not found.',
                [],
                404
            );
        }

        if ($student->status !== 'active') {
            return $this->sendError(
                'This student registration is not active.',
                [],
                403
            );
        }

        if ($student->user_id) {
            return $this->sendError(
                'An account has already been created for this student ID.',
                [],
                409
            );
        }

        return $this->sendResponse([
            'student' => [
                'student_id' => $student->student_id,
                'name' => trim(
                    $student->first_name
                    . ' '
                    . $student->last_name
                ),
                'class' => $student->schoolClass,
            ],
            'can_create_account' => true,
        ], 'Student ID verified successfully.');
    }

    public function register(
        Request $request
    ): JsonResponse {
        $validator = Validator::make(
            $request->all(),
            [
                'student_id' => [
                    'required',
                    'string',
                    'max:50',
                ],
                'email' => [
                    'required',
                    'email',
                    'max:150',
                    'unique:users,email',
                    'unique:students,email',
                ],
                'gender' => [
                    'required',
                    Rule::in([
                        'male',
                        'female',
                    ]),
                ],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8)
                        ->mixedCase()
                        ->numbers(),
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->sendError(
                'The student account information is invalid.',
                $validator->errors(),
                422
            );
        }

        try {
            $result = DB::transaction(
                function () use ($request) {
                    $studentId = strtoupper(
                        trim((string) $request->student_id)
                    );

                    $student = Student::query()
                        ->where('student_id', $studentId)
                        ->lockForUpdate()
                        ->first();

                    if (! $student) {
                        return [
                            'error' => 'The student ID was not found.',
                            'status' => 404,
                        ];
                    }

                    if ($student->status !== 'active') {
                        return [
                            'error' => 'This student registration is not active.',
                            'status' => 403,
                        ];
                    }

                    if ($student->user_id) {
                        return [
                            'error' => 'An account has already been created for this student ID.',
                            'status' => 409,
                        ];
                    }

                    $email = strtolower(
                        trim((string) $request->email)
                    );

                    $user = User::create([
                        'name' => trim(
                            $student->first_name
                            . ' '
                            . $student->last_name
                        ),
                        'email' => $email,
                        'phone' => null,
                        'role' => 'student',
                        'password' => $request->password,
                        'status' => 'active',
                        'must_change_password' => false,
                    ]);

                    $student->update([
                        'user_id' => $user->id,
                        'email' => $email,
                        'gender' => $request->gender,
                    ]);

                    $student->load(
                        'schoolClass:id,name,code,level'
                    );

                    return [
                        'user' => $user,
                        'student' => $student,
                    ];
                }
            );

            if (isset($result['error'])) {
                return $this->sendError(
                    $result['error'],
                    [],
                    $result['status']
                );
            }

            return $this->sendCreated([
                'user' => $result['user']->only([
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                ]),
                'student' => [
                    'student_id' =>
                        $result['student']->student_id,
                    'first_name' =>
                        $result['student']->first_name,
                    'last_name' =>
                        $result['student']->last_name,
                    'gender' =>
                        $result['student']->gender,
                    'class' =>
                        $result['student']->schoolClass,
                ],
            ], 'Student account created successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->sendError(
                'The student account could not be created.',
                [],
                500
            );
        }
    }
}
