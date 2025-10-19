<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Generate invoice for an order
     */
    public function generateInvoice(Order $order)
    {
        // Check if user is authorized (seller or buyer of the order)
        if ($order->buyer_id !== auth()->id() && $order->seller_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if invoice already exists
        $existingInvoice = Invoice::where('order_id', $order->id)->first();
        if ($existingInvoice) {
            return response()->json([
                'message' => 'Invoice already exists',
                'invoice' => $existingInvoice,
            ]);
        }

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'order_id' => $order->id,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $order->seller_id,
            'total_amount' => $order->total_amount,
            'tax_amount' => $order->total_amount * 0.18, // 18% GST
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'sent',
        ]);

        return response()->json([
            'message' => 'Invoice generated successfully',
            'invoice' => $invoice->load(['order', 'buyer', 'seller']),
        ], 201);
    }

    /**
     * Get invoice details
     */
    public function show(Invoice $invoice)
    {
        // Check authorization
        if ($invoice->buyer_id !== auth()->id() && $invoice->seller_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'invoice' => $invoice->load(['order.orderItems.product', 'buyer', 'seller']),
        ]);
    }

    /**
     * Get user's invoices
     */
    public function getUserInvoices()
    {
        $user = auth()->user();
        
        if ($user->isBuyer()) {
            $invoices = Invoice::where('buyer_id', $user->id)
                              ->with(['order', 'seller'])
                              ->orderBy('created_at', 'desc')
                              ->paginate(10);
        } elseif ($user->isSeller()) {
            $invoices = Invoice::where('seller_id', $user->id)
                              ->with(['order', 'buyer'])
                              ->orderBy('created_at', 'desc')
                              ->paginate(10);
        } else {
            // Admin can see all invoices
            $invoices = Invoice::with(['order', 'buyer', 'seller'])
                              ->orderBy('created_at', 'desc')
                              ->paginate(10);
        }

        return response()->json($invoices);
    }

    /**
     * Update invoice status
     */
    public function updateStatus(Request $request, Invoice $invoice)
    {
        $request->validate([
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
        ]);

        // Only seller or admin can update invoice status
        if ($invoice->seller_id !== auth()->id() && !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $invoice->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Invoice status updated successfully',
            'invoice' => $invoice,
        ]);
    }

    /**
     * Download invoice as PDF (placeholder - would need PDF library)
     */
    public function downloadPDF(Invoice $invoice)
    {
        // Check authorization
        if ($invoice->buyer_id !== auth()->id() && $invoice->seller_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // This would integrate with a PDF library like DomPDF or TCPDF
        return response()->json([
            'message' => 'PDF generation feature requires PDF library integration',
            'invoice_data' => $invoice->load(['order.orderItems.product', 'buyer', 'seller']),
        ]);
    }
}
