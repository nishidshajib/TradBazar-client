<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bargain extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'user_id', 'offered_price', 'counter_price', 'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Check if the offered price meets minimum requirements
    public function isAcceptable()
    {
        return $this->offered_price >= $this->product->min_price;
    }

    // Auto-accept if price meets minimum
    public function checkAndAutoAccept()
    {
        if ($this->isAcceptable()) {
            $this->update(['status' => 'accepted']);
            return true;
        }
        return false;
    }

    public function getStatusMessageAttribute()
    {
        switch ($this->status) {
            case 'pending':
                return 'Waiting for seller response';
            case 'countered':
                return 'Seller made a counter offer';
            case 'accepted':
                return 'Bargain accepted! You can now purchase';
            case 'rejected':
                return 'Bargain rejected by seller';
            default:
                return 'Unknown status';
        }
    }
}
