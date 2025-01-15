<?php

namespace App\Http\Controllers;

use Stripe\Stripe;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Traits\HandlesApiResponse;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    use HandlesApiResponse;
    public function fetchPayments()
    {
        return $this->safeCall(function () {
            // Fetch authenticated user
            $user = Auth::user();

            // Check if the user is authenticated
            if (!$user) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Fetch all payments associated with the authenticated user
            $payments = Payment::where('user_id', $user->id)->get();

            // Check if no payments were found
            if ($payments->isEmpty()) {
                return $this->errorResponse('No payments found for this user.', 404);
            }

            return $this->successResponse(
                'Payments retrieved successfully.',
                ['payments' => $payments]
            );
        });
    }
}
