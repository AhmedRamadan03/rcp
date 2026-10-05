<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => true,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    }

    public function render($request, $exception)
    {
        if ($exception instanceof AuthorizationException) {
            if($request->is('api/*')) {
                return response()->json([
                    'error' => true,
                    'message' => $exception->getMessage() ?: 'You are not authorized to perform this action.',
                ], $exception->status() ?? 403);
            }
            return back()->with(['unauthorized' => 'You are not authorized to perform this action.']);
        }

        // Handle ModelNotFoundException before Laravel wraps it
        if ($exception instanceof ModelNotFoundException && $request->is('api/*')) {
            $modelName = class_basename($exception->getModel());

            return response()->json([
                'success' => false,
                'message' => "{$modelName} not found.",
            ], 404);
        }

        if ($exception instanceof NotFoundHttpException && $request->is('api/*')) {
            $previous = $exception->getPrevious();

            $modelName = ($previous instanceof ModelNotFoundException)
                ? class_basename($previous->getModel())
                : 'Resource';

            return response()->json([
                'success' => false,
                'message' => "{$modelName} not found.",
            ], 404);
        }


        return parent::render($request, $exception);
    }
}
