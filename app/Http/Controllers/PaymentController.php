<?php

namespace App\Http\Controllers;

use Stripe\Stripe;
use App\Models\Vote;
use App\Models\Payment;
use Stripe\StripeClient;
use Illuminate\Http\Request;
use Laravel\Cashier\Subscription;
use App\Traits\HandlesApiResponse;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    use HandlesApiResponse;

    public function index($month, $year)
    {
        return $this->safeCall(function () use ($month, $year) {
            // Fetch the authenticated user
            $user = Auth::user();

            // Check if the user is an admin
            if (!$user || !$user->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Fetch all payments for the given month and year
            // $payments = Payment::whereMonth('created_at', $month)
            //     ->whereYear('created_at', $year)
            //     ->get();

            $payments = Subscription::with('user')
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->get();


            // Check if no payments were found
            // if ($payments->isEmpty()) {
            //     return $this->errorResponse('No payments found for the specified period.', 404);
            // }

            return $this->successResponse(
                'Payments retrieved successfully.',
                ['payments' => $payments]
            );
        });
    }

    protected $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(env('STRIPE_SECRET'));
    }

    public function totalPayments()
    {
        return $this->safeCall(function () {
            $user = Auth::user();
            if (!$user || !$user->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $subscriptions = Subscription::where('stripe_status', 'active')->get();
            $totalPayments = 0;

            foreach ($subscriptions as $subscription) {
                $price = $this->stripe->prices->retrieve($subscription->stripe_price);
                // Assuming each subscription record has a 'renewal_count' that tracks the number of renewals
                $numPayments = max($subscription->renewal_count, 1); // Ensure at least one payment is counted
                $totalPayments += ($price->unit_amount / 100) * $numPayments; // Multiply by the number of payments
            }

            return $this->successResponse('Total payments retrieved successfully.', ['total_payments' => $totalPayments]);
        });
    }


    // public function totalPayments()
    // {
    //     return $this->safeCall(function () {
    //         // Fetch the authenticated user
    //         $user = Auth::user();

    //         // Check if the user is an admin
    //         if (!$user || !$user->is_admin) {
    //             return $this->errorResponse('You are not authorized to perform this action.', 403);
    //         }

    //         // Get the current month and year
    //         $month = date('m');
    //         $year = date('Y');

    //         $totalActiveSubscriptions = Subscription::where('stripe_status', 'active')
    //             ->whereYear('created_at', $year)
    //             ->whereMonth('created_at', $month)
    //             ->count();  // Count active subscriptions

    //         $totalPayments = $totalActiveSubscriptions * 10;  // Multiply by 10 as per your requirement


    //         return $this->successResponse(
    //             'Total payments retrieved successfully.',
    //             ['total_payments' => $totalPayments]
    //         );
    //     });
    // }



    public function totalMembers()
    {
        return $this->safeCall(function () {
            // Fetch the authenticated user
            $user = Auth::user();

            // Check if the user is an admin
            if (!$user || !$user->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Get the current month and year
            $month = date('m');
            $year = date('Y');

            // Fetch the total number of members for the current month and year
            $totalMembers = Subscription::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();

            return $this->successResponse(
                'Total members retrieved successfully.',
                ['total_members' => $totalMembers]
            );
        });
    }





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
            $payments = Subscription::where('user_id', $user->id)->get();

            // Check if no payments were found
            if ($payments->isEmpty()) {
                return $this->errorResponse('No payments found.', 404);
            }

            return $this->successResponse(
                'Payments retrieved successfully.',
                ['payments' => $payments]
            );
        });
    }
}
