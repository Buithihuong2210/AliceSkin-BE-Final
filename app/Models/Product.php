<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id';

    protected $fillable = [
        'name',
        'description',
        'price',
        'discount',
        'discounted_price',
        'quantity',
        'brand_id',
        'image',
        'status',
        'short_description',
        'volume',
        'nature',
        'rating',
        'product_type',
        'main_ingredient',
        'target_skin_type',
    ];

    const STATUS_AVAILABLE = 'available';
    const STATUS_OUT_OF_STOCK = 'out of stock';

    /**
     * Get the brand associated with the product.
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'brand_id');
    }

    /**
     * Calculate the discounted price.
     *
     * @return float
     */
    public function calculateDiscountedPrice()
    {
        if ($this->discount > 0 && $this->discount < 100) {
            return $this->price * (1 - $this->discount / 100);
        }
        return $this->price;
    }

    /**
     * Boot method to calculate the discounted price before saving.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            $product->discounted_price = $product->calculateDiscountedPrice();
        });

        static::updating(function ($product) {
            $product->discounted_price = $product->calculateDiscountedPrice();
        });
    }

    /**
     * Get the average rating for the product based on reviews.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'product_id');
    }

    public function calculateAverageRating()
    {
        return $this->reviews()->avg('rate');
    }

    protected $casts = [
        'images' => 'array',
    ];
}
