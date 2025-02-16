<?php

namespace App\Http\Controllers\Api;

use Stripe\Stripe;
use App\Models\User;
use App\Jobs\LoginUser;
use App\Models\Payment;
use Stripe\Subscription;
use App\Traits\AuthTrait;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use App\Jobs\RegisterUser;
use Illuminate\Http\Request;
use App\Traits\HandlesApiResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Foundation\Bus\DispatchesJobs;


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
                'first_name',
                'last_name',
                'country',
                'city',
                'zip_code',
                'address',
                'email'
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
    // public function createPaymentIntent(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         $user = auth()->user();

    //         // ✅ Check if the user already has a valid Stripe customer ID
    //         if (!$user->stripe_customer_id || !$this->isValidStripeCustomer($user->stripe_customer_id)) {
    //             $customer = \Stripe\Customer::create([
    //                 'email' => $user->email,
    //                 'name' => "{$user->first_name} {$user->last_name}",
    //             ]);
    //             $user->stripe_customer_id = $customer->id;
    //             $user->save();
    //         }

    //         // Validate the request
    //         $validator = Validator::make($request->all(), [
    //             'payment_method' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Validation error',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         // Attach payment method to customer
    //         $paymentMethod = \Stripe\PaymentMethod::retrieve($request->payment_method);
    //         $paymentMethod->attach(['customer' => $user->stripe_customer_id]);

    //         // Retrieve price from Stripe
    //         $price = \Stripe\Price::retrieve('price_1QhpRzDgYV6zJ17vbxoBnokH');

    //         // ✅ Create a PaymentIntent
    //         $paymentIntent = \Stripe\PaymentIntent::create([
    //             'amount' => $price->unit_amount,
    //             'currency' => $price->currency,
    //             'payment_method' => $request->payment_method,
    //             'customer' => $user->stripe_customer_id,
    //             'payment_method_types' => ['card'],
    //             'setup_future_usage' => 'off_session',
    //             'confirm' => true,
    //         ]);

    //         // Save payment details
    //         Payment::create([
    //             'user_id' => $user->id,
    //             'payment_intent_id' => $paymentIntent->id,
    //             'payment_method' => $request->payment_method,
    //             'stripe_customer_id' => $user->stripe_customer_id,
    //             'amount' => $price->unit_amount / 100,
    //             'status' => $paymentIntent->status,
    //         ]);

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'PaymentIntent created and confirmed successfully.',
    //             'data' => [
    //                 'client_secret' => $paymentIntent->client_secret,
    //                 'payment_intent_id' => $paymentIntent->id,
    //                 'amount' => $price->unit_amount / 100,
    //                 'currency' => $price->currency,
    //                 'payment_status' => $paymentIntent->status,
    //             ],
    //         ]);
    //     } catch (\Stripe\Exception\ApiErrorException $e) {
    //         \Log::error('Failed to create PaymentIntent: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to create PaymentIntent.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // private function isValidStripeCustomer($customerId)
    // {
    //     try {
    //         $customer = \Stripe\Customer::retrieve($customerId);
    //         return isset($customer->id) && $customer->id === $customerId;
    //     } catch (\Exception $e) {
    //         return false; // Invalid customer ID
    //     }
    // }
    public function createPaymentIntent(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret')); // Use test key for testing

        try {
            $user = auth()->user();

            // ✅ **Ensure the user has a Stripe customer ID**
            if (!$user->stripe_customer_id) {
                $customer = \Stripe\Customer::create([
                    'email' => $user->email,
                    'name' => "{$user->first_name} {$user->last_name}",
                ]);
                $user->stripe_customer_id = $customer->id;
                $user->save();
            } else {
                // ✅ **Check if Customer Exists in Stripe**
                try {
                    \Stripe\Customer::retrieve($user->stripe_customer_id);
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    // If customer is not found, recreate them
                    $customer = \Stripe\Customer::create([
                        'email' => $user->email,
                        'name' => "{$user->first_name} {$user->last_name}",
                    ]);
                    $user->stripe_customer_id = $customer->id;
                    $user->save();
                }
            }

            // ✅ **Validate request**
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

            // ✅ **Retrieve and attach PaymentMethod**
            try {
                $paymentMethod = \Stripe\PaymentMethod::retrieve($request->payment_method);
                $paymentMethod->attach(['customer' => $user->stripe_customer_id]);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid PaymentMethod ID. Please try again.',
                    'error' => $e->getMessage(),
                ], 400);
            }

            // ✅ **Set default PaymentMethod for future billing**
            \Stripe\Customer::update($user->stripe_customer_id, [
                'invoice_settings' => ['default_payment_method' => $request->payment_method],
            ]);

            // ✅ **Create a PaymentIntent**
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => 1000, // 10 GBP (amount in pence)
                'currency' => 'gbp',
                'payment_method' => $request->payment_method,
                'customer' => $user->stripe_customer_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => 'https://yourwebsite.com/payment-success',
            ]);

            // ✅ **Store Payment in Database**
            $payment = Payment::create([
                'user_id' => $user->id,
                'payment_intent_id' => $paymentIntent->id,
                'payment_method' => $request->payment_method,
                'stripe_customer_id' => $user->stripe_customer_id,
                'amount' => 10.00, // Convert from pence
                'status' => $paymentIntent->status,
            ]);

            // ✅ **Handle SCA (requires_action)**
            if ($paymentIntent->status === 'requires_action') {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment requires authentication.',
                    'data' => [
                        'requires_action' => true,
                        'client_secret' => $paymentIntent->client_secret,
                        'payment_intent_id' => $paymentIntent->id,
                    ],
                ], 402);
            }

            // ✅ **If payment is successful**
            return response()->json([
                'status' => true,
                'message' => 'Payment processed successfully.',
                'data' => [
                    'payment_intent_id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                ],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create PaymentIntent.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    // working 10.49 am 2/16/25
    // public function createPaymentIntent(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         $user = auth()->user();

    //         // Ensure the user has a Stripe customer ID
    //         if (!$user->stripe_customer_id) {
    //             $customer = \Stripe\Customer::create([
    //                 'email' => $user->email,
    //                 'name' => "{$user->first_name} {$user->last_name}",
    //             ]);
    //             $user->stripe_customer_id = $customer->id;
    //             $user->save();
    //         }

    //         // Validate request
    //         $validator = Validator::make($request->all(), [
    //             'payment_method' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Validation error',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         // Retrieve and attach PaymentMethod
    //         try {
    //             $paymentMethod = \Stripe\PaymentMethod::retrieve($request->payment_method);
    //             $paymentMethod->attach(['customer' => $user->stripe_customer_id]);
    //         } catch (\Stripe\Exception\InvalidRequestException $e) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Invalid PaymentMethod ID. Please try again.',
    //                 'error' => $e->getMessage(),
    //             ], 400);
    //         }

    //         // Set default PaymentMethod
    //         \Stripe\Customer::update($user->stripe_customer_id, [
    //             'invoice_settings' => ['default_payment_method' => $request->payment_method],
    //         ]);

    //         // Create PaymentIntent with SCA support
    //         $paymentIntent = \Stripe\PaymentIntent::create([
    //             'amount' => 1000, // Amount in pence (e.g., £10.00)
    //             'currency' => 'gbp',
    //             'payment_method' => $request->payment_method,
    //             'customer' => $user->stripe_customer_id,
    //             'confirmation_method' => 'manual',
    //             'confirm' => true,
    //             'return_url' => 'https://yourwebsite.com/payment-success',
    //         ]);

    //         // ✅ **Store PaymentIntent in Database (Even if requires_action)**
    //         $payment = Payment::create([
    //             'user_id' => $user->id,
    //             'payment_intent_id' => $paymentIntent->id,
    //             'payment_method' => $request->payment_method,
    //             'stripe_customer_id' => $user->stripe_customer_id,
    //             'amount' => 10.00, // Convert from pence
    //             'status' => $paymentIntent->status,
    //         ]);

    //         // ✅ **If authentication is required, return client_secret**
    //         if ($paymentIntent->status === 'requires_action') {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Payment requires authentication.',
    //                 'data' => [
    //                     'requires_action' => true,
    //                     'client_secret' => $paymentIntent->client_secret,
    //                     'payment_intent_id' => $paymentIntent->id, // Return PaymentIntent ID for tracking
    //                 ],
    //             ], 402);
    //         }

    //         // ✅ **If payment is successful**
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Payment processed successfully.',
    //             'data' => [
    //                 'payment_intent_id' => $paymentIntent->id,
    //                 'status' => $paymentIntent->status,
    //             ],
    //         ]);
    //     } catch (\Stripe\Exception\ApiErrorException $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to create PaymentIntent.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }




    // working 6.22
    // public function createPaymentIntent(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         $user = auth()->user();

    //         // Ensure the user has a Stripe customer ID
    //         if (!$user->stripe_customer_id) {
    //             $customer = \Stripe\Customer::create([
    //                 'email' => $user->email,
    //                 'name' => "{$user->first_name} {$user->last_name}",
    //             ]);
    //             $user->stripe_customer_id = $customer->id;
    //             $user->save();
    //         }

    //         // Validate the request
    //         $validator = Validator::make($request->all(), [
    //             'payment_method' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Validation error',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }
    //         // Must add this code in live key
    //         $paymentMethod = \Stripe\PaymentMethod::retrieve($request->payment_method);
    //         $paymentMethod->attach(['customer' => $user->stripe_customer_id]);

    //         // Create a PaymentIntent
    //         $price = \Stripe\Price::retrieve('price_1QhpRzDgYV6zJ17vbxoBnokH'); // Replace with your Price ID

    //         $paymentIntent = \Stripe\PaymentIntent::create([
    //             'amount' => $price->unit_amount,
    //             'currency' => $price->currency,
    //             'payment_method' => $request->payment_method,
    //             'customer' => $user->stripe_customer_id,
    //             'payment_method_types' => ['card'],
    //             'setup_future_usage' => 'off_session', // Save for future payments
    //         ]);

    //         // Save the payment details to the database
    //         Payment::create([
    //             'user_id' => $user->id,
    //             'payment_intent_id' => $paymentIntent->id,
    //             'payment_method' => $request->payment_method,
    //             'stripe_customer_id' => $user->stripe_customer_id,
    //             'amount' => $price->unit_amount / 100,
    //             'status' => $paymentIntent->status,
    //         ]);

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'PaymentIntent created successfully.',
    //             'data' => [
    //                 'client_secret' => $paymentIntent->client_secret,
    //                 'payment_intent_id' => $paymentIntent->id,
    //                 'amount' => $price->unit_amount / 100,
    //                 'currency' => $price->currency,
    //             ],
    //         ]);
    //     } catch (\Stripe\Exception\ApiErrorException $e) {
    //         \Log::error('Failed to create PaymentIntent: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to create PaymentIntent.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }




    // Working 2/13/2025
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

    //         // Fetch the authenticated user
    //         $user = Auth::user();

    //         // Retrieve the latest payment record for the user
    //         $payment = Payment::where('user_id', $user->id)->latest()->first();

    //         if (!$payment || !$payment->payment_method || !$payment->stripe_customer_id || !$payment->payment_intent_id) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No valid payment method, customer ID, or payment intent found for the user.',
    //             ], 404);
    //         }

    //         // Retrieve and confirm the payment intent from Stripe
    //         $paymentIntent = \Stripe\PaymentIntent::retrieve($payment->payment_intent_id);

    //         if ($paymentIntent->status === 'requires_confirmation') {
    //             $paymentIntent = $paymentIntent->confirm();

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

    //         // Attach the payment method to the customer
    //         $paymentMethod = \Stripe\PaymentMethod::retrieve($payment->payment_method);
    //         $paymentMethod->attach(['customer' => $payment->stripe_customer_id]);

    //         // Set the payment method as the default for the customer
    //         \Stripe\Customer::update($payment->stripe_customer_id, [
    //             'invoice_settings' => [
    //                 'default_payment_method' => $payment->payment_method,
    //             ],
    //         ]);

    //         // Create a subscription
    //         $subscription = \Stripe\Subscription::create([
    //             'customer' => $payment->stripe_customer_id,
    //             'items' => [
    //                 [
    //                     'price' => $request->price_id,
    //                 ],
    //             ],
    //         ]);

    //         // Save subscription details to the database
    //         $payment->update([
    //             'subscription_id' => $subscription->id,
    //             'status' => $subscription->status === 'active' ? 'successful' : 'incomplete',
    //         ]);

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
    //             'message' => 'Failed to create subscription.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // public function subscribe(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'price_id' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Validation error',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         $user = Auth::user();
    //         $payment = Payment::where('user_id', $user->id)->latest()->first();

    //         if (!$payment || !$payment->payment_method || !$payment->stripe_customer_id || !$payment->payment_intent_id) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No valid payment method, customer ID, or payment intent found for the user.',
    //             ], 404);
    //         }

    //         $paymentIntent = \Stripe\PaymentIntent::retrieve($payment->payment_intent_id);

    //         if ($paymentIntent->status === 'requires_confirmation') {
    //             $paymentIntent = $paymentIntent->confirm();
    //         }

    //         // ✅ Handle "requires_action" properly
    //         if ($paymentIntent->status === 'requires_action') {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Payment requires additional authentication.',
    //                 'data' => [
    //                     'requires_action' => true,
    //                     'client_secret' => $paymentIntent->client_secret,
    //                 ],
    //             ], 402);
    //         }

    //         if ($paymentIntent->status !== 'succeeded') {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Payment could not be completed.',
    //                 'data' => [
    //                     'payment_intent_status' => $paymentIntent->status,
    //                 ],
    //             ], 402);
    //         }

    //         $paymentMethod = \Stripe\PaymentMethod::retrieve($payment->payment_method);
    //         $paymentMethod->attach(['customer' => $payment->stripe_customer_id]);

    //         \Stripe\Customer::update($payment->stripe_customer_id, [
    //             'invoice_settings' => [
    //                 'default_payment_method' => $payment->payment_method,
    //             ],
    //         ]);

    //         $subscription = \Stripe\Subscription::create([
    //             'customer' => $payment->stripe_customer_id,
    //             'items' => [
    //                 [
    //                     'price' => $request->price_id,
    //                 ],
    //             ],
    //         ]);

    //         $payment->update([
    //             'subscription_id' => $subscription->id,
    //             'status' => $subscription->status === 'active' ? 'successful' : 'incomplete',
    //         ]);

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
    //             'message' => 'Failed to create subscription.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    // working 11.04 am 2025/02/16
    // public function subscribe(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'price_id' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Validation error',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         $user = Auth::user();
    //         $payment = Payment::where('user_id', $user->id)->latest()->first();

    //         if (!$payment || !$payment->payment_method || !$payment->stripe_customer_id || !$payment->payment_intent_id) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No valid payment method, customer ID, or payment intent found for the user.',
    //             ], 404);
    //         }

    //         // Attach payment method and set as default for the customer
    //         $paymentMethod = \Stripe\PaymentMethod::retrieve($payment->payment_method);
    //         $paymentMethod->attach(['customer' => $payment->stripe_customer_id]);

    //         \Stripe\Customer::update($payment->stripe_customer_id, [
    //             'invoice_settings' => [
    //                 'default_payment_method' => $payment->payment_method,
    //             ],
    //         ]);

    //         // ✅ **Create Subscription and Expand Latest Invoice**
    //         $subscription = \Stripe\Subscription::create([
    //             'customer' => $payment->stripe_customer_id,
    //             'items' => [
    //                 [
    //                     'price' => $request->price_id,
    //                 ],
    //             ],
    //             'default_payment_method' => $payment->payment_method,
    //             'expand' => ['latest_invoice.payment_intent'],
    //             'payment_behavior' => 'default_incomplete', // Ensures first payment intent is created
    //         ]);

    //         $latestInvoice = $subscription->latest_invoice;
    //         $invoicePaymentIntent = $latestInvoice->payment_intent;

    //         // ✅ **Confirm PaymentIntent if `requires_confirmation`**
    //         if ($invoicePaymentIntent && $invoicePaymentIntent->status === 'requires_confirmation') {
    //             $invoicePaymentIntent = $invoicePaymentIntent->confirm();
    //         }

    //         // ✅ **Handle 3D Secure (requires_action)**
    //         if ($invoicePaymentIntent && $invoicePaymentIntent->status === 'requires_action') {
    //             // ✅ **Save the subscription in the database even if authentication is required**
    //             $payment->update([
    //                 'subscription_id' => $subscription->id,
    //                 'status' => 'pending_authentication', // Mark as pending if requires_action
    //             ]);

    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Subscription payment requires authentication.',
    //                 'data' => [
    //                     'requires_action' => true,
    //                     'client_secret' => $invoicePaymentIntent->client_secret,
    //                     'subscription_id' => $subscription->id,
    //                 ],
    //             ], 402);
    //         }

    //         // ✅ **If payment fails, return an error**
    //         if ($invoicePaymentIntent && $invoicePaymentIntent->status !== 'succeeded') {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Subscription payment failed.',
    //                 'data' => [
    //                     'subscription_id' => $subscription->id,
    //                     'payment_intent_status' => $invoicePaymentIntent->status,
    //                 ],
    //             ], 402);
    //         }

    //         // ✅ **Save Subscription in the Database**
    //         $payment->update([
    //             'subscription_id' => $subscription->id,
    //             'status' => $subscription->status === 'active' ? 'successful' : 'incomplete',
    //         ]);

    //         $user->update([
    //             'subscription_id' => $subscription->id,
    //         ]);

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Subscription activated successfully.',
    //             'data' => [
    //                 'subscription_id' => $subscription->id,
    //                 'status' => $subscription->status,
    //             ],
    //         ]);
    //     } catch (\Stripe\Exception\ApiErrorException $e) {
    //         \Log::error('Failed to create subscription: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to create subscription.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // working 11.04 am 2025/02/16
    public function subscribe(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        \Log::info('Subscription process started.', ['user_id' => Auth::id()]);

        try {
            // ✅ **Validate Input**
            \Log::info('Validating request data.');
            $validator = Validator::make($request->all(), [
                'price_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                \Log::warning('Validation failed.', ['errors' => $validator->errors()]);
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $priceId = trim(filter_var($request->price_id, FILTER_SANITIZE_STRING));
            \Log::info('Received valid price_id.', ['price_id' => $priceId]);

            // ✅ **Fetch User and Payment Details**
            $user = Auth::user();
            \Log::info('Fetching latest payment details.', ['user_id' => $user->id]);

            $payment = Payment::where('user_id', $user->id)
                ->whereNotNull('payment_intent_id')
                ->latest()
                ->first();

            if (!$payment || !$payment->payment_method || !$payment->stripe_customer_id || !$payment->payment_intent_id) {
                \Log::error('No valid payment method, customer ID, or payment intent found.', ['user_id' => $user->id]);
                return response()->json([
                    'status' => false,
                    'message' => 'No valid payment method, customer ID, or payment intent found for the user.',
                ], 404);
            }

            $paymentIntentId = $payment->payment_intent_id;
            \Log::info('Retrieved payment intent.', ['payment_intent_id' => $paymentIntentId]);

            // ✅ **Confirm the PaymentIntent (if required)**
            try {
                \Log::info('Fetching PaymentIntent from Stripe.', ['payment_intent_id' => $paymentIntentId]);
                $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

                if (in_array($paymentIntent->status, ['requires_action', 'requires_confirmation'])) {
                    \Log::info('PaymentIntent requires confirmation, attempting to confirm.', ['status' => $paymentIntent->status]);

                    // **FIX: Provide return_url explicitly for SCA authentication**
                    // $paymentIntent = $paymentIntent->confirm([
                    //     'return_url' => 'https://yourwebsite.com/payment-confirmation'
                    // ]);
                    $paymentIntent = $paymentIntent->confirm([
                        'return_url' => url('/api/payment-confirmation') // ✅ Set a proper return URL
                    ]);
                }

                // if ($paymentIntent->status === 'requires_action') {

                //     return response()->json([
                //         'status' => false,
                //         'message' => 'Payment requires authentication.',
                //         'data' => [
                //             'requires_action' => true,
                //             'client_secret' => $paymentIntent->client_secret,  // Send this to the frontend
                //             'payment_intent_id' => $paymentIntentId,
                //         ],
                //     ], 402);
                // }
                if ($paymentIntent->status === 'requires_action') {
                    \Log::warning('Payment requires authentication.', ['payment_intent_id' => $paymentIntentId]);

                    // ✅ **Check if Stripe provides a redirect URL for authentication**
                    if (isset($paymentIntent->next_action) && $paymentIntent->next_action->type === 'redirect_to_url') {
                        $redirectUrl = $paymentIntent->next_action->redirect_to_url->url;

                        \Log::info('Redirecting user for authentication.', ['redirect_url' => $redirectUrl]);

                        return response()->json([
                            'status' => false,
                            'message' => 'Payment requires authentication. Redirect the user to this URL.',
                            'data' => [
                                'requires_action' => true,
                                'redirect_url' => $redirectUrl,  // ✅ Redirect the user to complete authentication
                                'payment_intent_id' => $paymentIntentId,
                            ],
                        ], 402);
                    }

                    return response()->json([
                        'status' => false,
                        'message' => 'Payment requires authentication but no redirect URL found.',
                        'data' => [
                            'requires_action' => true,
                            'client_secret' => $paymentIntent->client_secret,
                            'payment_intent_id' => $paymentIntentId,
                        ],
                    ], 402);
                }



                if ($paymentIntent->status !== 'succeeded') {

                    return response()->json([
                        'status' => false,
                        'message' => 'Payment failed. Cannot proceed with subscription.',
                        'data' => [
                            'payment_intent_status' => $paymentIntent->status,
                        ],
                    ], 402);
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Error confirming payment intent.',
                    'error' => $e->getMessage(),
                ], 500);
            }


            try {
                $paymentMethod = \Stripe\PaymentMethod::retrieve($payment->payment_method);
                $paymentMethod->attach(['customer' => $payment->stripe_customer_id]);
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid PaymentMethod ID.',
                    'error' => $e->getMessage(),
                ], 400);
            }

            // ✅ **Set Default Payment Method for the Customer**
            \Stripe\Customer::update($payment->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $payment->payment_method,
                ],
            ]);

            // ✅ **Create Subscription After Payment is Confirmed**
            $subscription = \Stripe\Subscription::create([
                'customer' => $payment->stripe_customer_id,
                'items' => [['price' => $priceId]],
                'default_payment_method' => $payment->payment_method,
                'expand' => ['latest_invoice.payment_intent'],
                'payment_behavior' => 'default_incomplete',
            ]);

            // ✅ **Update Payment Record in Database**
            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => $subscription->status === 'active' ? 'successful' : 'incomplete',
            ]);

            $user->update([
                'subscription_id' => $subscription->id,
            ]);


            return response()->json([
                'status' => true,
                'message' => 'Subscription activated successfully.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                ],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Log::error('Failed to create subscription.', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to create subscription.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    // public function subscribe(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
    //     \Log::info('Subscription process started.', ['user_id' => Auth::id()]);

    //     try {
    //         // ✅ **Validate Input**
    //         $validator = Validator::make($request->all(), [
    //             'price_id' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Validation error',
    //                 'errors' => $validator->errors(),
    //             ], 400);
    //         }

    //         $priceId = trim(filter_var($request->price_id, FILTER_SANITIZE_STRING));
    //         $user = Auth::user();

    //         // ✅ **Fetch User Payment Details**
    //         $payment = Payment::where('user_id', $user->id)
    //             ->whereNotNull('payment_intent_id')
    //             ->latest()
    //             ->first();

    //         if (!$payment || !$payment->payment_method || !$payment->stripe_customer_id) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'No valid payment method or customer ID found for the user.',
    //             ], 404);
    //         }

    //         // ✅ **Create Subscription FIRST**
    //         \Log::info('Creating subscription for user.', ['user_id' => $user->id]);

    //         $subscription = \Stripe\Subscription::create([
    //             'customer' => $payment->stripe_customer_id,
    //             'items' => [['price' => $priceId]],
    //             'default_payment_method' => $payment->payment_method,
    //             'payment_behavior' => 'default_incomplete', // ✅ Allows incomplete payments
    //             'expand' => ['latest_invoice.payment_intent'],
    //         ]);

    //         \Log::info('Subscription created.', ['subscription_id' => $subscription->id]);

    //         // ✅ **Retrieve PaymentIntent from Subscription**
    //         $subscriptionPaymentIntent = $subscription->latest_invoice->payment_intent ?? null;

    //         if (!$subscriptionPaymentIntent) {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Failed to retrieve subscription payment intent.',
    //             ], 500);
    //         }

    //         // ✅ **Store Subscription ID in Database**
    //         $payment->update([
    //             'subscription_id' => $subscription->id,
    //             'status' => 'requires_action',
    //         ]);

    //         // ✅ **Check if Authentication is Needed**
    //         if ($subscriptionPaymentIntent->status === 'requires_action') {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'Payment requires authentication.',
    //                 'data' => [
    //                     'requires_action' => true,
    //                     'client_secret' => $subscriptionPaymentIntent->client_secret,
    //                     'payment_intent_id' => $subscriptionPaymentIntent->id,
    //                     'subscription_id' => $subscription->id, // ✅ Now we include subscription_id
    //                 ],
    //             ], 402);
    //         }

    //         // ✅ **Check if Payment is Successful**
    //         if ($subscriptionPaymentIntent->status === 'succeeded') {
    //             $payment->update(['status' => 'successful']);

    //             return response()->json([
    //                 'status' => true,
    //                 'message' => 'Subscription activated successfully.',
    //                 'data' => [
    //                     'subscription_id' => $subscription->id,
    //                     'status' => 'active',
    //                 ],
    //             ]);
    //         }

    //         // ✅ **If Payment is Pending**
    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Subscription created but payment is pending.',
    //             'data' => [
    //                 'subscription_id' => $subscription->id,
    //                 'status' => $subscriptionPaymentIntent->status,
    //             ],
    //         ]);
    //     } catch (\Stripe\Exception\ApiErrorException $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to create subscription.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function confirmSubscription(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $subscriptionId = $request->input('subscription_id');

        if (!$subscriptionId) {
            return response()->json([
                'status' => false,
                'message' => 'Missing subscription_id.',
            ], 400);
        }

        try {
            \Log::info('Fetching subscription from Stripe.', ['subscription_id' => $subscriptionId]);

            // ✅ Retrieve Subscription from Stripe
            $subscription = \Stripe\Subscription::retrieve($subscriptionId);

            if (!$subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription not found in Stripe.',
                ], 404);
            }

            \Log::info('Subscription Details:', (array) $subscription);

            // ✅ If the subscription is already active, return success
            if ($subscription->status === 'active') {
                return response()->json([
                    'status' => true,
                    'message' => 'Subscription is already active.',
                    'data' => [
                        'subscription_id' => $subscriptionId,
                        'status' => 'active',
                    ],
                ]);
            }

            // ✅ Get PaymentIntent from the latest invoice
            $invoiceId = $subscription->latest_invoice;
            $invoice = \Stripe\Invoice::retrieve($invoiceId);
            $paymentIntentId = $invoice->payment_intent;
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

            \Log::info('PaymentIntent Details:', (array) $paymentIntent);

            // ✅ Handle 3D Secure Authentication
            if ($paymentIntent->status === 'requires_action') {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment requires authentication.',
                    'data' => [
                        'requires_action' => true,
                        'client_secret' => $paymentIntent->client_secret,
                        'payment_intent_id' => $paymentIntent->id,
                        'subscription_id' => $subscriptionId,
                    ],
                ], 402);
            }

            // ✅ Handle Requires Confirmation (Frontend must confirm it)
            if ($paymentIntent->status === 'requires_confirmation') {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment requires confirmation.',
                    'data' => [
                        'requires_action' => true,
                        'client_secret' => $paymentIntent->client_secret,
                        'payment_intent_id' => $paymentIntent->id,
                        'subscription_id' => $subscriptionId,
                    ],
                ], 402);
            }

            // ✅ Handle Failed Payments (Requires new payment method)
            if ($paymentIntent->status === 'requires_payment_method') {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment failed. Please update your payment method.',
                    'data' => [
                        'subscription_id' => $subscriptionId,
                        'status' => 'requires_payment_method',
                    ],
                ], 400);
            }

            // ✅ If Payment is Successful, mark subscription as active
            if ($paymentIntent->status === 'succeeded') {
                Payment::where('subscription_id', $subscriptionId)->update([
                    'status' => 'successful',
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Subscription activated successfully.',
                    'data' => [
                        'subscription_id' => $subscriptionId,
                        'status' => 'active',
                    ],
                ]);
            }

            // ✅ Handle Pending Payment
            return response()->json([
                'status' => true,
                'message' => 'Subscription confirmed, but payment is still processing.',
                'data' => [
                    'subscription_id' => $subscriptionId,
                    'status' => $subscription->status,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error confirming subscription.', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Error confirming subscription.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    // public function pauseSubscription(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         // Retrieve the subscription ID from the request
    //         $subscriptionId = $request->input('subscription_id');

    //         // Update the subscription to pause collection
    //         $subscription = \Stripe\Subscription::update(
    //             $subscriptionId, // Pass the subscription ID
    //             [
    //                 'pause_collection' => [
    //                     'behavior' => 'keep_as_draft', // Options: 'keep_as_draft', 'mark_uncollectible', or 'void'
    //                 ],
    //             ]
    //         );

    //         // Update the status in the payments table
    //         $payment = Payment::where('subscription_id', $subscriptionId)->first();
    //         if ($payment) {
    //             $payment->update(['status' => 'paused']);
    //         }

    //         // Update the payment intent in Stripe to show paused status
    //         if (isset($subscription->latest_invoice) && $subscription->latest_invoice) {
    //             $invoice = \Stripe\Invoice::retrieve($subscription->latest_invoice);
    //             if (isset($invoice->payment_intent) && $invoice->payment_intent) {
    //                 \Stripe\PaymentIntent::update(
    //                     $invoice->payment_intent,
    //                     ['metadata' => ['status' => 'paused']]
    //                 );
    //             }
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Subscription paused successfully and updated in Stripe.',
    //         ]);
    //     } catch (\Exception $e) {
    //         \Log::error('Failed to pause subscription: ' . $e->getMessage());

    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to pause subscription.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


    // public function resumeSubscription(Request $request)
    // {
    //     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    //     try {
    //         // Retrieve the subscription ID from the request
    //         $subscriptionId = $request->input('subscription_id');

    //         // Update the subscription to resume collection
    //         $subscription = \Stripe\Subscription::update(
    //             $subscriptionId, // Pass the subscription ID
    //             [
    //                 'pause_collection' => null, // Removing pause_collection to resume the subscription
    //             ]
    //         );

    //         // Update the status in the payments table
    //         $payment = Payment::where('subscription_id', $subscriptionId)->first();
    //         if ($payment) {
    //             $payment->update(['status' => 'active']);
    //         }

    //         // Update the payment intent in Stripe to remove paused status
    //         if (isset($subscription->latest_invoice) && $subscription->latest_invoice) {
    //             $invoice = \Stripe\Invoice::retrieve($subscription->latest_invoice);
    //             if (isset($invoice->payment_intent) && $invoice->payment_intent) {
    //                 \Stripe\PaymentIntent::update(
    //                     $invoice->payment_intent,
    //                     ['metadata' => ['status' => 'active']]
    //                 );
    //             }
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'Subscription resumed successfully and updated in Stripe.',
    //         ]);
    //     } catch (\Exception $e) {
    //         \Log::error('Failed to resume subscription: ' . $e->getMessage());

    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to resume subscription.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
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
