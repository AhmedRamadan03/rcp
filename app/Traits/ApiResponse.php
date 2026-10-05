<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Return a success JSON response.
     */
    public function successResponse(string $message, mixed $data = null, int $code = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        // Handle JsonResource and ResourceCollection responses
        if ($data instanceof JsonResource) {
            $resourceResponse = $data->response()->getData(true);

            // If it's a paginated response, extract data, meta, and links
            if (isset($resourceResponse['data']) && (isset($resourceResponse['meta']) || isset($resourceResponse['links']))) {
                $response['data'] = $resourceResponse['data'];

                // Handle meta - remove links from meta if it exists
                if (isset($resourceResponse['meta'])) {
                    $meta = $resourceResponse['meta'];
                    // Remove the links array from meta
                    if (isset($meta['links'])) {
                        unset($meta['links']);
                    }
                    $response['meta'] = $meta;
                }

                // Handle root level links if they exist
                if (isset($resourceResponse['links'])) {
                    $response['links'] = $resourceResponse['links'];
                }
            } else {
                // For single resource or non-paginated collection
                $response['data'] = $resourceResponse;
            }
        }
        // Handle regular data
        else if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Return an error JSON response.
     */
    public function apiErrorResponse(string $message,   $data = [], int $code = 400)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json([
             'success' => false,
            'message' => $message,
            'data'=>$data
        ],$code);
    }

    /**
     * Return a 201 Created response.
     */
    public function createdResponse(string $message, mixed $data = null): JsonResponse
    {
        return $this->successResponse($message, $data, Response::HTTP_CREATED);
    }

    /**
     * Return a 204 No Content response.
     */
    public function noContentResponse(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Return a 404 Not Found response.
     */
    public function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a 403 Forbidden response.
     */
    public function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a 401 Unauthorized response.
     */
    public function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a 422 Validation Error response.
     */
    public function validationErrorResponse(string $message = 'Validation failed', mixed $errors = null): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }
}
