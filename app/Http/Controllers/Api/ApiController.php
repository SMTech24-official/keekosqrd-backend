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
            // Validate the request
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'zip_code' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'payment_intent_id' => 'required|string',
                'payment_method' => 'required|string',
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

            // Prepare user data
            $data = $request->only([
                'first_name',
                'last_name',
                'country',
                'city',
                'zip_code',
                'address',
                'email',
                'password',
            ]);
            $data['password'] = Hash::make($data['password']);

            // Create the user
            $user = User::create($data);

            // Log user creation
            \Log::info('User created successfully:', ['user' => $user->toArray()]);

            // Store payment record
            Payment::create([
                'user_id' => $user->id,
                'payment_intent_id' => $request->payment_intent_id,
                'payment_method' => $request->payment_method,
                'amount' => $paymentConfirmation['paymentIntent']->amount / 100, // Convert cents to dollars
            ]);

            \Log::info('Payment record stored successfully for user:', ['user_id' => $user->id]);

            // Create a recurring subscription for the user
            $subscription = $this->createSubscription($user, $request);

            // Generate a JWT token for the user
            $token = JWTAuth::fromUser($user);

            // Log token generation
            \Log::info('JWT token generated successfully.');

            // Return success response with token, user, and subscription details
            $cookie = cookie('token', $token, 60); // 60 minutes

            return $this->successResponse(
                'User registered and subscription started successfully.',
                [
                    'token' => $token,
                    'user' => $user->toArray(),
                    'subscription' => $subscription,
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

            // Check if the PaymentIntent has already succeeded
            if ($paymentIntent->status === 'succeeded') {
                return [
                    'status' => true,
                    'message' => 'Payment has already been confirmed.',
                    'paymentIntent' => $paymentIntent,
                ];
            }

            // Confirm the PaymentIntent with a return URL
            $paymentIntent = $paymentIntent->confirm([
                'payment_method' => $request->payment_method,
                'return_url' => config('app.frontend_url') . '/payment-confirmation',
            ]);

            if ($paymentIntent->status === 'succeeded') {
                return [
                    'status' => true,
                    'message' => 'Payment successfully confirmed.',
                    'paymentIntent' => $paymentIntent,
                ];
            }

            return [
                'status' => false,
                'message' => 'Payment not confirmed. Requires further action.',
                'paymentIntent' => $paymentIntent,
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Payment confirmation failed: ' . $e->getMessage());

            return [
                'status' => false,
                'message' => 'Stripe API error during confirmation.',
                'error' => $e->getMessage(),
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


    private function createSubscription($user, Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Create a Stripe customer for the user
            $customer = \Stripe\Customer::create([
                'email' => $user->email,
            ]);

            \Log::info('Stripe customer created successfully.', ['customer_id' => $customer->id]);

            // Retrieve the PaymentMethod instance
            $paymentMethod = \Stripe\PaymentMethod::retrieve($request->payment_method);

            // Attach the payment method to the customer
            $paymentMethod->attach([
                'customer' => $customer->id,
            ]);

            \Log::info('Payment method attached successfully.', ['payment_method_id' => $paymentMethod->id]);

            // Fetch the payment methods attached to the customer
            $attachedPaymentMethods = \Stripe\PaymentMethod::all([
                'customer' => $customer->id,
                'type' => 'card',
            ]);

            if (count($attachedPaymentMethods->data) > 0) {
                $attachedPaymentMethodId = $attachedPaymentMethods->data[0]->id; // Use the first attached payment method
            } else {
                \Log::error('No payment methods found for customer.', ['customer_id' => $customer->id]);
                return null; // Handle gracefully
            }

            \Log::info('Using attached payment method for subscription.', ['payment_method_id' => $attachedPaymentMethodId]);

            // Set the default payment method for the customer
            \Stripe\Customer::update($customer->id, [
                'invoice_settings' => [
                    'default_payment_method' => $attachedPaymentMethodId,
                ],
            ]);

            \Log::info('Default payment method updated for customer.', ['customer_id' => $customer->id]);

            // Create the subscription with custom price data
            $subscription = \Stripe\Subscription::create([
                'customer' => $customer->id,
                'items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product' => 'prod_Rb1UGp5GFLTBZk', // Replace with your actual product ID from Stripe
                            'recurring' => ['interval' => 'month'], // Define the subscription interval (e.g., month or year)
                            'unit_amount' => $request->amount * 100, // Amount from `createPaymentIntent` in cents
                        ],
                    ],
                ],
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            \Log::info('Subscription created successfully.', ['subscription_id' => $subscription->id]);

            return $subscription;
        } catch (\Exception $e) {
            \Log::error('Subscription creation failed: ' . $e->getMessage());
            return null;
        }
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
