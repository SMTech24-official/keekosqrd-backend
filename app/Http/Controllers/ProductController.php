<?php

namespace App\Http\Controllers;

use App\Models\Vote;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Jobs\ProductStoreJob;
use App\Traits\HandlesApiResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
                'product_image' => 'nullable|image|max:2048',
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
            $products = Product::all();
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
                'product_image' => 'nullable|image|max:2048',
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

            return $this->successResponse(
                'Product deleted successfully',
                ['product' => $product]
            );
        });
    }

    /**
     * Get all active products.
     */
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
            // Check if the user is authenticated
            if (!Auth::check()) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $userId = Auth::id();

            // Check if the product exists
            $product = Product::find($productId);

            if (!$product) {
                return $this->errorResponse('Product not found.', 404);
            }

            // also check if the product is active
            if (!$product->status) {
                return $this->errorResponse('Product is not active.', 403);
            }
            // also check if the user voted for other products
            $userVotes = Vote::where('user_id', $userId)->count();
            
            if ($userVotes >= 1) {
                return $this->errorResponse('You have already voted for 1 products.', 403);
            }
            // Check if the user has already voted for this product
            $vote = Vote::where('user_id', $userId)->where('product_id', $productId)->first();

            if ($vote) {
                return $this->errorResponse('You have already voted for this product.', 403);
            }

            // Create a new vote entry
            Vote::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'votes' => 1,
            ]);

            // Return the updated product and votes count
            $totalVotes = Vote::where('product_id', $productId)->sum('votes');

            return $this->successResponse('Vote added successfully', [
                'product' => $product,
                'total_votes' => $totalVotes,
            ]);
        });
    }

}
