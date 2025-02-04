<?php

use App\Models\Payment;
use App\Models\ChatTitle;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoteController;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
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

Route::get('/community', [CommunityController::class, 'index'])->name('community.index');

Route::group([
    "middleware" => ["auth:api"]
], function () {

    Route::post('/register-subscription',[SubscriptionController::class, 'registerSubscription']);

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
    // make product active
    Route::post('/products/{id}/active', [ProductController::class, 'productActive']);

    Route::post('/products/{id}/vote', [ProductController::class, 'vote']);

    Route::post('/stripe/payment', [ApiController::class, 'stripePayment']);

    Route::prefix('community')->controller(CommunityController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{id}', 'show');
        Route::delete('/{id}', 'destroy');
        Route::post('/{id}', 'update');
        Route::post('/{id}/approve', 'is_approved');
    });

});
