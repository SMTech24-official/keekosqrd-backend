<?php

namespace App\Http\Controllers;

use Stripe\Stripe;
use App\Models\User;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use App\Traits\HandlesApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use Stripe\Checkout\Session as StripeSession;
use Laravel\Cashier\Exceptions\IncompletePayment;


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


    // public function checkout(Request $request)
    // {
    //     $user = auth()->user();

    //     // Set your secret Stripe API key
    //     Stripe::setApiKey(config('cashier.secret'));

    //     // Stripe price ID for the subscription (create this in your Stripe Dashboard)
    //     $stripePriceId = 'price_1QmbEQDgYV6zJ17vhlyPX5Vb'; // Replace with your actual recurring price ID

    //     // Create Stripe customer if one does not exist
    //     if (!$user->hasStripeId()) {
    //         $user->createAsStripeCustomer();
    //     }

    //     // Create a Stripe Checkout session
    //     try {
    //         $checkoutSession = Session::create([
    //             'payment_method_types' => ['card'],
    //             'line_items' => [
    //                 [
    //                     'price' => $stripePriceId,
    //                     'quantity' => 1,
    //                 ],
    //             ],
    //             'mode' => 'subscription', // Set the mode to subscription for recurring payments
    //             'customer' => $user->stripe_id,
    //             // 'success_url' => route('checkout-success'),
    //             // 'cancel_url' => route('checkout-cancel'),
    //             'success_url' => 'https://www.ksquaredsourcedcity.com/',
    //             'cancel_url' => 'https://www.ksquaredsourcedcity.com/',

    //         ]);

    //         // Return the session URL for redirect
    //         return response()->json([
    //             'url' => $checkoutSession->url,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 400);
    //     }
    // }

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


    // Route::get('subscriptions/resume', function () {
    //     $user = Auth::user();
    //     $activeDefultSubscription = $user->subscription('default');

    //     $activeDefultSubscription->resume();

    //     dd('Subscription resumed!');
    // });
}
