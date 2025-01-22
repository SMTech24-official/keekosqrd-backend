<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\HandlesApiResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    use HandlesApiResponse;
    public function registerSubscription($paymentId, $priceId, $userData, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'zip_code' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Step 2: Check Validation Errors
        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return $this->errorResponse('Validation error', 400, $validator->errors());
        }

        // Step 3: Prepare User Data
        $data = $request->only([
            'first_name',
            'last_name',
            'country',
            'city',
            'zip_code',
            'address',
            'email',
        ]);
        $data['password'] = Hash::make($request->password);

        // Step 4: Create User
        $user = User::create($data);

        $customer = \Stripe\Customer::create([
            'email' => $user->email,
            'name' => $user->first_name, // Assuming 'name' exists in your user table
        ]);

        // Log User Creation
        \Log::info('User created successfully:', ['user' => $user->toArray()]);

        // Step 5: Generate JWT Token
        $token = JWTAuth::fromUser($user);

        // Log Token Generation
        \Log::info('JWT token generated for user:', ['user_id' => $user->id]);
    }
}
