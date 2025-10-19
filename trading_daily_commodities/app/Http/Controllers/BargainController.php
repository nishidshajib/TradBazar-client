<?php

namespace App\Http\Controllers;

use App\Models\Bargain;
use App\Models\Product;
use Illuminate\Http\Request;

class BargainController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        if ($user->isBuyer()) {
            $bargains = Bargain::where('user_id', $user->id)
                             ->with(['product.seller'])
                             ->orderBy('created_at', 'desc')
                             ->get();
        } elseif ($user->isSeller()) {
            $bargains = Bargain::whereHas('product', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })->with(['product', 'user'])
              ->orderBy('created_at', 'desc')
              ->get();
        } else {
            $bargains = Bargain::with(['product.seller', 'user'])
                             ->orderBy('created_at', 'desc')
                             ->get();
        }

        return response()->json($bargains);
    }

    /**
     * Get product bargaining information and user's bargain status
     */
    public function getProductBargainInfo($productId)
    {
        $product = Product::findOrFail($productId);
        $user = auth()->user();

        $userBargain = null;
        if ($user) {
            $userBargain = Bargain::where('product_id', $productId)
                                 ->where('user_id', $user->id)
                                 ->latest()
                                 ->first();
        }

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'display_price' => $product->price, // Show maximum price
                'minimum_price' => $product->min_price,
                'maximum_price' => $product->price,
                'bargaining_enabled' => $product->isBargainingEnabled(),
            ],
            'user_bargain' => $userBargain,
            'can_bargain' => $product->isBargainingEnabled() && (!$userBargain || $userBargain->status === 'rejected'),
            'can_purchase' => $userBargain && $userBargain->status === 'accepted',
            'purchase_price' => $userBargain && $userBargain->status === 'accepted' ? $userBargain->offered_price : $product->price,
            'bargain_status_message' => $userBargain ? $this->getBargainStatusMessage($userBargain) : null
        ]);
    }

    private function getBargainStatusMessage($bargain)
    {
        switch ($bargain->status) {
            case 'pending':
                return 'Your bargain is being reviewed by the seller';
            case 'accepted':
                return 'Congratulations! Your bargain was accepted. You can now purchase at ₹' . number_format($bargain->offered_price, 2);
            case 'rejected':
                return 'Your bargain was rejected. You can try with a higher offer.';
            case 'countered':
                return 'Seller made a counter offer of ₹' . number_format($bargain->counter_price, 2);
            default:
                return 'Unknown status';
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'offered_price' => 'required|numeric|min:0.01',
        ]);

        $product = Product::findOrFail($request->product_id);
        $user = auth()->user();

        if (!$user->isBuyer()) {
            return response()->json(['error' => 'Only buyers can bargain'], 403);
        }

        // Check if product allows bargaining
        if (!$product->isBargainingEnabled()) {
            return response()->json([
                'error' => 'Bargaining is not enabled for this product',
                'message' => 'This product has a fixed price'
            ], 400);
        }

        // Check if user is trying to bargain on their own product
        if ($product->user_id === $user->id) {
            return response()->json(['error' => 'You cannot bargain on your own product'], 400);
        }

        // Check existing bargains
        $existingBargain = Bargain::where('product_id', $request->product_id)
                                 ->where('user_id', $user->id)
                                 ->whereIn('status', ['pending', 'accepted'])
                                 ->first();

        if ($existingBargain) {
            return response()->json([
                'error' => 'You already have an active bargain for this product',
                'existing_bargain' => $existingBargain->load('product'),
                'can_purchase' => $existingBargain->status === 'accepted'
            ], 400);
        }

        // Create bargain
        $bargain = Bargain::create([
            'product_id' => $request->product_id,
            'user_id' => $user->id,
            'offered_price' => $request->offered_price,
            'status' => 'pending'
        ]);

        // Enhanced bargaining logic
        if ($request->offered_price >= $product->price) {
            // Offer meets or exceeds maximum price - auto accept
            $bargain->update(['status' => 'accepted']);
            $message = 'Bargain accepted! You offered the full price or more.';
            $canPurchase = true;
        } elseif ($request->offered_price >= $product->min_price) {
            // Offer meets minimum price - auto accept
            $bargain->update(['status' => 'accepted']);
            $message = 'Bargain accepted! Your offer meets the minimum acceptable price.';
            $canPurchase = true;
        } else {
            // Offer below minimum - rejected
            $bargain->update(['status' => 'rejected']);
            $message = 'Bargain rejected. Your offer is below the minimum acceptable price.';
            $canPurchase = false;
        }

        return response()->json([
            'message' => $message,
            'bargain' => $bargain->fresh()->load('product'),
            'can_purchase' => $canPurchase,
            'minimum_price' => $product->min_price,
            'maximum_price' => $product->price,
            'purchase_price' => $canPurchase ? $bargain->offered_price : null
        ], 201);
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
