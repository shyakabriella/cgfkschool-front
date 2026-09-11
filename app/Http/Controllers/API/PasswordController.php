<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class PasswordController extends BaseController
{
    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)
                    ->mixedCase()
                    ->numbers(),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The provided password information is invalid.',
                $validator->errors(),
                422
            );
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->sendError(
                'Your current password is incorrect.',
                [
                    'current_password' => [
                        'Your current password is incorrect.',
                    ],
                ],
                422
            );
        }

        if (Hash::check($request->password, $user->password)) {
            return $this->sendError(
                'Your new password must be different from your current password.',
                [
                    'password' => [
                        'Please choose a different password.',
                    ],
                ],
                422
            );
        }

        $user->update([
            'password' => $request->password,
            'must_change_password' => false,
        ]);

        return $this->sendResponse(
            [
                'user' => $user->fresh(),
            ],
            'Password changed successfully.'
        );
    }
}
