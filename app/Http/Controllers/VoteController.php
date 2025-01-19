<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vote;
use App\Mail\VoteMail;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Traits\HandlesApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class VoteController extends Controller
{
    use HandlesApiResponse;

    public function index($month, $year)
    {
        return $this->safeCall(function () use ($month, $year) {
            // Check if the user is authenticated
            if (!Auth::check()) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

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

            // Fetch all votes for the given month and year
            $votes = Vote::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->with('user', 'product')
                ->get();

            if ($votes->isEmpty()) {
                return $this->successResponse('No votes found.', ['votes' => []]);
            }

            return $this->successResponse(
                'Votes retrieved successfully.',
                ['votes' => $votes]
            );
        });
    }

    public function totalVoters($month, $year)
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

            $totalVoters = Vote::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();

            return $this->successResponse(
                'Total voters retrieved successfully.',
                ['total_voters' => $totalVoters]
            );
        });
    }

    // public function makeWiner(Request $request, $id)
    // {
    //     return $this->safeCall(function () use ($request, $id) {
    //         // Validate the request data
    //         $data = $request->validate([
    //             'status' => 'required|boolean',
    //         ]);

    //         // Check if the user is authenticated
    //         if (!Auth::check()) {
    //             return $this->errorResponse('You are not authorized to perform this action.', 403);
    //         }

    //         // Check if the user is an admin
    //         $user = Auth::user();

    //         if (!$user->is_admin) {
    //             return $this->errorResponse('You are not authorized to perform this action.', 403);
    //         }

    //         // Find the vote by ID
    //         $vote = Vote::find($id);

    //         if (!$vote) {
    //             return $this->errorResponse('Vote not found.', 404);
    //         }

    //         // Update the status of the vote
    //         $vote->update(['status' => $data['status']]);


    //         return $this->successResponse(
    //             'Status updated successfully.',
    //             ['status' => $vote]
    //         );
    //     });
    // }

    public function makeWiner(Request $request, $id, $month, $year)
    {
        try {
            // Validate the request
            $data = $request->validate([
                'status' => 'required|boolean',
            ]);

            // Ensure the user is authenticated and authorized (admin)
            $user = Auth::user();
            if (!$user || !$user->is_admin) {
                return response()->json(['error' => 'Unauthorized action.'], 403);
            }

            // Check if a winner already exists for the given month and year
            $existingWinner = Vote::where('status', true)
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->first();

            if ($existingWinner) {
                return response()->json([
                    'error' => 'A winner has already been selected for this month and year.'
                ], 400);
            }

            // Find the vote by ID
            $vote = Vote::find($id);
            if (!$vote) {
                return response()->json(['error' => 'Vote not found.'], 404);
            }

            // Update the vote status to make this user the winner
            $vote->update(['status' => $data['status']]);

            // Fetch the winner and product details
            $winner = User::find($vote->user_id);
            $product = Product::find($vote->product_id);

            if (!$winner || !$product) {
                return response()->json(['error' => 'Winner or product details not found.'], 404);
            }

            // Send an email to the winner
            Mail::to($winner->email)->send(new VoteMail($winner, $product, $vote));

            return response()->json(['message' => 'Winner selected and email sent successfully.'], 200);
        } catch (\Exception $e) {
            // Log the error and return an appropriate response
            \Log::error('Error in makeWiner method: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred. Please try again later.'], 500);
        }
    }

    public function winers()
    {
        return $this->safeCall(function () {
            // Check if the user is authenticated
            if (!Auth::check()) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Check if the user is an admin
            $user = Auth::user();

            if (!$user->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Fetch all winers
            $winers = Vote::where('status', true)
                ->with('user', 'product')
                ->get();

            if ($winers->isEmpty()) {
                return $this->successResponse('No winers found.', ['winers' => []]);
            }

            return $this->successResponse(
                'Winers retrieved successfully.',
                ['winers' => $winers]
            );
        });
    }

    public function exportWiners()
    {
        // Exporting CSV requires returning a StreamedResponse directly, bypassing safeCall

        // Check if the user is authenticated
        if (!Auth::check()) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        // Check if the user is an admin
        $user = Auth::user();

        if (!$user->is_admin) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        // Fetch all winers
        $winers = Vote::where('status', true)
            ->with(['user:id,first_name,last_name', 'product:id,product_name'])
            ->get();

        if ($winers->isEmpty()) {
            return $this->successResponse('No winers found to export.', ['winers' => []]);
        }

        // Format data for export
        $exportData = $winers->map(function ($winer) {
            return [
                'Vote ID' => $winer->id,
                'User Name' => ($winer->user->first_name ?? '') . ' ' . ($winer->user->last_name ?? 'N/A'),
                'Product Name' => $winer->product->product_name ?? 'N/A',
                'Created At' => $winer->created_at->format('Y-m-d H:i:s'),
                'Updated At' => $winer->updated_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        // Generate CSV file
        $fileName = "winers_list.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($exportData) {
            $file = fopen('php://output', 'w');

            // Add header row
            fputcsv($file, array_keys($exportData[0]));

            // Add data rows
            foreach ($exportData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }



    public function exportVotes($month, $year)
    {

        if (!Auth::check()) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

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

        // Fetch all votes for the given month and year
        $votes = Vote::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->with('user', 'product')
            ->get();

        if ($votes->isEmpty()) {
            return $this->successResponse('No votes found to export.', ['votes' => []]);
        }

        $exportData = $votes->map(function ($vote) {
            return [
                'Vote ID' => $vote->id,
                'User Name' => ($vote->user->first_name ?? '') . ' ' . ($vote->user->last_name ?? 'N/A'),
                'Product Name' => $vote->product->product_name ?? 'N/A',
                'Date' => $vote->created_at->format('Y-m-d H:i:s'),
                "Email" => $vote->user->email ?? 'N/A',
            ];
        })->toArray();

        // Generate CSV file
        $fileName = "votes_{$year}_{$month}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($exportData) {
            $file = fopen('php://output', 'w');

            // Add header row
            fputcsv($file, array_keys($exportData[0]));

            // Add data rows
            foreach ($exportData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function votingHistory()
    {
        return $this->safeCall(function () {

            if (!Auth::check()) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $votes = Vote::where('user_id', Auth::user()->id)
                ->with('product')
                ->get();

            return $this->successResponse('Voting history retrieved successfully.', ['votes' => $votes]);
        });
    }
}
