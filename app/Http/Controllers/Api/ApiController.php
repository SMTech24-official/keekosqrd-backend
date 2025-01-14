<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Jobs\LoginUser;
use App\Traits\AuthTrait;
use App\Jobs\RegisterUser;
use Illuminate\Http\Request;
use App\Traits\HandlesApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Foundation\Bus\DispatchesJobs;

class ApiController extends Controller
{
    use HandlesApiResponse, AuthTrait, DispatchesJobs;

    // User registration
    public function register(Request $request)
    {
        return $this->safeCall(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed:', $validator->errors()->toArray());
                return $this->errorResponse('Validation error', 400, $validator->errors());
            }

            $data = $request->only(['first_name', 'last_name', 'email', 'password']);
            $job = new RegisterUser($data);

            $result = $job->getResult(); // Get the result directly

            \Log::info('RegisterUser job result:', $result);

            if (!is_array($result)) {
                return $this->errorResponse('Unexpected server error', 500);
            }

            if (isset($result['error'])) {
                return $this->errorResponse($result['error'], 401);
            }

            $cookie = cookie('token', $result['token'], 60); // 60 minutes

            return $this->successResponse(
                'User registered successfully but pending need approval by the admin',
                [
                    'token' => $result['token'],
                    'user' => $result['user']
                ]
            )->cookie($cookie);
        });
    }



    // User login
    public function login(Request $request)
    {
        return $this->safeCall(function () use ($request) {
            $credentials = $request->only('email', 'password');

            $job = new LoginUser($credentials);
            $result = $job->handle(); // Directly call the handle method

            // Handle errors from the job
            if (isset($result['error'])) {
                return $this->errorResponse($result['error'], $result['status_code'] ?? 400);
            }

            // Generate cookie
            $cookie = cookie('token', $result['token'], 10080); // 10080 minutes (7 days)

            // Return success response
            return $this->successResponse(
                'Login successful',
                [
                    'token' => $result['token'],
                    'user' => $result['user']
                ]
            )->cookie($cookie);
        });
    }


    // Get authenticated user
    public function getUser()
    {
        return $this->safeCall(function () {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return $this->errorResponse('User not found', 404);
            }

            return $this->successResponse('User retrieved successfully', compact('user'));
        });
    }

    public function logout()
    {
        return $this->safeCall(function () {
            try {
                if (!$token = JWTAuth::getToken()) {
                    return $this->errorResponse('Token not provided', 400);
                }

                JWTAuth::invalidate($token);
                return $this->successResponse('Logout successful');
            } catch (JWTException $e) {
                return $this->errorResponse('Failed to invalidate token', 500);
            }
        });
    }

    public function approveUser(Request $request, User $user)
    {
        return $this->safeCall(function () use ($request, $user) {
            if (!Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }
            $user->update(['is_approved' => 1]);
            return $this->successResponse('User approved successfully');
        });
    }
}
