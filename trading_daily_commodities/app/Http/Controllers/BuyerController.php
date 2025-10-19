<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuyerController extends Controller
{
    /**
     * Browse products (public access)
     */
    public function browseProducts(Request $request)
    {
        $query = Product::with(['category', 'seller']);

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        $products = $query->paginate(12);

        return response()->json($products);
    }

    /**
     * Search products (public access)
     */
    public function searchProducts(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $searchTerm = $request->query;

        $products = Product::with(['category', 'seller'])
            ->where(function($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%");
            })
            ->paginate(12);

        return response()->json($products);
    }

    /**
     * Place order (takes items from user's cart)
     */
    public function placeOrder(Request $request)
    {
        $userId = auth()->id();
        
        $request->validate([
            'cart_items' => 'sometimes|array',
            'cart_items.*.product_id' => 'required|exists:products,id',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'bargain_id' => 'sometimes|exists:bargains,id',
            'use_bargain_price' => 'sometimes|boolean'
        ]);

        DB::beginTransaction();

        try {
            $total = 0;
            $orderItems = [];

            // Handle bargain-based purchase
            if ($request->has('bargain_id') && $request->use_bargain_price) {
                $bargain = Bargain::with('product')->findOrFail($request->bargain_id);
                
                // Validate bargain ownership and status
                if ($bargain->user_id !== $userId) {
                    throw new \Exception("You don't own this bargain");
                }
                
                if ($bargain->status !== 'accepted') {
                    throw new \Exception("Bargain is not accepted yet");
                }

                $product = $bargain->product;
                $bargainPrice = $bargain->offered_price;
                
                // Check stock
                if ($product->quantity < 1) {
                    throw new \Exception("Product is out of stock");
                }

                $total = $bargainPrice;
                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => $bargainPrice
                ];

                // Update product quantity
                $product->decrement('quantity', 1);
                
                // Mark bargain as used
                $bargain->update(['status' => 'completed']);

            } else {
                // Handle regular cart-based purchase
                $cartItems = Cart::with('product')->where('user_id', $userId)->get();

                if ($cartItems->isEmpty()) {
                    throw new \Exception('Cart is empty');
                }

                foreach ($cartItems as $cartItem) {
                    $itemTotal = $cartItem->product->price * $cartItem->quantity;
                    $total += $itemTotal;
                    
                    $orderItems[] = [
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->product->price
                    ];

                    // Update product quantity
                    $product = $cartItem->product;
                    if ($product->quantity < $cartItem->quantity) {
                        throw new \Exception("Insufficient stock for product: {$product->name}");
                    }
                    $product->decrement('quantity', $cartItem->quantity);
                }

                // Clear cart
                Cart::where('user_id', $userId)->delete();
            }

            // Create order
            $order = Order::create([
                'user_id' => $userId,
                'total' => $total,
                'status' => 'pending'
            ]);

            // Create order items
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price']
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Order placed successfully',
                'order' => $order->load(['orderItems.product', 'buyer']),
                'used_bargain' => isset($bargain)
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * View order history (only user's orders)
     */
    public function viewOrderHistory()
    {
        $orders = Order::with(['orderItems.product', 'transaction'])
                      ->where('user_id', auth()->id())
                      ->orderBy('created_at', 'desc')
                      ->paginate(10);

        return response()->json($orders);
    }

    /**
     * Get single order details
     */
    public function getOrderDetails(Order $order)
    {
        // Ensure user can only view their own orders
        if ($order->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($order->load(['orderItems.product', 'transaction', 'refund']));
    }

    /**
     * Get order tracking with status history
     */
    public function getOrderTracking(Order $order)
    {
        // Ensure user can only view their own orders
        if ($order->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'order' => $order->load(['statusHistory.updatedBy', 'orderItems.product']),
            'current_status' => $order->status,
            'tracking_history' => $order->statusHistory,
        ]);
    }

    /**
     * Request refund
     */
    public function requestRefund(Request $request, Order $order)
    {
        // Ensure user can only refund their own orders
        if ($order->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'delivered') {
            return response()->json(['error' => 'Can only request refund for delivered orders'], 400);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $refund = $order->refund()->create([
            'amount' => $order->total,
            'status' => 'Requested',
            'reason' => $request->reason,
            'requested_at' => now()
        ]);

        return response()->json([
            'message' => 'Refund requested successfully',
            'refund' => $refund
        ], 201);
    }
}
