<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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
            \Log::info('Processing RegisterUser:', $this->data);

            if (empty($this->data['email']) || empty($this->data['password'])) {
                return ['error' => 'Email and password are required.'];
            }

            // Check if email exists
            if (User::where('email', $this->data['email'])->exists()) {
                return ['error' => 'Email already exists.'];
            }

            return DB::transaction(function () {
                // Create user inside transaction
                $user = User::create([
                    'first_name' => $this->data['first_name'],
                    'last_name' => $this->data['last_name'],
                    'country' => $this->data['country'],
                    'city' => $this->data['city'],
                    'zip_code' => $this->data['zip_code'],
                    'address' => $this->data['address'],
                    'email' => $this->data['email'],
                    'password' => Hash::make($this->data['password']),
                    'is_admin' => $this->data['is_admin'] ?? 0,
                ]);

                \Log::info('User created successfully', ['user' => $user]);

                // Generate JWT token
                $token = JWTAuth::claims([
                    'iss' => config('app.url') . '/api/register',
                    'role' => $user->is_admin ? 'admin' : 'user',
                ])->fromUser($user);

                \Log::info('JWT token generated');

                return [
                    'token' => $token,
                    'user' => $user,
                ];
            });
        } catch (\Exception $e) {
            \Log::error('Error in RegisterUser', [
                'message' => $e->getMessage(),
            ]);

            return ['error' => 'Registration failed.'];
        }
    }

    public function getResult()
    {
        return $this->handle(); // Invoke handle method to return the result directly
    }
}
