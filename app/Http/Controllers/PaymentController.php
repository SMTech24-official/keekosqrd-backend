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

    public function index()
    {
        return $this->safeCall(function () {
            // add user must be is_admin
            $user = Auth::user();

            if (!$user->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }
            // Fetch all payments
            $payments = Payment::all();

            return $this->successResponse(
                'Payments retrieved successfully.',
                ['payments' => $payments]
            );
        });
    }

    public function totalPayments($month, $year)
    {
        return $this->safeCall(function () use ($month, $year) {
            // Check if the user is an admin
            $user = Auth::user();

            if (!$user->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Validate the month parameter
            if (!is_numeric($month) || $month < 1 || $month > 12) {
                return $this->errorResponse('Invalid month provided.', 400);
            }

            // Validate the year parameter
            if (!is_numeric($year) || $year < 1900 || $year > date('Y')) {
                return $this->errorResponse('Invalid year provided.', 400);
            }

            // Fetch total amount of payments for the given month and year
            $totalPayments = Payment::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->sum('amount');

            return $this->successResponse(
                'Total payments retrieved successfully.',
                ['total_payments' => $totalPayments]
            );
        });
    }


    public function totalMembers($month, $year)
    {
        return $this->safeCall(function () use ($month, $year) {
            // Check if the user is an admin
            $user = Auth::user();

            if (!$user->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Validate the month parameter
            if (!is_numeric($month) || $month < 1 || $month > 12) {
                return $this->errorResponse('Invalid month provided.', 400);
            }

            // Validate the year parameter
            if (!is_numeric($year) || $year < 1900 || $year > date('Y')) {
                return $this->errorResponse('Invalid year provided.', 400);
            }

            // Fetch total number of members for the given month and year
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
                return $this->errorResponse('No payments found for this user.', 404);
            }

            return $this->successResponse(
                'Payments retrieved successfully.',
                ['payments' => $payments]
            );
        });
    }
}
