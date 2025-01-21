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
use Stripe\Subscription;


class ApiController extends Controller
{
    use HandlesApiResponse, AuthTrait, DispatchesJobs;

    public function register(Request $request)
    {
        return $this->safeCall(function () use ($request) {
            // Step 1: Validate the Request
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

            // Log User Creation
            \Log::info('User created successfully:', ['user' => $user->toArray()]);

            // Step 5: Generate JWT Token
            $token = JWTAuth::fromUser($user);

            // Log Token Generation
            \Log::info('JWT token generated for user:', ['user_id' => $user->id]);

            // Step 6: Return Success Response
            return $this->successResponse(
                'User registered successfully.',
                [
                    'token' => $token,
                    'user' => $user->toArray(),
                ]
            );
        });
    }

    public function createPaymentIntent(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Fetch the price dynamically from Stripe using the Price ID
            $price = \Stripe\Price::retrieve('price_1QhpRzDgYV6zJ17vbxoBnokH'); // Replace with your Stripe Price ID

            // Create a PaymentIntent using the dynamic price
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $price->unit_amount, // Amount is fetched directly from the Price object (in cents)
                'currency' => $price->currency, // Currency is fetched from the Price object
                'payment_method_types' => ['card'], // Accept card payments
            ]);

            return response()->json([
                'status' => true,
                'message' => 'PaymentIntent created successfully',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $price->unit_amount / 100, // Convert cents to dollars
                    'currency' => $price->currency,
                ],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Failed to create PaymentIntent: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create PaymentIntent',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function subscribe(Request $request)
    {
        return $this->safeCall(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|string',
                'price_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation error', 400, $validator->errors()->toJson());
            }

            $user = Auth::user();

            // Create or retrieve the customer
            if (!$user->stripe_customer_id) {
                $customer = \Stripe\Customer::create([
                    'email' => $user->email,
                    'name' => "{$user->first_name} {$user->last_name}",
                ]);
                $user->update(['stripe_customer_id' => $customer->id]);
            } else {
                $customer = \Stripe\Customer::retrieve($user->stripe_customer_id);
            }

            // Attach the payment method to the customer
            $paymentMethod = \Stripe\PaymentMethod::retrieve($request->payment_method);
            $paymentMethod->attach(['customer' => $customer->id]);

            // Update the customer's default payment method
            \Stripe\Customer::update($customer->id, [
                'invoice_settings' => [
                    'default_payment_method' => $request->payment_method,
                ],
            ]);

            // Create a PaymentIntent with automatic payment methods
            $paymentIntent = \Stripe\PaymentIntent::create([
                'customer' => $customer->id,
                'amount' => 1100, // Example amount in cents
                'currency' => 'usd',
                'payment_method' => $request->payment_method,
                'confirm' => true,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never', // Disable redirects
                ],
            ]);

            // Handle PaymentIntent status
            if ($paymentIntent->status === 'requires_action') {
                return $this->successResponse('Payment requires additional authentication.', [
                    'payment_intent_id' => $paymentIntent->id,
                    'next_action' => $paymentIntent->next_action,
                ]);
            }

            // Create the subscription
            $subscription = \Stripe\Subscription::create([
                'customer' => $customer->id,
                'items' => [
                    ['price' => $request->price_id],
                ],
                'default_payment_method' => $request->payment_method,
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            $user->update([
                'subscription_id' => $subscription->id,
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return $this->successResponse('Subscription created successfully.', [
                'subscription_id' => $subscription->id,
                'payment_intent_id' => $paymentIntent->id,
                'status' => $subscription->status,
            ]);
        });
    }

    public function pauseSubscription(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Retrieve the subscription ID from the request
            $subscriptionId = $request->input('subscription_id');

            // Update the subscription to pause collection
            $subscription = \Stripe\Subscription::update(
                $subscriptionId, // Pass the subscription ID
                [
                    'pause_collection' => [
                        'behavior' => 'keep_as_draft', // Options: 'keep_as_draft', 'mark_uncollectible', or 'void'
                    ],
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Subscription paused successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to pause subscription: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to pause subscription.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function resumeSubscription(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Retrieve the subscription ID from the request
            $subscriptionId = $request->input('subscription_id');

            // Update the subscription to clear the pause_collection
            $subscription = \Stripe\Subscription::update(
                $subscriptionId, // Pass the subscription ID
                [
                    'pause_collection' => null, // Clear pause_collection to resume
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Subscription resumed successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to resume subscription: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to resume subscription.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function login(Request $request)
    {
        return $this->safeCall(function () use ($request) {
            $credentials = $request->only('email', 'password');

            $job = new LoginUser($credentials);
            $result = $job->handle();

            if (isset($result['error'])) {
                return $this->errorResponse($result['error'], $result['status_code'] ?? 400);
            }

            $cookie = cookie('token', $result['token'], 10080); // 10080 minutes (7 days)

            return $this->successResponse(
                'Login successful',
                [
                    'token' => $result['token'],
                    'user' => $result['user'],
                ]
            )->cookie($cookie);
        });
    }

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
