<?php

namespace App\Http\Controllers;

use App\Models\Vote;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Jobs\ProductStoreJob;
use App\Traits\HandlesApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Cashier\Subscription;


class ProductController extends Controller
{
    use HandlesApiResponse;

    /**
     * Store a new product.
     */
    public function store(Request $request)
    {
        return $this->safeCall(function () use ($request) {
            // Convert 'status' to boolean if present
            $input = $request->all();
            if (isset($input['status'])) {
                $input['status'] = filter_var($input['status'], FILTER_VALIDATE_BOOLEAN);
            }

            // Validate input
            $validator = Validator::make($input, [
                'product_name' => 'required|string|max:255',
                'brand_name' => 'required|string|max:255',
                'model' => 'nullable|string|max:255',
                'size' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                // 'product_image' => 'nullable|image|max:2048',
                'product_image' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,svg,webp|max:2048',
                'status' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors()->toJson());
            }

            $data = $validator->validated();

            // Handle file upload
            if ($request->hasFile('product_image')) {
                $data['product_image'] = $request->file('product_image')->store('product_images', 'public');
            }
            // only is_admin can store a product
            if (!Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }
            // Dispatch the job
            ProductStoreJob::dispatch($data);


            return $this->successResponse('Product is being stored', [
                'data' => $data,
            ]);
        });
    }




    /**
     * Get all products.
     */
    public function index()
    {
        return $this->safeCall(function () {
            $products = Product::paginate(10);
            return $this->successResponse('Products retrieved successfully', [
                'products' => $products
            ]);
        });
    }

    /**
     * Get a single product by ID.
     */
    public function show($id)
    {
        return $this->safeCall(function () use ($id) {
            $product = Product::findOrFail($id);
            return $this->successResponse('Product retrieved successfully', [
                'product' => $product
            ]);
        });
    }

    /**
     * Update a product by ID.
     */
    public function update(Request $request, $id)
    {
        return $this->safeCall(function () use ($request, $id) {
            $product = Product::findOrFail($id);

            // Convert 'status' to boolean if present
            $input = $request->all();
            if (isset($input['status'])) {
                $input['status'] = filter_var($input['status'], FILTER_VALIDATE_BOOLEAN);
            }

            // Validate input
            $validator = Validator::make($input, [
                'product_name' => 'nullable|string|max:255',
                'brand_name' => 'nullable|string|max:255',
                'model' => 'nullable|string|max:255',
                'size' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                // 'product_image' => 'nullable|image|max:2048',
                'product_image' => 'nullable|mimes:jpg,jpeg,png,gif,bmp,svg,webp|max:10240',
                'status' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors()->toJson());
            }

            $data = $validator->validated();

            // Handle file upload
            if ($request->hasFile('product_image')) {
                // Delete old image if it exists
                if ($product->product_image && \Storage::disk('public')->exists($product->product_image)) {
                    \Storage::disk('public')->delete($product->product_image);
                }

                $data['product_image'] = $request->file('product_image')->store('product_images', 'public');
            }

            $product->update($data);
            // only is_admin can update a product
            if (!Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            return $this->successResponse('Product updated successfully', [
                'product' => $product,
            ]);
        });
    }



    public function destroy($id)
    {
        return $this->safeCall(function () use ($id) {
            $product = Product::findOrFail($id);
            $product->delete();

            // only is_admin can delete a product
            if (!Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            return $this->successResponse(
                'Product deleted successfully',
                ['product' => $product]
            );
        });
    }

    public function productActive($id)
    {
        return $this->safeCall(function () use ($id) {
            // Check if the current user is an admin
            if (!Auth::user()->is_admin) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            // Retrieve the user by ID
            $product = Product::find($id);

            if (!$product) {
                return $this->errorResponse('product not found.', 404);
            }

            // if status is false make it true
            if ($product->status == false) {
                $product->status = true;
                $product->save();
            }

            // Return the user's status
            return $this->successResponse(
                'User status retrieved and updated successfully.',
                [
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'status' => $product->status,
                        'last_login_at' => $product->last_login_at
                    ]
                ]
            );
        });
    }


    public function activeProducts()
    {
        return $this->safeCall(function () {
            $products = Product::where('status', true)->get();
            return $this->successResponse('Active products retrieved successfully', [
                'products' => $products
            ]);
        });
    }



    public function vote(Request $request, $productId)
    {
        return $this->safeCall(function () use ($productId) {
            if (!Auth::check()) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $userId = Auth::id();

            // Retrieve the product; ensure it exists and is active before proceeding
            $product = Product::find($productId);
            if (!$product || !$product->status) {
                return $this->errorResponse('Product not found or is not active.', 404);
            }

            // Retrieve the latest active subscription
            $latestSubscription = Subscription::where('user_id', $userId)
                ->where('stripe_status', 'active')
                ->latest('created_at')
                ->first();

            if (!$latestSubscription) {
                return $this->errorResponse('You must have an active subscription to vote.', 403);
            }

            // Determine the start and end of the current subscription period
            $subscriptionStart = $latestSubscription->created_at;
            $subscriptionEnd = $subscriptionStart->copy()->addDays(30);

            // Check if now is within the current subscription period
            if (now()->greaterThan($subscriptionEnd)) {
                return $this->errorResponse('Your subscription period for voting has expired. Please renew your subscription.', 403);
            }

            // Check if the user has already voted in the current subscription period for any product
            $alreadyVotedThisPeriod = Vote::where('user_id', $userId)
                ->whereBetween('created_at', [$subscriptionStart, $subscriptionEnd])
                ->exists();

            if ($alreadyVotedThisPeriod) {
                return $this->errorResponse('You have already voted during this subscription period. You cannot vote for multiple products.', 403);
            }

            // Record the new vote
            $newVote = Vote::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'votes' => 1,
                'created_at' => now(),  // Ensure timestamps are used to track voting time
            ]);

            $totalVotes = Vote::where('product_id', $productId)->sum('votes');

            return $this->successResponse('Vote added successfully', [
                'product' => $product,
                'total_votes' => $totalVotes,
            ]);
        });
    }





    // public function vote(Request $request, $productId)
    // {
    //     return $this->safeCall(function () use ($productId) {
    //         if (!Auth::check()) {
    //             return $this->errorResponse('You are not authorized to perform this action.', 403);
    //         }

    //         $userId = Auth::id(); // Simpler way to get the user ID

    //         // Check if the user has an active subscription
    //         $activeDefaultSubscription = Subscription::where('user_id', $userId)
    //             ->where('stripe_status', 'active')
    //             ->first();

    //         if (!$activeDefaultSubscription) {
    //             return $this->errorResponse('You must be subscribed to vote.', 403);
    //         }

    //         // Check if the product exists and is active
    //         $product = Product::find($productId);
    //         if (!$product || !$product->status) {
    //             return $this->errorResponse('Product not found or is not active.', 404);
    //         }

    //         // Check if user has already voted for this product recently based on subscription rules
    //         $lastVote = Vote::where('user_id', $userId)
    //             ->latest('created_at')
    //             ->first();

    //         $timeAllowedBetweenVotes = $this->getTimeAllowedBetweenVotes($activeDefaultSubscription); // Implement this method based on subscription rules

    //         if ($lastVote && now()->diffInHours($lastVote->created_at) < $timeAllowedBetweenVotes) {
    //             return $this->errorResponse('You can only vote once every ' . $timeAllowedBetweenVotes/24 . ' days.', 403);
    //         }


    //         // Cast new vote
    //         $newVote = Vote::create([
    //             'user_id' => $userId,
    //             'product_id' => $productId,
    //             'votes' => 1,
    //         ]);

    //         $totalVotes = Vote::where('product_id', $productId)->sum('votes');

    //         return $this->successResponse('Vote added successfully', [
    //             'product' => $product,
    //             'total_votes' => $totalVotes,
    //         ]);
    //     });
    // }

    // // Example helper method to determine time allowed between votes
    // // Example helper method to determine time allowed between votes
    // protected function getTimeAllowedBetweenVotes($subscription)
    // {
    //     // Adjust the return value based on the type or other attributes of the subscription
    //     switch ($subscription->type_id) {
    //         case 'default':
    //             return 24 * 30; // 30 days expressed in hours
    //         default:
    //             return 24 * 30; // Adjust as necessary for different subscription types
    //     }
    // }


    // public function vote(Request $request, $productId)
    // {
    //     return $this->safeCall(function () use ($productId) {
    //         if (!Auth::check()) {
    //             return $this->errorResponse('You are not authorized to perform this action.', 403);
    //         }

    //         $userId = Auth::id(); // Simpler way to get the user ID

    //         // Check if the user has an active subscription
    //         $activeDefaultSubscription = Subscription::where('user_id', $userId)
    //             ->where('stripe_status', 'active')
    //             ->first();

    //         if (!$activeDefaultSubscription) {
    //             return $this->errorResponse('You must be subscribed to vote.', 403);
    //         }

    //         $product = Product::find($productId);
    //         if (!$product) {
    //             return $this->errorResponse('Product not found.', 404);
    //         }

    //         if (!$product->status) {
    //             return $this->errorResponse('Product is not active.', 403);
    //         }

    //         if (Vote::where('user_id', $userId)->where('product_id', $productId)->exists()) {
    //             return $this->errorResponse('You have already voted for this product.', 403);
    //         }

    //         $newVote = Vote::create([
    //             'user_id' => $userId,
    //             'product_id' => $productId,
    //             'votes' => 1,
    //         ]);

    //         $totalVotes = Vote::where('product_id', $productId)->sum('votes');

    //         return $this->successResponse('Vote added successfully', [
    //             'product' => $product,
    //             'total_votes' => $totalVotes,
    //         ]);
    //     });
    // }


}
