<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * Create payment transaction
     */
    public function createPayment(Request $request, Order $order)
    {
        $request->validate([
            'payment_method' => 'required|in:credit_card,debit_card,upi,net_banking,cash_on_delivery',
        ]);

        // Check if user owns this order
        if ($order->buyer_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if payment already exists
        $existingTransaction = Transaction::where('order_id', $order->id)->first();
        if ($existingTransaction) {
            return response()->json(['error' => 'Payment already processed for this order'], 400);
        }

        $transaction = Transaction::create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'payment_method' => $request->payment_method,
            'transaction_id' => 'TXN' . time() . Str::random(6),
            'transaction_date' => now(),
            'status' => $request->payment_method === 'cash_on_delivery' ? 'Pending' : 'Completed',
        ]);

        // Update order status
        if ($transaction->status === 'Completed') {
            $order->update(['status' => 'confirmed']);
        }

        return response()->json([
            'message' => 'Payment processed successfully',
            'transaction' => $transaction,
        ], 201);
    }

    /**
     * Get transaction details
     */
    public function show(Transaction $transaction)
    {
        // Check if user is authorized to view this transaction
        $order = $transaction->order;
        if ($order->buyer_id !== auth()->id() && $order->seller_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'transaction' => $transaction->load('order'),
        ]);
    }

    /**
     * Get user's transaction history
     */
    public function getUserTransactions()
    {
        $user = auth()->user();
        
        if ($user->isBuyer()) {
            $transactions = Transaction::whereHas('order', function($query) use ($user) {
                $query->where('buyer_id', $user->id);
            })->with('order')->orderBy('created_at', 'desc')->paginate(10);
        } elseif ($user->isSeller()) {
            $transactions = Transaction::whereHas('order', function($query) use ($user) {
                $query->where('seller_id', $user->id);
            })->with('order')->orderBy('created_at', 'desc')->paginate(10);
        } else {
            // Admin can see all transactions
            $transactions = Transaction::with('order')->orderBy('created_at', 'desc')->paginate(10);
        }

        return response()->json($transactions);
    }

    /**
     * Process refund (Admin only)
     */
    public function processRefund(Request $request, Transaction $transaction)
    {
        $request->validate([
            'refund_amount' => 'required|numeric|min:0|max:' . $transaction->amount,
            'reason' => 'required|string|max:500',
        ]);

        if ($transaction->status === 'Refunded') {
            return response()->json(['error' => 'Transaction already refunded'], 400);
        }

        $transaction->update([
            'status' => 'Refunded',
        ]);

        return response()->json([
            'message' => 'Refund processed successfully',
            'transaction' => $transaction,
        ]);
    }
}
