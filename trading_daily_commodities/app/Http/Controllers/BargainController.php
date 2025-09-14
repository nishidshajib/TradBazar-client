<?php

namespace App\Http\Controllers;

use App\Models\Bargain;
use App\Models\Product;
use Illuminate\Http\Request;

class BargainController extends Controller
{
    public function index()
    {
        return auth()->user()->bargains()->with('product')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'offered_price' => 'required|numeric|min:0',
        ]);

        $product = Product::find($request->product_id);

        if (auth()->user()->role !== 'buyer') {
            return response()->json(['error' => 'Only buyers can bargain'], 403);
        }

        // Create bargain
        $bargain = Bargain::create([
            'product_id' => $request->product_id,
            'user_id' => auth()->id(),
            'offered_price' => $request->offered_price,
        ]);

        // System logic for bargaining
        if ($request->offered_price >= $product->price) {
            $bargain->status = 'accepted';
            // Optionally create order here
            // Order::create([...]);
        } elseif ($request->offered_price < $product->min_price) {
            $bargain->status = 'rejected';
        } else {
            $counter = ($product->price + $request->offered_price) / 2;
            $bargain->counter_price = $counter;
            $bargain->status = 'countered';
        }

        $bargain->save();

        return response()->json($bargain, 201);
    }

    // Buyer responds to counter (accept or new offer)
    public function respond(Request $request, Bargain $bargain)
    {
        if (auth()->id() !== $bargain->user_id || $bargain->status !== 'countered') {
            return response()->json(['error' => 'Invalid bargain'], 400);
        }

        $request->validate([
            'action' => 'required|in:accept,new_offer',
            'new_offered_price' => 'required_if:action,new_offer|numeric|min:0',
        ]);

        if ($request->action === 'accept') {
            $bargain->status = 'accepted';
            // Optionally create order with counter_price
        } else {
            // Treat as new offer, re-run logic
            $bargain->offered_price = $request->new_offered_price;
            $bargain->status = 'pending';
            $bargain->save();

            // Re-apply system logic
            $product = $bargain->product;
            if ($request->new_offered_price >= $product->price) {
                $bargain->status = 'accepted';
            } elseif ($request->new_offered_price < $product->min_price) {
                $bargain->status = 'rejected';
            } else {
                $counter = ($product->price + $request->new_offered_price) / 2;
                $bargain->counter_price = $counter;
                $bargain->status = 'countered';
            }
        }

        $bargain->save();

        return $bargain;
    }
}
