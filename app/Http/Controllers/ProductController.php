<?php

namespace App\Http\Controllers;

use App\Jobs\ProductStoreJob;
use App\Models\Product;
use App\Traits\HandlesApiResponse;
use Illuminate\Http\Request;
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
            $validator = Validator::make($request->all(), [
                'product_name' => 'required|string|max:255',
                'brand_name' => 'required|string|max:255',
                'model' => 'nullable|string|max:255',
                'size' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'product_image' => 'nullable|image|max:2048',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors()->toJson());
            }

            $data = $validator->validated();

            if ($request->hasFile('product_image')) {
                $data['product_image'] = $request->file('product_image')->store('product_images', 'public');
            }

            ProductStoreJob::dispatch($data);

            return $this->successResponse('Product is being stored', [
                'data' => $data
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
            return $this->successResponse('Product retrieved successfully',[
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

            $validator = Validator::make($request->all(), [
                'product_name' => 'nullable|string|max:255',
                'brand_name' => 'nullable|string|max:255',
                'model' => 'nullable|string|max:255',
                'size' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'product_image' => 'nullable|image|max:2048',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors()->toJson());
            }

            $data = $validator->validated();

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



    /**
     * Delete a product by ID.
     */
    public function destroy($id)
    {
        return $this->safeCall(function () use ($id) {
            $product = Product::findOrFail($id);
            $product->delete();

            return $this->successResponse('Product deleted successfully',
                ['product' => $product]
            );
        });
    }
}
