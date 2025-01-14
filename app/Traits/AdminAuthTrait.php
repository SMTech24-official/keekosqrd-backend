<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\AdminGraphTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\UserAuthStoreRequest;

trait AdminAuthTrait
{
    use AdminGraphTrait;
    public function store(UserAuthStoreRequest $request)
    {
        return $this->safeCall(function () use ($request) {

            // Check if the authenticated user is an admin
            if (!Auth::check() || !Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Create a new user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Generate a JWT token for the new user
            $token = auth()->login($user);

            // Create a cookie with the token
            $cookie = cookie('access_token', $token, 60 * 24); // Token valid for 1 day

            // Return success response with the token
            return $this->successResponse(
                'User created successfully',
                ['token' => $token]
            )->cookie($cookie);
        });
    }


    public function destroy($id)
    {
        return $this->safeCall(function () use ($id) {

            if (!Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Find the user by ID or throw an exception if not found
            $user = User::findOrFail($id);

            // Delete the user
            $user->delete();

            return $this->successResponse(
                'User deleted successfully',
                ['user' => $user]
            );
        });
    }

    public function search(Request $request)
    {
        return $this->safeCall(function () use ($request) {
            // Ensure the user is authenticated and is an admin
            $user = Auth::user();
            if (!$user || !$user->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Validate the input using the request instance
            $validated = $request->validate([
                'query' => 'required|string|min:1',
            ]);

            // Search for users matching the query
            $query = $validated['query'];
            $users = User::where('first_name', 'like', '%' . $query . '%')
                ->orWhere('last_name', 'like', '%' . $query . '%')
                ->paginate(10);

            // Return paginated results with metadata
            return $this->successResponse('Users retrieved successfully.', [
                'data' => $users->items(),
                'pagination' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                ],
            ]);
        });
    }
}
