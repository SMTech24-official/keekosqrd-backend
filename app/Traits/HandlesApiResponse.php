<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

trait HandlesApiResponse
{
    public function safeCall(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            //throw $th;
            // Log the error for debugging purposes
            $this->logError($e);

            // Returned a structured error response
            return $this->errorResponse(
                'Something went wrong. Please try again.',
                500,
                $e->getMessage()
            );
        }
    }

    // For returning success JSON response
    public function successResponse(string $message, array $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
            'status_code' => $status
        ], $status);
    }

    // For returning error JSON response
    public function errorResponse(string $message, int $status = 500, string $debugMessage = null): JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'status_code' => $status,
            'error' => app()->environment('production') ? null : $debugMessage, // show detailed error message only in production
        ], $status);
    }

    // For logging errors
    public function logError(\Throwable $e): void
    {
        Log::error('Exception caught: ', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
