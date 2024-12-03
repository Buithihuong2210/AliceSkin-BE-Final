<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingCart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'subtotal', 'status'];

    public function items()
    {
        return $this->hasMany(CartItem::class, 'shopping_cart_id');
    }

}
