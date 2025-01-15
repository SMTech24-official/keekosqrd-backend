<?php

use App\Models\ChatTitle;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OrfaAIController;
use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ChatTitleController;
use App\Http\Controllers\CaseExampleController;
use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\TestimonialController;
use App\Http\Controllers\TransactionController;
// use App\Jobs\TestJob;



Route::post("register", [ApiController::class, "register"]);
Route::post("login", [ApiController::class, "login"]);
Route::post('forgot-password', [ApiController::class, 'forgotPassword']);
Route::post('verify-otp', [ApiController::class, 'verifyOtp']);
Route::post('reset-password', [ApiController::class, 'resetPassword']);

Route::post('/create-payment-intent', [ApiController::class, 'createPaymentIntent']);
Route::post('/confirm-payment', [ApiController::class, 'confirmPayment']);

Route::get('/active-products', [ProductController::class, 'activeProducts'])->name('products.active');


Route::group([
    "middleware" => ["auth:api"]
], function () {

    Route::patch('/users/{user}/approve', [ApiController::class, 'approveUser']);

    Route::get("users", [ApiController::class, "users"]);
    Route::post("users/create", [ApiController::class, "store"]);
    Route::get("users/show", [ApiController::class, "getAllUsers"]);
    Route::get("user/{id}", [ApiController::class, "show"]);
    Route::post('/user/{id}/update', [ApiController::class, 'update']);
    Route::post("users/active-inactive/{id}", [ApiController::class, "activeInactive"]);
    Route::delete("users/delete/{id}", [ApiController::class, "destroy"]);
    
    Route::post('/export-users', [ApiController::class, 'exportUsers'])->name('users.export');

    Route::get('/payments/user', [PaymentController::class, 'fetchPayments']);


    Route::post('/users/search', [ApiController::class, 'search']);

    Route::get("profile", [ApiController::class, "profile"]);
    Route::put("update-profile", [UserController::class, "updateProfile"]);
    Route::get("refresh-token", [ApiController::class, "refreshToken"]);
    Route::get("logout", [ApiController::class, "logout"]);

    // Product CRUD routes
    Route::get('/products', [ProductController::class, 'index'])->name('products.index'); // Retrieve all products
    Route::post('/products', [ProductController::class, 'store'])->name('products.store'); // Create a new product
    Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show'); // Retrieve a single product
    Route::post('/products/update/{id}', [ProductController::class, 'update'])->name('products.update');

    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy'); // Delete a product
    // make a route to get a list of all active products

    Route::post('/products/{id}/vote', [ProductController::class, 'vote']);
});
