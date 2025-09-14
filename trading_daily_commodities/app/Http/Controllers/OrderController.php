<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        return auth()->user()->orders()->with('product')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::find($request->product_id);

        if ($product->stock < $request->quantity) {
            return response()->json(['error' => 'Insufficient stock'], 400);
        }

        $total_price = $product->price * $request->quantity;

        $order = $request->user()->orders()->create([
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'total_price' => $total_price,
        ]);

        // Update stock
        $product->stock -= $request->quantity;
        $product->save();

        return response()->json($order, 201);
    }

    public function updateStatus(Request $request, Order $order)
    {
        if (auth()->id() !== $order->product->user_id) { // Only seller can update
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'required|in:paid,shipped,delivered,cancelled',
        ]);

        $order->status = $request->status;
        $order->save();

        return $order;
    }
}
