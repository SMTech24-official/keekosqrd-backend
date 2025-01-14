<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\UserAuthStoreRequest;

trait AdminAuthGetTrait
{
    use AdminAuthTrait;
    public function users()
    {
        return $this->safeCall(function () {
            if (!Auth::user()->is_admin) {
                return $this->errorResponse(
                    'You are not authorized to perform this action.',
                    403
                );
            }
            $users = User::all();
            $data = $users->count();

            $activeUsers = User::where('status', 'active')->count();
            $inactiveUsers = User::where('status', 'inactive')->count();

            // Count users by month
            $userCounts = User::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
                ->groupBy('year', 'month')
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get();

            $newUsers = User::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->count();

            // Calculate increment percentage between months
            $incrementCounts = [];
            foreach ($userCounts as $key => $current) {
                if ($key > 0) {
                    $previous = $userCounts[$key - 1];
                    $incrementPercentage = ($previous->count > 0)
                        ? (($current->count - $previous->count) / $previous->count) * 100
                        : 0;

                    $incrementCounts[] = [
                        'year' => $current->year,
                        'month' => $current->month,
                        'count' => $current->count,
                        'increment_percentage' => round($incrementPercentage, 2),
                    ];
                } else {
                    $incrementCounts[] = [
                        'year' => $current->year,
                        'month' => $current->month,
                        'count' => $current->count,
                        'increment_percentage' => 0, // No increment for the first month
                    ];
                }
            }

            return $this->successResponse(
                'Users fetched successfully',
                [
                    'totalUsers' => $data,
                    'activeUsers' => $activeUsers,
                    'inactiveUsers' => $inactiveUsers,
                    'newUsers' => $newUsers,
                    'incrementCounts' => $incrementCounts,
                    'users' => $users
                ],
            );
        });
    }


    public function getAllUsers()
    {
        return $this->safeCall(function () {

            if (!Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $users = User::all();

            return $this->successResponse(
                'Users retrieved successfully',
                ['users' => $users]
            );
        });
    }


    public function activeInactive($id)
    {
        return $this->safeCall(function () use ($id) {
            // Check if the current user is an admin
            if (!Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Retrieve the user by ID
            $user = User::find($id);

            if (!$user) {
                return $this->errorResponse('User not found.', 404);
            }

            // Return the user's status
            return $this->successResponse(
                'User status retrieved and updated successfully.',
                [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'status' => $user->status,
                        'last_login_at' => $user->last_login_at
                    ]
                ]
            );
        });
    }
}
