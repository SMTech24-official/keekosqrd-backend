<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;

class PaymentController extends Controller
{
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

    public function confirmPayment(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'payment_intent_id' => 'required|string',
            'payment_method' => 'nullable|string',
        ]);

        // Set the Stripe API key
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Retrieve the PaymentIntent
            $paymentIntent = \Stripe\PaymentIntent::retrieve($request->payment_intent_id);

            // Confirm the PaymentIntent with a return URL
            $confirmedPaymentIntent = $paymentIntent->confirm([
                'payment_method' => $request->payment_method,
                'return_url' => 'https://yourdomain.com/payment-confirmation', // Replace with your actual return URL
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment confirmation initiated. Follow redirects if required.',
                'paymentIntent' => $confirmedPaymentIntent,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Payment confirmation failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
