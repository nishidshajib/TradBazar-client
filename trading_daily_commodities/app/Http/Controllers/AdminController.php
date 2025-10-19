<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Approve a seller
     */
    public function approveSeller(User $user)
    {
        if (!$user->isSeller()) {
            return response()->json(['error' => 'User is not a seller'], 400);
        }

        $user->update(['status' => 'Approved']);

        return response()->json(['message' => 'Seller approved successfully', 'user' => $user]);
    }

    /**
     * Block a user
     */
    public function blockUser(User $user)
    {
        $user->update(['status' => 'Blocked']);

        return response()->json(['message' => 'User blocked successfully', 'user' => $user]);
    }

    /**
     * Get all users
     */
    public function getAllUsers(Request $request)
    {
        $query = User::query();

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $users = $query->paginate(10);

        return response()->json($users);
    }

    /**
     * Manage categories - create new category
     */
    public function createCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string'
        ]);

        $category = Category::create($request->all());

        return response()->json(['message' => 'Category created successfully', 'category' => $category], 201);
    }

    /**
     * Update category
     */
    public function updateCategory(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string'
        ]);

        $category->update($request->all());

        return response()->json(['message' => 'Category updated successfully', 'category' => $category]);
    }

    /**
     * Delete category
     */
    public function deleteCategory(Category $category)
    {
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    /**
     * Get all categories
     */
    public function getCategories()
    {
        $categories = Category::with('products')->get();

        return response()->json($categories);
    }
}
