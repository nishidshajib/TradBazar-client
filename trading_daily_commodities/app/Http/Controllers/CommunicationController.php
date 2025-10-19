<?php

namespace App\Http\Controllers;

use App\Models\Communication;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    /**
     * Send message
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'order_id' => 'nullable|exists:orders,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
        ]);

        $sender = auth()->user();

        // Validate that user can message the receiver
        if ($request->has('order_id')) {
            $order = Order::find($request->order_id);
            if (!$order || ($order->buyer_id !== $sender->id && $order->seller_id !== $sender->id)) {
                return response()->json(['error' => 'You can only message about your own orders'], 403);
            }
        }

        $communication = Communication::create([
            'sender_id' => $sender->id,
            'receiver_id' => $request->receiver_id,
            'order_id' => $request->order_id,
            'subject' => $request->subject,
            'message' => $request->message,
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Message sent successfully',
            'communication' => $communication->load(['sender', 'receiver', 'order']),
        ], 201);
    }

    /**
     * Get user's messages (inbox)
     */
    public function getMessages(Request $request)
    {
        $user = auth()->user();

        $query = Communication::forUser($user->id)
                              ->with(['sender', 'receiver', 'order'])
                              ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->has('type')) {
            if ($request->type === 'sent') {
                $query->where('sender_id', $user->id);
            } elseif ($request->type === 'received') {
                $query->where('receiver_id', $user->id);
            } elseif ($request->type === 'unread') {
                $query->where('receiver_id', $user->id)->unread();
            }
        }

        $messages = $query->paginate(10);

        return response()->json($messages);
    }

    /**
     * Get conversation between two users for a specific order
     */
    public function getConversation(Request $request)
    {
        $request->validate([
            'other_user_id' => 'required|exists:users,id',
            'order_id' => 'nullable|exists:orders,id',
        ]);

        $user = auth()->user();
        $otherUserId = $request->other_user_id;

        $query = Communication::where(function($q) use ($user, $otherUserId) {
            $q->where(function($subQ) use ($user, $otherUserId) {
                $subQ->where('sender_id', $user->id)->where('receiver_id', $otherUserId);
            })->orWhere(function($subQ) use ($user, $otherUserId) {
                $subQ->where('sender_id', $otherUserId)->where('receiver_id', $user->id);
            });
        });

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        $conversation = $query->with(['sender', 'receiver', 'order'])
                            ->orderBy('created_at', 'asc')
                            ->get();

        // Mark messages as read
        Communication::where('receiver_id', $user->id)
                    ->where('sender_id', $otherUserId)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Communication $communication)
    {
        // Only receiver can mark as read
        if ($communication->receiver_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $communication->markAsRead();

        return response()->json([
            'message' => 'Message marked as read',
        ]);
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount()
    {
        $count = Communication::where('receiver_id', auth()->id())
                              ->unread()
                              ->count();

        return response()->json([
            'unread_count' => $count,
        ]);
    }
}
