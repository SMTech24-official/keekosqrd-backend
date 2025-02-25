<?php

namespace App\Http\Controllers;

use Stripe\Stripe;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use App\Traits\HandlesApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use Stripe\Checkout\Session as StripeSession;

class SubscriptionController extends Controller
{
    use HandlesApiResponse;

    public function checkout(Request $request)
    {
        $user = auth()->user();

        // Set your secret Stripe API key
        Stripe::setApiKey(config('cashier.secret'));

        // Stripe price ID for the subscription (create this in your Stripe Dashboard)
        $stripePriceId = 'price_1QmbEQDgYV6zJ17vhlyPX5Vb'; // Replace with your actual recurring price ID

        // Create Stripe customer if one does not exist
        if (!$user->hasStripeId()) {
            $user->createAsStripeCustomer();
        }

        // Create a Stripe Checkout session
        try {
            $checkoutSession = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price' => $stripePriceId,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription', // Set the mode to subscription for recurring payments
                'customer' => $user->stripe_id,
                // 'success_url' => route('checkout-success'),
                // 'cancel_url' => route('checkout-cancel'),
                'success_url' => 'https://www.ksquaredsourcedcity.com/',
                'cancel_url' => 'https://www.ksquaredsourcedcity.com/',

            ]);

            // Return the session URL for redirect
            return response()->json([
                'url' => $checkoutSession->url,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function resumeSubscription()
    {
        $user = auth()->user();
        $activeDefultSubscription = $user->subscription('default');

        $activeDefultSubscription->resume();

        return response()->json(['message' => 'Subscription resumed!']);
    }


    public function cancelSubscription()
    {
        $user = auth()->user();
        $subscription = $user->subscription('default')->cancel();
        return response()->json(['message' => 'Subscription canceled!', 'subscription' => $subscription]);
    }


    public function updateSubscriptionCheckout(Request $request)
    {
        $user = Auth::user();

        // Log the user attempting to update the payment method
        Log::info('Updating subscription checkout', ['user_id' => $user->id]);

        if (!$user->hasStripeId()) {
            // $user->createAsStripeCustomer();
            return response()->json(['error' => 'No Stripe customer account found.'], 400);
        }
        // if (!$user->hasStripeId()) {
        //     Log::error('No Stripe customer account found', ['user_id' => $user->id]);
        //     return response()->json(['error' => 'No Stripe customer account found.'], 400);
        // }

        Stripe::setApiKey(config('cashier.secret'));


        try {
            $session = StripeSession::create([
                'payment_method_types' => ['card'],
                'mode' => 'setup',
                'customer' => $user->stripe_id,
                'setup_intent_data' => [
                    'metadata' => [
                        'customer_id' => $user->stripe_id,
                        'subscription_id' => $request->subscription_id,
                    ],
                ],
                'success_url' => url('https://www.ksquaredsourcedcity.com?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('https://www.ksquaredsourcedcity.com'),
            ]);

            // Log successful session creation
            Log::info('Stripe checkout session created successfully', ['session_id' => $session->id]);

            // Return the checkout session URL for the frontend to redirect to
            return response()->json(['url' => $session->url]);
        } catch (\Exception $e) {
            // Log the exception details
            Log::error('Failed to create Stripe checkout session', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
