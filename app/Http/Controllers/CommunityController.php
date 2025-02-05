<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Community;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Traits\HandlesApiResponse;

class CommunityController extends Controller
{
    use HandlesApiResponse;

    public function is_approved($id)
    {
        return $this->safeCall(function () use ($id) {
            // Check if user is authenticated and is an admin using is_admin field
            if (!Auth::check() || !Auth::user()->is_admin) {
                return $this->errorResponse('Unauthorized. Only admins can approve communities.', 403);
            }

            $community = Community::find($id);
            if (!$community) {
                return $this->errorResponse('Community not found.', 404);
            }

            $community->is_approved = '1';
            $community->save();

            return $this->successResponse(
                'Community approved successfully.',
                ['community' => $community]
            );
        });
    }



    public function index()
    {
        return $this->safeCall(function () {
            $communities = Community::orderBy('created_at', 'desc')->limit(12)->get();
            return $this->successResponse('Communities retrieved successfully.', ['communities' => $communities]);
        });
    }


    public function store(Request $request)
    {
        return $this->safeCall(function () use ($request) {
            $validator = Validator::make($request->all(), [
                'product_name' => 'required|string|max:255',
                'product_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:102400',
                'brand' => 'required|string|max:255',
                'model' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }
            if (!Auth::check() || !Auth::user()->is_admin) {
                return $this->errorResponse('Unauthorized. Only admins can view communities.', 403);
            }

            // Handle File Upload Properly
            $productImagePath = null;
            if ($request->hasFile('product_image')) {
                $productImagePath = $request->file('product_image')->store('community_images', 'public');
            }

            // Create the community record
            $community = Community::create([
                'user_id' => Auth::id(),
                'product_name' => $request->product_name,
                'product_image' => $productImagePath, // Store correct path
                'brand' => $request->brand,
                'model' => $request->model,
                'description' => $request->description
            ]);

            return $this->successResponse('Community created successfully.', ['community' => $community], 201);
        });
    }


    public function show($id)
    {
        return $this->safeCall(function () use ($id) {
            // Ensure the user is authenticated and is an admin
            if (!Auth::check() || !Auth::user()->is_admin) {
                return $this->errorResponse('Unauthorized. Only admins can view communities.', 403);
            }

            $community = Community::with('user')->find($id);
            if (!$community) {
                return $this->errorResponse('Community not found.', 404);
            }

            return $this->successResponse('Community retrieved successfully.', ['community' => $community]);
        });
    }


    public function update(Request $request, $id)
    {
        return $this->safeCall(function () use ($request, $id) {
            $community = Community::find($id);
            if (!$community) {
                return $this->errorResponse('Community not found.', 404);
            }

            if (!Auth::check() || !Auth::user()->is_admin) {
                return $this->errorResponse('Unauthorized. Only admins can view communities.', 403);
            }

            $validator = Validator::make($request->all(), [
                'product_name' => 'sometimes|string|max:255',
                'product_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'brand' => 'sometimes|string|max:255',
                'model' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            if ($request->hasFile('product_image')) {
                $productImagePath = $request->file('product_image')->store('community_images', 'public');

                $community->product_image = $productImagePath;
            }

            $community->update($request->only(['product_name', 'brand', 'model']));

            $community->product_image = $community->product_image ? asset('storage/' . $community->product_image) : null;

            return $this->successResponse('Community updated successfully.', ['community' => $community]);
        });
    }



    public function destroy($id)
    {
        return $this->safeCall(function () use ($id) {
            // Ensure the user is authenticated and is an admin
            if (!Auth::check() || !Auth::user()->is_admin) {
                return $this->errorResponse('Unauthorized. Only admins can delete communities.', 403);
            }

            $community = Community::find($id);
            if (!$community) {
                return $this->errorResponse('Community not found.', 404);
            }

            $community->delete();
            return $this->successResponse('Community deleted successfully.');
        });
    }

}
