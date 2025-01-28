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

            if ($validator->fails()) {
                return $this->errorResponse('Validation error', 400, $validator->errors());
            }

            $data = $request->only([
                'first_name', 'last_name', 'country', 'city', 'zip_code', 'address', 'email'
            ]);
            $data['password'] = $request->password;

            // Dispatch the job synchronously (blocking)
            $job = new RegisterUser($data);
            $result = $job->handle();

            if (isset($result['error'])) {
                return $this->errorResponse($result['error'], 400);
            }

            return $this->successResponse(
                'User registered successfully.',
                $result
            );
        });
    }


    // working 6.22
    public function createPaymentIntent(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $user = auth()->user();

            // Ensure the user has a Stripe customer ID
            if (!$user->stripe_customer_id) {
                $customer = \Stripe\Customer::create([
                    'email' => $user->email,
                    'name' => "{$user->first_name} {$user->last_name}",
                ]);
                $user->stripe_customer_id = $customer->id;
                $user->save();
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Create a PaymentIntent
            $price = \Stripe\Price::retrieve('price_1QhpRzDgYV6zJ17vbxoBnokH'); // Replace with your Price ID

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $price->unit_amount,
                'currency' => $price->currency,
                'payment_method' => $request->payment_method,
                'customer' => $user->stripe_customer_id,
                'payment_method_types' => ['card'],
                'setup_future_usage' => 'off_session', // Save for future payments
            ]);

            // Save the payment details to the database
            Payment::create([
                'user_id' => $user->id,
                'payment_intent_id' => $paymentIntent->id,
                'payment_method' => $request->payment_method,
                'stripe_customer_id' => $user->stripe_customer_id,
                'amount' => $price->unit_amount / 100,
                'status' => $paymentIntent->status,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'PaymentIntent created successfully.',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $price->unit_amount / 100,
                    'currency' => $price->currency,
                ],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Failed to create PaymentIntent: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create PaymentIntent.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // Working 5.26 pm
    // public function createPaymentIntent(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         $user = auth()->user();

    //         // Check if the user already has a Stripe customer ID
    //         if (!$user->stripe_customer_id) {
    //             // Create a new Stripe customer
    //             $customer = \Stripe\Customer::create([
    //                 'email' => $user->email,
    //                 'name' => $user->name, // Assuming 'name' exists in your user table
    //             ]);

    //             // Save the Stripe customer ID to the user
    //             $user->stripe_customer_id = $customer->id;
    //             $user->save();
    //         }

    //         // Validate the request
    //         $validator = Validator::make($request->all(), [
    //             'payment_method' => 'required|string', // Ensure payment method is passed
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Validation error',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         // Fetch the price dynamically from Stripe using the Price ID
    //         $price = \Stripe\Price::retrieve('price_1QhpRzDgYV6zJ17vbxoBnokH'); // Replace with your Stripe Price ID

    //         // Create a PaymentIntent
    //         $paymentIntent = \Stripe\PaymentIntent::create([
    //             'amount' => $price->unit_amount, // Amount in cents
    //             'currency' => $price->currency, // Currency from the Price object
    //             'payment_method' => $request->payment_method, // Use the provided payment method
    //             'payment_method_types' => ['card'], // Accept only card payments
    //             'customer' => $user->stripe_customer_id, // Link to the Stripe customer
    //         ]);

    //         // Store the payment details in the payments table
    //         $payment = Payment::create([
    //             'user_id' => $user->id,
    //             'payment_intent_id' => $paymentIntent->id,
    //             'payment_method' => $request->payment_method,
    //             'stripe_customer_id' => $user->stripe_customer_id,
    //             'amount' => $price->unit_amount / 100, // Convert cents to dollars
    //             'subscription_id' => null, // Add subscription ID if applicable in future
    //             'status' => $paymentIntent->status,
    //         ]);

    //         // Return the desired response format
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'PaymentIntent created successfully',
    //             'data' => [
    //                 'client_secret' => $paymentIntent->client_secret,
    //                 'payment_intent_id' => $paymentIntent->id,
    //                 'amount' => $price->unit_amount / 100, // Convert cents to dollars
    //                 'currency' => $price->currency,
    //             ],
    //         ]);
    //     } catch (\Stripe\Exception\ApiErrorException $e) {
    //         \Log::error('Failed to create PaymentIntent: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to create PaymentIntent',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


    // Working 6.22
    public function subscribe(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'price_id' => 'required|string', // Price ID is required
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Fetch the authenticated user
            $user = Auth::user();

            // Retrieve the latest payment record for the user
            $payment = Payment::where('user_id', $user->id)->latest()->first();

            if (!$payment || !$payment->payment_method || !$payment->stripe_customer_id || !$payment->payment_intent_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'No valid payment method, customer ID, or payment intent found for the user.',
                ], 404);
            }

            // Retrieve and confirm the payment intent from Stripe
            $paymentIntent = \Stripe\PaymentIntent::retrieve($payment->payment_intent_id);

            if ($paymentIntent->status === 'requires_confirmation') {
                $paymentIntent = $paymentIntent->confirm();

                if ($paymentIntent->status !== 'succeeded') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Payment could not be completed.',
                        'data' => [
                            'payment_intent_status' => $paymentIntent->status,
                        ],
                    ], 402);
                }
            }

            // Attach the payment method to the customer
            $paymentMethod = \Stripe\PaymentMethod::retrieve($payment->payment_method);
            $paymentMethod->attach(['customer' => $payment->stripe_customer_id]);

            // Set the payment method as the default for the customer
            \Stripe\Customer::update($payment->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $payment->payment_method,
                ],
            ]);

            // Create a subscription
            $subscription = \Stripe\Subscription::create([
                'customer' => $payment->stripe_customer_id,
                'items' => [
                    [
                        'price' => $request->price_id,
                    ],
                ],
            ]);

            // Save subscription details to the database
            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => $subscription->status === 'active' ? 'successful' : 'incomplete',
            ]);

            $user->update([
                'subscription_id' => $subscription->id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Subscription created and payment confirmed successfully.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                ],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Failed to create subscription: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create subscription.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    // working 2.38 pm
    // public function subscribe(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         // Validate the request
    //         $validator = Validator::make($request->all(), [
    //             'price_id' => 'required|string', // Price ID is required
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Validation error',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         // Fetch the latest payment record for the authenticated user
    //         $user = Auth::user();
    //         $payment = Payment::where('user_id', $user->id)->latest()->first();

    //         if (!$payment || !$payment->stripe_customer_id || !$payment->payment_intent_id) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Payment, customer ID, or payment intent ID not found.',
    //             ], 404);
    //         }

    //         // Confirm the PaymentIntent using the ID from the database
    //         $paymentIntent = \Stripe\PaymentIntent::retrieve($payment->payment_intent_id);

    //         if ($paymentIntent->status === 'requires_confirmation') {
    //             $paymentIntent->confirm();

    //             if ($paymentIntent->status !== 'succeeded') {
    //                 return response()->json([
    //                     'status' => false,
    //                     'message' => 'Payment could not be completed.',
    //                     'data' => [
    //                         'payment_intent_status' => $paymentIntent->status,
    //                     ],
    //                 ], 402);
    //             }
    //         }

    //         // Create a subscription using the Stripe API
    //         $subscription = \Stripe\Subscription::create([
    //             'customer' => $payment->stripe_customer_id,
    //             'items' => [
    //                 [
    //                     'price' => $request->price_id,
    //                 ],
    //             ],
    //             'expand' => ['latest_invoice.payment_intent'],
    //         ]);

    //         // Update the payment record in the database with subscription details
    //         $payment->update([
    //             'subscription_id' => $subscription->id,
    //             'status' => $subscription->status === 'active' ? 'successful' : 'incomplete',
    //         ]);

    //         // Update the user's subscription details
    //         $user->update([
    //             'subscription_id' => $subscription->id,
    //         ]);

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Subscription created and payment confirmed successfully.',
    //             'data' => [
    //                 'subscription_id' => $subscription->id,
    //                 'status' => $subscription->status,
    //             ],
    //         ]);
    //     } catch (\Stripe\Exception\ApiErrorException $e) {
    //         \Log::error('Failed to create subscription: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to create subscription',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

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

            // Update the status in the payments table
            $payment = Payment::where('subscription_id', $subscriptionId)->first();
            if ($payment) {
                $payment->update(['status' => 'paused']);
            }

            // Update the payment intent in Stripe to show paused status
            if (isset($subscription->latest_invoice) && $subscription->latest_invoice) {
                $invoice = \Stripe\Invoice::retrieve($subscription->latest_invoice);
                if (isset($invoice->payment_intent) && $invoice->payment_intent) {
                    \Stripe\PaymentIntent::update(
                        $invoice->payment_intent,
                        ['metadata' => ['status' => 'paused']]
                    );
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Subscription paused successfully and updated in Stripe.',
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

            // Update the subscription to resume collection
            $subscription = \Stripe\Subscription::update(
                $subscriptionId, // Pass the subscription ID
                [
                    'pause_collection' => null, // Removing pause_collection to resume the subscription
                ]
            );

            // Update the status in the payments table
            $payment = Payment::where('subscription_id', $subscriptionId)->first();
            if ($payment) {
                $payment->update(['status' => 'active']);
            }

            // Update the payment intent in Stripe to remove paused status
            if (isset($subscription->latest_invoice) && $subscription->latest_invoice) {
                $invoice = \Stripe\Invoice::retrieve($subscription->latest_invoice);
                if (isset($invoice->payment_intent) && $invoice->payment_intent) {
                    \Stripe\PaymentIntent::update(
                        $invoice->payment_intent,
                        ['metadata' => ['status' => 'active']]
                    );
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Subscription resumed successfully and updated in Stripe.',
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
