<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterController extends BaseController
{


    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'unique:users,phone',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->numbers(),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The provided information is invalid.',
                $validator->errors(),
                422
            );
        }

        $user = User::create([
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
            'phone' => trim($request->phone),
            'password' => $request->password,
            'role' => 'teacher',
            'status' => 'active',
            'must_change_password' => false,
        ]);

        return $this->sendCreated([
            'user' => $user,
        ], 'User registered successfully.');
    }

    public function staff(Request $request): JsonResponse
    {
        if ($response = $this->authorizeStaffManagement($request)) {
            return $response;
        }

        $staff = User::query()
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'role',
                'status',
                'must_change_password',
                'last_login_at',
                'created_at',
            ])
            ->whereIn(
                'role',
                DB::table('roles')->pluck('slug')
            )
            ->when(
                $request->filled('role'),
                fn ($query) => $query->where('role', $request->role)
            )
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->status)
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(
                min(max($request->integer('per_page', 15), 5), 100)
            );

        return $this->sendResponse(
            $staff,
            'Staff retrieved successfully.'
        );
    }

    public function staffRoles(Request $request): JsonResponse
    {
        if ($response = $this->authorizeStaffManagement($request)) {
            return $response;
        }

        $roles = DB::table('roles')
            ->select([
                'id',
                'name',
                'slug',
                'description',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'value' => $role->slug,
                'label' => $role->name,
                'description' => $role->description,
            ])
            ->values();

        return $this->sendResponse(
            $roles,
            'Staff roles retrieved successfully.'
        );
    }

    public function createStaff(Request $request): JsonResponse
    {
        if ($response = $this->authorizeStaffManagement($request)) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:150',
                'unique:users,email',
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'unique:users,phone',
            ],
            'role' => [
                'required',
                'string',
                'exists:roles,slug',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The provided staff information is invalid.',
                $validator->errors(),
                422
            );
        }

        $user = User::create([
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
            'phone' => trim($request->phone),
            'role' => $request->role,
            'password' => Str::password(40),
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $token = PasswordBroker::broker()->createToken($user);

        $user->sendPasswordResetNotification($token);

        return $this->sendCreated([
            'user' => $user->only([
                'id',
                'name',
                'email',
                'phone',
                'role',
                'status',
                'must_change_password',
                'created_at',
            ]),
            'password_setup_email_sent' => true,
        ], 'Staff created and password setup email sent successfully.');
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The provided login information is invalid.',
                $validator->errors(),
                422
            );
        }

        $login = trim($request->login);

        $user = User::query()
            ->where(function ($query) use ($login) {
                $query
                    ->where(
                        'email',
                        strtolower($login)
                    )
                    ->orWhere('phone', $login)
                    ->orWhereHas(
                        'student',
                        fn ($studentQuery) =>
                            $studentQuery->where(
                                'student_id',
                                strtoupper($login)
                            )
                    );
            })
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->sendError(
                'The email, phone number, or password is incorrect.',
                null,
                401
            );
        }

        if (! $user->isActive()) {
            return $this->sendError(
                'Your account is suspended. Contact the school administration.',
                null,
                403
            );
        }

        $user->update([
            'last_login_at' => now(),
        ]);

        $tokenName = $request->device_name ?: 'school-web';
        $token = $user->createToken($tokenName)->plainTextToken;

        $authenticatedUser = $user->fresh();

        if ($authenticatedUser->role === 'student') {
            $authenticatedUser->load([
                'student.schoolClass:id,name,code,level',
            ]);
        }

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $authenticatedUser,
        ], 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'student') {
            $user->load([
                'student.schoolClass:id,name,code,level',
            ]);
        }

        return $this->sendResponse([
            'user' => $user,
        ], 'Current user retrieved successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->sendResponse(
            null,
            'Logout successful.'
        );
    }

    private function authorizeStaffManagement(
        Request $request
    ): ?JsonResponse {
        $user = $request->user();

        if (! $user) {
            return $this->sendError('Unauthenticated.', [], 401);
        }

        if (! in_array($user->role, ['admin', 'headmaster'], true)) {
            return $this->sendError(
                'You are not allowed to manage staff.',
                [],
                403
            );
        }

        return null;
    }
}
