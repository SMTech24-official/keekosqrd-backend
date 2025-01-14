<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisterUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        try {
            \Log::info('Processing RegisterUser with data:', $this->data);

            $user = User::create([
                'first_name' => $this->data['first_name'] ?? '',
                'last_name' => $this->data['last_name'] ?? '',
                'email' => $this->data['email'] ?? '',
                'password' => Hash::make($this->data['password'] ?? ''),
                'is_approved' => 0, // User is not approved by default
                'is_admin' => $this->data['is_admin'] ?? 0, // Optional admin flag
            ]);

            \Log::info('User created successfully:', ['user' => $user->toArray()]);

            $token = JWTAuth::claims([
                'role' => $user->is_admin ? 'admin' : 'user',
            ])->fromUser($user);

            \Log::info('JWT token generated successfully.');

            return [
                'token' => $token,
                'user' => $user->toArray(),
            ];
        } catch (\Exception $e) {
            \Log::error('Error in RegisterUser job:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'error' => 'Failed to register user',
            ];
        }
    }


    public function getResult()
    {
        return $this->handle(); // Invoke handle method to return the result directly
    }
}
