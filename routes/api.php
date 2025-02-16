<?php

use Stripe\Stripe;
use App\Models\Payment;
use App\Models\ChatTitle;
use Stripe\PaymentIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoteController;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\SubscriptionController;


// Route::post("register", [ApiController::class, "register"]);
Route::post('/register', [ApiController::class, 'register'])->name('api.register');

Route::post("login", [ApiController::class, "login"]);
Route::post('forgot-password', [ApiController::class, 'forgotPassword']);
Route::post('verify-otp', [ApiController::class, 'verifyOtp']);
Route::post('reset-password', [ApiController::class, 'resetPassword']);

// Route::post('/confirm-payment', [ApiController::class, 'confirmPayment']);

Route::get('/active-products', [ProductController::class, 'activeProducts'])->name('products.active');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');

Route::get('/community', [CommunityController::class, 'index']);

Route::group([
    "middleware" => ["auth:api"]
], function () {

    Route::post('/register-subscription',[SubscriptionController::class, 'registerSubscription']);

    Route::post('/create-payment-intent', [ApiController::class, 'createPaymentIntent']);
    Route::post('/subscribe', [ApiController::class, 'subscribe'])->name('api.subscribe');
    Route::post('/confirm-subscription', [ApiController::class, 'confirmSubscription']);



    Route::patch('/users/{user}/approve', [ApiController::class, 'approveUser']);

    Route::get('user/votes', [VoteController::class, 'userVotes']);
    Route::get('user/winers', [VoteController::class, 'userWiners']);

    Route::get("users", [ApiController::class, "users"]);
    Route::post("users/create", [ApiController::class, "store"]);
    Route::get("users/show", [ApiController::class, "getAllUsers"]);
    Route::get("user", [ApiController::class, "show"]);
    Route::post('/user/{id}/update', [ApiController::class, 'update']);
    Route::post("users/active-inactive/{id}", [ApiController::class, "activeInactive"]);
    Route::delete("users/delete/{id}", [ApiController::class, "destroy"]);
    Route::get('users/voting-history', [VoteController::class, 'votingHistory']);
    Route::post('/export-users', [ApiController::class, 'exportUsers'])->name('users.export');

    Route::post('pause-subscription', [ApiController::class, 'pauseSubscription']);
    Route::post('resume-subscription', [ApiController::class, 'resumeSubscription']);

    Route::get('/payments/user', [PaymentController::class, 'fetchPayments']);
    Route::get('/payments/{month}/{year}', [PaymentController::class, 'index']);
    Route::get('/payments/total', [PaymentController::class, 'totalPayments']);
    Route::get('/total-members', [PaymentController::class, 'totalMembers']);
    Route::get('/total-voters', [VoteController::class, 'totalVoters']);

    Route::get('/votes/{month}/{year}', [VoteController::class, 'index']);

    Route::get('/votes/export/{month}/{year}', [VoteController::class, 'exportVotes']);
    Route::post('make-winer/{id}/{month}/{year}', [VoteController::class, 'makeWiner']);
    Route::get('/winers', [VoteController::class, 'winers']);
    Route::get('export-winers', [VoteController::class, 'exportWiners']);

    Route::post('/users/search', [ApiController::class, 'search']);

    Route::get("profile", [ApiController::class, "profile"]);
    Route::put("update-profile", [UserController::class, "updateProfile"]);
    Route::get("refresh-token", [ApiController::class, "refreshToken"]);
    Route::get("logout", [ApiController::class, "logout"]);

    Route::post('/products', [ProductController::class, 'store'])->name('products.store'); // Create a new product
    Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show'); // Retrieve a single product
    Route::post('/products/update/{id}', [ProductController::class, 'update'])->name('products.update');

    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy'); // Delete a product
    // make product active
    Route::post('/products/{id}/active', [ProductController::class, 'productActive']);

    Route::post('/products/{id}/vote', [ProductController::class, 'vote']);

    Route::post('/stripe/payment', [ApiController::class, 'stripePayment']);

    Route::prefix('community')->controller(CommunityController::class)->group(function () {
        // Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}', 'update');
        Route::post('/{id}/approve', 'is_approved');
    });

});

Route::get('/payment-confirmation', function (Request $request) {
    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    $paymentIntentId = $request->query('payment_intent');

    if (!$paymentIntentId) {
        return response()->json(['status' => false, 'message' => 'Missing payment intent ID.'], 400);
    }

    try {
        $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

        if ($paymentIntent->status === 'requires_confirmation') {

            $paymentIntent = $paymentIntent->confirm([
                'return_url' => url('/api/payment-confirmation') // ✅ Set a valid return URL
            ]);
        }

        if ($paymentIntent->status === 'succeeded') {

            Payment::where('payment_intent_id', $paymentIntentId)->update([
                'status' => 'successful',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment successful! Subscription activated.',
                'data' => [
                    'payment_intent_id' => $paymentIntentId,
                    'status' => $paymentIntent->status,
			'redirect_url' => 'https://www.ksquaredsourcedcity.com/'
                ],
            ]);
        } else {

            return response()->json([
                'status' => false,
                'message' => 'Payment still incomplete. Please wait a moment and refresh.',
                'data' => [
                    'status' => $paymentIntent->status,
                ],
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Error retrieving or confirming payment intent.',
            'error' => $e->getMessage(),
        ], 500);
    }
});

// Route::get('/payment-confirmation', function (Request $request) {
//     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

//     $paymentIntentId = $request->query('payment_intent');

//     if (!$paymentIntentId) {
//         return response()->json(['status' => false, 'message' => 'Missing payment intent ID.'], 400);
//     }

//     try {
//         $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

//         // ✅ Re-confirm PaymentIntent if required
//         if ($paymentIntent->status === 'requires_confirmation') {
//             \Log::info('🔄 Re-confirming PaymentIntent.', ['payment_intent_id' => $paymentIntentId]);

//             $paymentIntent = $paymentIntent->confirm([
//                 'return_url' => 'https://yourwebsite.com/payment-confirmation'
//             ]);
//         }

//         if ($paymentIntent->status === 'succeeded') {
//             \Log::info('✅ Payment completed successfully.', ['payment_intent_id' => $paymentIntentId]);

//             // ✅ Fetch payment details
//             $payment = Payment::where('payment_intent_id', $paymentIntentId)->first();
//             if (!$payment) {
//                 return response()->json(['status' => false, 'message' => 'Payment record not found.'], 404);
//             }

//             // ✅ Update payment status
//             $payment->update(['status' => 'successful']);

//             // ✅ Fetch user
//             $user = \App\Models\User::find($payment->user_id);
//             if (!$user || !$payment->stripe_customer_id || !$payment->payment_method) {
//                 return response()->json(['status' => false, 'message' => 'Missing customer details for subscription.'], 400);
//             }

//             // ✅ Create Subscription in Stripe
//             $subscription = \Stripe\Subscription::create([
//                 'customer' => $payment->stripe_customer_id,
//                 'items' => [['price' => 'price_1QmbEQDgYV6zJ17vhlyPX5Vb']], // Ensure correct price_id
//                 'default_payment_method' => $payment->payment_method,
//                 'expand' => ['latest_invoice.payment_intent'],
//                 'payment_behavior' => 'default_incomplete',
//             ]);

//             \Log::info('✅ Subscription created.', ['subscription_id' => $subscription->id]);

//             // ✅ Confirm Subscription PaymentIntent
//             $subscriptionPaymentIntent = \Stripe\PaymentIntent::retrieve(
//                 $subscription->latest_invoice->payment_intent->id
//             );

//             if ($subscriptionPaymentIntent->status === 'requires_action') {
//                 \Log::warning('⚠️ Subscription PaymentIntent requires authentication.', [
//                     'subscription_payment_intent_id' => $subscriptionPaymentIntent->id
//                 ]);

//                 // ✅ Redirect the user to complete authentication
//                 return response()->json([
//                     'status' => false,
//                     'message' => 'Payment requires authentication. Redirect the user to this URL.',
//                     'data' => [
//                         'requires_action' => true,
//                         'redirect_url' => $subscriptionPaymentIntent->next_action->redirect_to_url->url,
//                         'payment_intent_id' => $subscriptionPaymentIntent->id,
//                     ],
//                 ], 402);
//             }

//             $subscriptionPaymentIntent->confirm([
//                 'return_url' => 'https://yourwebsite.com/payment-confirmation'
//             ]);

//             \Log::info('✅ Subscription PaymentIntent confirmed.');

//             // ✅ Fetch and check the invoice status
//             $invoice = \Stripe\Invoice::retrieve($subscription->latest_invoice->id);
//             \Log::info('🔄 Checking Invoice Status.', ['invoice_id' => $invoice->id, 'status' => $invoice->status]);

//             // ✅ If the invoice is still open, try to pay it manually
//             if ($invoice->status === 'open') {
//                 \Log::info('💳 Paying Open Invoice.', ['invoice_id' => $invoice->id]);

//                 try {
//                     $invoice = $invoice->pay();
//                     \Log::info('✅ Invoice Paid Successfully.', ['invoice_id' => $invoice->id, 'status' => $invoice->status]);
//                 } catch (\Exception $e) {
//                     \Log::error('❌ Invoice Payment Failed.', ['error' => $e->getMessage()]);

//                     return response()->json([
//                         'status' => false,
//                         'message' => 'Invoice payment requires authentication.',
//                         'data' => [
//                             'requires_action' => true,
//                             'redirect_url' => $subscriptionPaymentIntent->next_action->redirect_to_url->url,
//                             'payment_intent_id' => $subscriptionPaymentIntent->id,
//                         ],
//                     ], 402);
//                 }
//             }

//             // ✅ Wait and fetch the latest subscription status
//             sleep(3);
//             $subscription = \Stripe\Subscription::retrieve($subscription->id);

//             \Log::info('🔄 Refreshed Subscription Status.', [
//                 'subscription_id' => $subscription->id,
//                 'status' => $subscription->status
//             ]);

//             // ✅ Store Subscription ID in Database
//             $payment->update(['subscription_id' => $subscription->id]);
//             $user->update(['subscription_id' => $subscription->id]);

//             return response()->json([
//                 'status' => true,
//                 'message' => 'Subscription activated successfully.',
//                 'data' => [
//                     'payment_intent_id' => $paymentIntentId,
//                     'subscription_id' => $subscription->id,
//                     'status' => $subscription->status,
//                 ],
//             ]);
//         } else {
//             \Log::info('⚠️ Payment still incomplete.', ['payment_intent_id' => $paymentIntentId]);

//             return response()->json([
//                 'status' => false,
//                 'message' => 'Payment still incomplete. Please wait a moment and refresh.',
//                 'data' => ['status' => $paymentIntent->status],
//             ]);
//         }
//     } catch (\Exception $e) {
//         \Log::error('❌ Error retrieving or confirming payment intent.', ['error' => $e->getMessage()]);
//         return response()->json([
//             'status' => false,
//             'message' => 'Error retrieving or confirming payment intent.',
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// });









