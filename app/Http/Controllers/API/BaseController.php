<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    /**
     * Return a successful API response.
     */
    protected function sendResponse(
        mixed $data,
        string $message,
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Return an API error response.
     */
    protected function sendError(
        string $message,
        mixed $errors = null,
        int $statusCode = 400
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a response when a resource is created.
     */
    protected function sendCreated(
        mixed $data,
        string $message = 'Record created successfully.'
    ): JsonResponse {
        return $this->sendResponse($data, $message, 201);
    }

    /**
     * Return a response without exposing whether a record exists.
     *
     * This is useful for forgot-password requests.
     */
    protected function sendGenericMessage(
        string $message
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }
}
