<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'total', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
                   ->withPivot(['quantity', 'price'])
                   ->withTimestamps();
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function communications()
    {
        return $this->hasMany(Communication::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function refund()
    {
        return $this->hasOne(Refund::class);
    }

    // Helper method to update status with history tracking
    public function updateStatus($newStatus, $comment = null, $updatedBy = null)
    {
        $oldStatus = $this->status;
        
        if ($oldStatus !== $newStatus) {
            $this->update(['status' => $newStatus]);
            
            $this->statusHistory()->create([
                'status' => $newStatus,
                'comment' => $comment,
                'updated_by' => $updatedBy ?: auth()->id(),
            ]);
        }
        
        return $this;
    }
}
