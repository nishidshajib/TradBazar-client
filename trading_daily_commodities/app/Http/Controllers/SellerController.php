<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    /**
     * Store a new product (automatically set user_id to auth()->id())
     */
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0.01',
            'min_price' => 'nullable|numeric|min:0.01|lt:price',
            'category_id' => 'required|exists:categories,id',
            'quantity' => 'required|integer|min:0',
            'image' => 'nullable|string',
        ]);

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'min_price' => $request->min_price,
            'category_id' => $request->category_id,
            'quantity' => $request->quantity,
            'image' => $request->image,
            'user_id' => auth()->id(), // Automatically set to authenticated seller
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load(['category', 'seller']),
            'bargaining_enabled' => $product->isBargainingEnabled()
        ], 201);
    }

    /**
     * Update product (use ProductPolicy to authorize)
     */
    public function updateProduct(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0.01',
            'min_price' => 'nullable|numeric|min:0.01|lt:price',
            'category_id' => 'sometimes|exists:categories,id',
            'quantity' => 'sometimes|integer|min:0',
            'image' => 'nullable|string',
        ]);

        // Validate min_price is less than price if both are provided
        if ($request->has('price') && $request->has('min_price')) {
            if ($request->min_price >= $request->price) {
                return response()->json([
                    'error' => 'Minimum price must be less than the maximum price'
                ], 400);
            }
        }

        $product->update($request->only([
            'name', 'description', 'price', 'min_price', 'category_id', 'quantity', 'image'
        ]));

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->load(['category', 'seller']),
            'bargaining_enabled' => $product->isBargainingEnabled()
        ]);
    }

    /**
     * View orders received (orders containing seller's products)
     */
    public function viewOrdersReceived()
    {
        $sellerId = auth()->id();
        
        // Get orders that contain products belonging to this seller
        $orders = Order::whereHas('products', function($query) use ($sellerId) {
            $query->where('user_id', $sellerId);
        })->with(['buyer', 'products' => function($query) use ($sellerId) {
            $query->where('user_id', $sellerId);
        }])->paginate(10);

        return response()->json($orders);
    }

    /**
     * Get seller's products
     */
    public function getMyProducts()
    {
        $products = Product::where('user_id', auth()->id())
                          ->with('category')
                          ->paginate(10);

        return response()->json($products);
    }

    /**
     * Delete product (with authorization)
     */
    public function deleteProduct(Product $product)
    {
        $this->authorize('delete', $product);
        
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Update order status (for orders containing seller's products)
     */
    public function updateOrderStatus(Request $request, Order $order)
    {
        // Check if the order contains products from this seller
        $hasSellerProducts = $order->products()->where('user_id', auth()->id())->exists();
        
        if (!$hasSellerProducts) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,confirmed,shipped,delivered,cancelled',
            'comment' => 'nullable|string|max:500',
        ]);

        // Use the new status tracking method
        $order->updateStatus($request->status, $request->comment, auth()->id());

        return response()->json([
            'message' => 'Order status updated successfully', 
            'order' => $order->load('statusHistory.updatedBy')
        ]);
    }
}
