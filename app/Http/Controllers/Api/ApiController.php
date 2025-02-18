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
use Illuminate\Support\Facades\DB;
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
    public function createPaymentIntent(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $user = auth()->user();

            // ✅ Ensure the customer exists in Stripe
            if (!$user->stripe_customer_id) {
                $customer = \Stripe\Customer::create([
                    'email' => $user->email,
                    'name' => "{$user->first_name} {$user->last_name}",
                ]);
                $user->stripe_customer_id = $customer->id;
                $user->save();
            }

            // ✅ Validate the request
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

            // ✅ Attach payment method to customer
            $paymentMethod = \Stripe\PaymentMethod::retrieve($request->payment_method);
            $paymentMethod->attach(['customer' => $user->stripe_customer_id]);

            // ✅ Set default payment method for customer
            \Stripe\Customer::update($user->stripe_customer_id, [
                'invoice_settings' => ['default_payment_method' => $request->payment_method],
            ]);

            // ✅ Create a PaymentIntent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => 1000, // 10 GBP
                'currency' => 'gbp',
                'payment_method' => $request->payment_method,
                'customer' => $user->stripe_customer_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => 'https://yourwebsite.com/payment-success',
            ]);

            // ✅ Store the payment in the database
            $payment = Payment::create([
                'user_id' => $user->id,
                'payment_intent_id' => $paymentIntent->id,
                'payment_method' => $request->payment_method,
                'stripe_customer_id' => $user->stripe_customer_id,
                'amount' => 10.00,
                'status' => $paymentIntent->status, // ✅ Store actual Stripe status
            ]);

            // ✅ Handle authentication requirement
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


    public function subscribe(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        \Log::info('Subscription process started.', ['user_id' => Auth::id()]);

        try {
            // ✅ Validate request
            $validator = Validator::make($request->all(), [
                'price_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $priceId = trim(filter_var($request->price_id, FILTER_SANITIZE_STRING));

            // ✅ Fetch user and payment details
            $user = Auth::user();
            $payment = Payment::where('user_id', $user->id)
                ->whereNotNull('payment_intent_id')
                ->latest()
                ->first();

            if (!$payment || !$payment->payment_method || !$payment->stripe_customer_id || !$payment->payment_intent_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'No valid payment method, customer ID, or payment intent found for the user.',
                ], 404);
            }

            $paymentIntentId = $payment->payment_intent_id;

            // ✅ Fetch PaymentIntent from Stripe
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

            if (in_array($paymentIntent->status, ['requires_action', 'requires_confirmation'])) {
                $paymentIntent = $paymentIntent->confirm();

                if ($paymentIntent->status === 'requires_action') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Payment requires authentication. Redirect the user to this URL.',
                        'data' => [
                            'requires_action' => true,
                            'redirect_url' => $paymentIntent->next_action->redirect_to_url->url,
                            'payment_intent_id' => $paymentIntentId,
                        ],
                    ], 402);
                }
            }

            if (!in_array($paymentIntent->status, ['succeeded', 'processing', 'requires_capture'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment is not completed yet.',
                    'data' => [
                        'payment_intent_status' => $paymentIntent->status,
                    ],
                ], 402);
            }

            // ✅ Create the Subscription
            $subscription = \Stripe\Subscription::create([
                'customer' => $payment->stripe_customer_id,
                'items' => [['price' => $priceId]],
                'default_payment_method' => $payment->payment_method,
                'expand' => ['latest_invoice.payment_intent'],
                'payment_behavior' => 'allow_incomplete', // ✅ Allow subscription to start even if not immediately active
            ]);

            if (!isset($subscription->id)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription creation failed.',
                ], 500);
            }

            // ✅ Update database with subscription details
            DB::beginTransaction();
            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => $subscription->status, // ✅ Store actual Stripe status
            ]);

            $user->update(['subscription_id' => $subscription->id]);
            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Subscription activated successfully.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'An error occurred during subscription process.',
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
