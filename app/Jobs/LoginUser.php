<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Traits\HandlesApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class LoginUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HandlesApiResponse;

    protected $credentials;

    public function __construct($credentials)
    {
        $this->credentials = $credentials;
    }

    public function handle()
    {
        try {
            Log::info('Attempting login with credentials:', $this->credentials);

            // Attempt login
            if (!$token = JWTAuth::attempt($this->credentials)) {
                Log::error('Login failed: Invalid credentials');
                return [
                    'error' => 'Invalid credentials',
                    'status_code' => 401
                ];
            }

            // Get authenticated user
            $user = auth()->user();

            if (!$user) {
                Log::error('Login failed: Authenticated user not found');
                return [
                    'error' => 'User not found',
                    'status_code' => 404
                ];
            }

            Log::info('User authenticated successfully:', ['user' => $user->toArray()]);

            // Create token with claims
            $token = JWTAuth::claims([
                'iss' => config('app.url') . '/api/login', // Ensure app URL is correct
                'role' => $user->is_admin ? 'admin' : 'user',
            ])->fromUser($user);

            Log::info('Token generated successfully:', ['token' => $token]);

            // Return the result directly
            return [
                'token' => $token,
                'user' => $user->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Error in LoginUser job:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'error' => 'Unexpected error occurred.',
                'status_code' => 500
            ];
        }
    }
}
