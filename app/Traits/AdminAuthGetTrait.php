<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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

    public function exportUsers(Request $request)
    {
        if (!Auth::user()->is_admin) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }

        // Fetch all users
        $users = User::all();

        // Prepare the CSV content
        $csvContent = '';
        $headers = ['ID', 'Firs Name', 'Last Name', 'Country', 'City', 'Zip Code', 'Address', 'status', 'Email', 'Created At', 'Updated At'];
        $csvContent .= implode(',', $headers) . "\n";

        foreach ($users as $user) {
            $csvContent .= implode(',', [
                $user->id,
                $user->first_name,
                $user->last_name,
                $user->country,
                $user->city,
                $user->zip_code,
                $user->address,
                $user->status,
                $user->is_admin ? 'Admin' : 'User',
                $user->email,
                $user->created_at,
                $user->updated_at,
            ]) . "\n";
        }

        // Return the response directly (bypassing `safeCall`)
        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users_export_' . date('Y-m-d_H-i-s') . '.csv"',
        ]);
    }

    public function show()
    {
        return $this->safeCall(function () {
            // Ensure the user is authenticated
            if (!Auth::check()) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Fetch the authenticated user along with their payments
            $authenticatedUser = Auth::user();

            $user = User::with('payments')->find($authenticatedUser->id);

            return $this->successResponse(
                'User and payments retrieved successfully',
                ['user' => $user]  // Return the user with payments
            );
        });
    }



    public function update(Request $request, $id)
    {
        return $this->safeCall(function () use ($request, $id) {
            // Ensure the user is authenticated
            if (!Auth::check()) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Fetch the authenticated user
            $authenticatedUser = Auth::user();

            // Check if the authenticated user matches the requested user ID
            if ($authenticatedUser->id != $id) {
                return $this->errorResponse('You are not authorized to update this profile.', 403);
            }

            // Convert the 'status' field to boolean if it exists
            $input = $request->all();
            if (isset($input['status'])) {
                $input['status'] = filter_var($input['status'], FILTER_VALIDATE_BOOLEAN);
            }

            // Validate the request
            $validator = Validator::make($input, [
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'password' => 'nullable|string|min:8|confirmed',
                'country' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:255',
                'zip_code' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:255',
                'status' => 'nullable|boolean', // Validate as boolean
                'profile_image' => 'nullable|image|max:2048',
                'last_login_at' => 'nullable|date',
                'is_admin' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            // Collect the validated data
            $data = $validator->validated();

            // Ensure the email cannot be updated
            unset($data['email']);

            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                // Delete the old profile image if it exists
                if ($authenticatedUser->profile_image && \Storage::disk('public')->exists($authenticatedUser->profile_image)) {
                    \Storage::disk('public')->delete($authenticatedUser->profile_image);
                }

                // Store the new profile image
                $data['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
            }

            // Hash the password if it is being updated
            if (isset($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }

            // Update the user record
            $authenticatedUser->update($data);

            return $this->successResponse(
                'User updated successfully',
                ['user' => $authenticatedUser]
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
