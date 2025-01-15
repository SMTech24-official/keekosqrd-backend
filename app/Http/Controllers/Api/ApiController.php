<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Jobs\LoginUser;
use App\Models\Payment;
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
use Stripe\Stripe;
use Stripe\PaymentMethod;
use Stripe\PaymentIntent;


class ApiController extends Controller
{
    use HandlesApiResponse, AuthTrait, DispatchesJobs;

    public function register(Request $request)
    {
        return $this->safeCall(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'zip_code' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'payment_intent_id' => 'required|string', // Ensure payment_intent_id is passed
                'payment_method' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed:', $validator->errors()->toArray());
                return $this->errorResponse('Validation error', 400, $validator->errors());
            }

            // Confirm payment before proceeding
            $paymentConfirmation = $this->confirmPayment($request);
            if (!$paymentConfirmation['status']) {
                return $this->errorResponse($paymentConfirmation['message'], 400, $paymentConfirmation['error'] ?? []);
            }

            $data = $request->only(['first_name', 'last_name', 'country', 'city', 'zip_code', 'address' ,'email', 'password']);
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
                'User registered successfully',
                [
                    'token' => $result['token'],
                    'user' => $result['user']
                ]
            )->cookie($cookie);
        });
    }

    private function confirmPayment(Request $request): array
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Retrieve the PaymentIntent
            $paymentIntent = \Stripe\PaymentIntent::retrieve($request->payment_intent_id);

            // Confirm the PaymentIntent with a return URL
            $confirmedPaymentIntent = $paymentIntent->confirm([
                'payment_method' => $request->payment_method,
                'return_url' => 'https://yourdomain.com/payment-confirmation', // Replace with your actual return URL
            ]);

            return [
                'status' => true,
                'message' => 'Payment confirmation initiated. Follow redirects if required.',
                'paymentIntent' => $confirmedPaymentIntent,
            ];
        } catch (\Exception $e) {
            \Log::error('Payment confirmation failed: ' . $e->getMessage());

            return [
                'status' => false,
                'message' => 'Payment confirmation failed.',
                'error' => $e->getMessage(),
            ];
        }
    }




    public function createPaymentIntent(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:1',
            ]);

            Stripe::setApiKey(config('services.stripe.secret'));

            $paymentIntent = \Stripe\PaymentIntent::create([
                // 'amount' => $request->amount, // Amount in cents
                'amount' => $request->amount * 100, // Amount in cents
                'currency' => 'usd',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'PaymentIntent created successfully.',
                'data' => [
                    'clientSecret' => $paymentIntent->client_secret,
                    'paymentIntentId' => $paymentIntent->id, // Add this line
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create PaymentIntent.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

}
