<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\BargainController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CommunicationController;
use Illuminate\Support\Facades\Route;

// Public Routes - No authentication required
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public product browsing and searching
Route::get('/products', [BuyerController::class, 'browseProducts']);
Route::get('/products/search', [BuyerController::class, 'searchProducts']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/products/{product}/bargain-info', [BargainController::class, 'getProductBargainInfo']);

// Buyer Routes - Require authentication and buyer role
Route::middleware(['auth:sanctum', 'isBuyer'])->group(function () {
    // Cart management
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{cart}', [CartController::class, 'update']);
    Route::delete('/cart/{cart}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Order management
    Route::post('/orders', [BuyerController::class, 'placeOrder']);
    Route::get('/orders', [BuyerController::class, 'viewOrderHistory']);
    Route::get('/orders/{order}', [BuyerController::class, 'getOrderDetails']);
    Route::get('/orders/{order}/tracking', [BuyerController::class, 'getOrderTracking']);
    Route::post('/orders/{order}/refund', [BuyerController::class, 'requestRefund']);

    // Payment and transactions
    Route::post('/orders/{order}/payment', [TransactionController::class, 'createPayment']);

    // Bargains
    Route::get('/bargains', [BargainController::class, 'index']);
    Route::post('/bargains', [BargainController::class, 'store']);
});

// Seller Routes - Require authentication and seller role
Route::middleware(['auth:sanctum', 'isSeller'])->group(function () {
    // Product management
    Route::post('/seller/products', [SellerController::class, 'storeProduct']);
    Route::get('/seller/products', [SellerController::class, 'getMyProducts']);
    Route::put('/seller/products/{product}', [SellerController::class, 'updateProduct']);
    Route::delete('/seller/products/{product}', [SellerController::class, 'deleteProduct']);

    // Order management
    Route::get('/seller/orders', [SellerController::class, 'viewOrdersReceived']);
    Route::patch('/seller/orders/{order}/status', [SellerController::class, 'updateOrderStatus']);

    // Invoice management
    Route::post('/orders/{order}/invoice', [InvoiceController::class, 'generateInvoice']);

    // Bargain responses
    Route::post('/bargains/{bargain}/respond', [BargainController::class, 'respond']);
});

// Admin Routes - Require authentication and admin role
Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
    // User management
    Route::get('/admin/users', [AdminController::class, 'getAllUsers']);
    Route::patch('/admin/users/{user}/approve', [AdminController::class, 'approveSeller']);
    Route::patch('/admin/users/{user}/block', [AdminController::class, 'blockUser']);

    // Category management
    Route::get('/admin/categories', [AdminController::class, 'getCategories']);
    Route::post('/admin/categories', [AdminController::class, 'createCategory']);
    Route::put('/admin/categories/{category}', [AdminController::class, 'updateCategory']);
    Route::delete('/admin/categories/{category}', [AdminController::class, 'deleteCategory']);

    // Transaction monitoring
    Route::patch('/admin/transactions/{transaction}/refund', [TransactionController::class, 'processRefund']);
});

// General authenticated routes (any role)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'getUserTransactions']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'getUserInvoices']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus']);
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPDF']);

    // Communications/Messaging
    Route::post('/messages', [CommunicationController::class, 'sendMessage']);
    Route::get('/messages', [CommunicationController::class, 'getMessages']);
    Route::get('/conversations', [CommunicationController::class, 'getConversation']);
    Route::patch('/messages/{communication}/read', [CommunicationController::class, 'markAsRead']);
    Route::get('/messages/unread/count', [CommunicationController::class, 'getUnreadCount']);
});
