<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'status',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function bargains()
    {
        return $this->hasMany(Bargain::class);
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Communication::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Communication::class, 'receiver_id');
    }

    // Helper methods for roles
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isSeller()
    {
        return $this->role === 'seller';
    }

    public function isBuyer()
    {
        return $this->role === 'buyer';
    }

    // Additional relationships for transactions and refunds
    public function transactions()
    {
        return $this->hasManyThrough(Transaction::class, Order::class);
    }

    public function buyerInvoices()
    {
        return $this->hasMany(Invoice::class, 'buyer_id');
    }

    public function sellerInvoices()
    {
        return $this->hasMany(Invoice::class, 'seller_id');
    }

    public function refunds()
    {
        return $this->hasManyThrough(Refund::class, Order::class);
    }

    // Helper methods for status
    public function isApproved()
    {
        return $this->status === 'Approved';
    }

    public function isPending()
    {
        return $this->status === 'Pending';
    }

    public function isBlocked()
    {
        return $this->status === 'Blocked';
    }
}
