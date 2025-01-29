<?php

namespace App\Http\Controllers;

use Stripe\Stripe;
use App\Models\Payment;
use App\Models\Vote;
use Illuminate\Http\Request;
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
            $payments = Payment::whereMonth('created_at', $month)
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


    public function totalPayments()
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

            // Fetch total amount of payments for the current month and year
            $totalPayments = Payment::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->sum('amount');

            return $this->successResponse(
                'Total payments retrieved successfully.',
                ['total_payments' => $totalPayments]
            );
        });
    }



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
            $totalMembers = Payment::whereYear('created_at', $year)
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
            $payments = Payment::where('user_id', $user->id)->get();

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
