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
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\StripeWebhookController;
use Laravel\Cashier\Exceptions\IncompletePayment;



Route::post('/register', [ApiController::class, 'register'])->name('api.register');

Route::post("login", [ApiController::class, "login"]);
Route::post('forgot-password', [ApiController::class, 'forgotPassword']);
Route::post('verify-otp', [ApiController::class, 'verifyOtp']);
Route::post('reset-password', [ApiController::class, 'resetPassword']);


Route::get('/active-products', [ProductController::class, 'activeProducts'])->name('products.active');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');

Route::get('/community', [CommunityController::class, 'index']);

Route::group([
    "middleware" => ["auth:api"]
], function () {

    Route::post('/create-payment-intent', [ApiController::class, 'createPaymentIntent']);
    Route::post('/subscribe', [ApiController::class, 'subscribe'])->name('api.subscribe');

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
    Route::post('/products/{id}/active', [ProductController::class, 'productActive']);

    Route::post('/products/{id}/vote', [ProductController::class, 'vote']);

    Route::post('/stripe/payment', [ApiController::class, 'stripePayment']);

    Route::prefix('community')->controller(CommunityController::class)->group(function () {
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}', 'update');
        Route::post('/{id}/approve', 'is_approved');
    });






    Route::post('checkout', [SubscriptionController::class, 'checkout']);

    Route::post('/cancel-subscription', [SubscriptionController::class, 'cancelSubscription']);

    // Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);

});


// Route::get('/payment-confirmation', function (Request $request) {
//     \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

//     $paymentIntentId = $request->query('payment_intent');

//     if (!$paymentIntentId) {
//         return response()->json(['status' => false, 'message' => 'Missing payment intent ID.'], 400);
//     }

//     try {
//         // Retrieve the PaymentIntent object
//         $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

//         // If the PaymentIntent requires confirmation (requires_action)
//         if ($paymentIntent->status === 'requires_action' || $paymentIntent->status === 'requires_confirmation') {
//             // If we are in the requires_action state, confirm again with return_url for redirection to complete authentication
//             $paymentIntent = $paymentIntent->confirm([
//                 'return_url' => url('/payment-success'), // Your success page here
//             ]);
//         }

//         // Check if payment is successful
//         if ($paymentIntent->status === 'succeeded') {
//             Payment::where('payment_intent_id', $paymentIntentId)->update([
//                 'status' => 'successful',
//             ]);

//             return redirect(url('/payment-success')); // Redirect to your success page
//         } else {
//             return response()->json([
//                 'status' => false,
//                 'message' => 'Payment still incomplete. Please wait a moment and refresh.',
//                 'data' => [
//                     'status' => $paymentIntent->status,
//                 ],
//             ]);
//         }
//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => false,
//             'message' => 'Error retrieving or confirming payment intent.',
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// });



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
                'return_url' => 'https://www.ksquaredsourcedcity.com/'
            ]);
        }

        if ($paymentIntent->status === 'succeeded') {

            Payment::where('payment_intent_id', $paymentIntentId)->update([
                'status' => 'successful',
            ]);
            return redirect('https://www.ksquaredsourcedcity.com/');
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
