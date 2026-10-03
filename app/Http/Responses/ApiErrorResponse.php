<?php

namespace App\Http\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiErrorResponse
{
    public static function fromException(Throwable $exception): JsonResponse
    {
        $status = match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };

        [$code, $message] = match ($status) {
            401 => ['UNAUTHENTICATED', 'Authentication is required.'],
            403 => ['FORBIDDEN', 'You are not authorized to perform this action.'],
            404 => ['NOT_FOUND', 'The requested resource was not found.'],
            405 => ['METHOD_NOT_ALLOWED', 'The request method is not allowed.'],
            422 => ['VALIDATION_ERROR', 'The given data was invalid.'],
            429 => ['TOO_MANY_REQUESTS', 'Too many requests. Try again later.'],
            500 => ['INTERNAL_SERVER_ERROR', 'An unexpected error occurred.'],
            default => ['HTTP_ERROR', 'The request could not be completed.'],
        };

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => ['code' => $code],
            'errors' => $exception instanceof ValidationException
                ? $exception->errors()
                : (object) [],
        ], $status);
    }
}
