<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BaseAPIController
{
    /**
     * Return a successful response with data
     *
     * @param mixed $data
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Return an error response
     *
     * @param string $message
     * @param int $status
     * @param array $errors
     * @return JsonResponse
     */
    protected function errorResponse(string $message = 'Error', int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $status);
    }

    /**
     * Validate the given request with the given rules.
     *
     * @param Request $request
     * @param array $rules
     * @return array
     * @throws ValidationException
     */
    protected function validateRequest(Request $request, array $rules): array
    {
        return $request->validate($rules);
    }

    /**
     * Format validation errors for API response
     *
     * @param array $errors
     * @return array
     */
    protected function formatValidationErrors(array $errors): array
    {
        $formatted = [];
        foreach ($errors as $field => $messages) {
            $formatted[$field] = implode(', ', $messages);
        }
        return $formatted;
    }

    /**
     * Handle API exceptions
     *
     * @param \Exception $exception
     * @return JsonResponse
     */
    protected function handleException(\Exception $exception): JsonResponse
    {
        $status = $exception instanceof ValidationException ? 422 : 500;

        return $this->errorResponse(
            $exception->getMessage(),
            $status,
            $exception instanceof ValidationException
                ? $this->formatValidationErrors($exception->errors())
                : [$exception->getMessage()]
        );
    }


    /**
     * Return a 403 Forbidden response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Return a 404 Not Found response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function notFound(string $message = 'Not Found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }
}
