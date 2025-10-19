<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'name', 'description', 'price', 'min_price', 'quantity', 'image',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function bargains()
    {
        return $this->hasMany(Bargain::class);
    }

    // Bargaining helper methods
    public function isBargainingEnabled()
    {
        return !is_null($this->min_price) && $this->min_price < $this->price;
    }

    public function canAcceptPrice($offered_price)
    {
        return $offered_price >= $this->min_price;
    }

    public function getBargainRangeAttribute()
    {
        return [
            'min_price' => $this->min_price,
            'max_price' => $this->price,
            'bargaining_enabled' => $this->isBargainingEnabled()
        ];
    }

    public function getDisplayPriceAttribute()
    {
        // Show maximum price to buyers initially
        return $this->price;
    }
}
