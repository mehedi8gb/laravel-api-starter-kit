<?php

namespace App\Exceptions;

use ErrorException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Illuminate\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     * @param Throwable $e
     * @return Response
     * @throws Throwable
     */
    public function render($request, Throwable $e): Response
    {
        // Check for ErrorException related to roles access
        if ($e instanceof ErrorException && str_contains($e->getMessage(), "Attempt to read property \"roles\" on false")) {
            return sendErrorResponse($e, 403); // Forbidden or 401 for Unauthorized
        }

        // Check for ModelNotFoundException and format the error
        if ($e instanceof ModelNotFoundException) {
            return sendErrorResponse($e, 404);
        }

        // Check for ValidationException
        if ($e instanceof ValidationException) {
            $errors = $e->errors();
            $firstErrorMessage = collect($errors)->flatten()->first(); // Get the first error message

            return response()->json([
                'success' => false,
                'message' => $firstErrorMessage,
            ], 422);
        }

        // Fallback to parent render
        return parent::render($request, $e);
    }
}

