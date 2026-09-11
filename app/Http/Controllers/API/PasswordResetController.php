<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends BaseController
{
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'email',
                'exists:users,email',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The provided email address is invalid.',
                $validator->errors(),
                422
            );
        }

        $status = Password::sendResetLink([
            'email' => strtolower(trim($request->email)),
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->sendError(
                __($status),
                null,
                422
            );
        }

        return $this->sendResponse(
            null,
            'Password reset link sent successfully.'
        );
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => [
                'required',
                'string',
            ],
            'email' => [
                'required',
                'email',
                'exists:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)
                    ->mixedCase()
                    ->numbers(),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The password information is invalid.',
                $validator->errors(),
                422
            );
        }

        $status = Password::reset(
            $request->only([
                'email',
                'password',
                'password_confirmation',
                'token',
            ]),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->sendError(
                $status === Password::INVALID_TOKEN
                    ? 'This password reset link is invalid or has expired.'
                    : __($status),
                null,
                422
            );
        }

        return $this->sendResponse(
            null,
            'Password reset successfully. You can now log in.'
        );
    }
}
