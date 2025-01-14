<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait AdminGraphTrait
{
    public function getGraphData($year = null, $month = null)
    {
        return $this->safeCall(function () use ($year, $month) {
            // Check if the current user is an admin
            if (!Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Query to count users by year and month
            $query = User::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
                ->groupBy('year', 'month')
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc');

            // Apply filters if year or month are provided
            if ($year) {
                $query->having('year', '=', $year);
            }
            if ($month) {
                $query->having('month', '=', $month);
            }

            $userCounts = $query->get();

            // Format data for graph plotting
            $userByMonth = $userCounts->map(function ($item) {
                return [
                    'year' => $item->year,
                    'month' => $item->month,
                    'count' => $item->count,
                ];
            });

            return $this->successResponse(
                'Graph data retrieved successfully.',
                ['userCounts' => $userByMonth]
            );
        });
    }
}
