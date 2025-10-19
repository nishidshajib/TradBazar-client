<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'user']);

        if ($request->has('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->paginate(10);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'quantity' => 'required|integer|min:0',
            'image' => 'nullable|string', // Base64 or URL
        ]);

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'category_id' => $request->category_id,
            'quantity' => $request->quantity,
            'image' => $request->image,
            'user_id' => auth()->id(),
        ]);

        return response()->json($product->load(['category', 'user']), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'user']));
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'quantity' => 'sometimes|integer|min:0',
            'image' => 'nullable|string',
        ]);

        $product->update($request->only([
            'name', 'description', 'price', 'category_id', 'quantity', 'image'
        ]));

        return response()->json($product->load(['category', 'user']));
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);
        
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function vendorProducts(User $user)
    {
        $products = $user->products()->with('category')->paginate(10);

        return response()->json($products);
    }
}
