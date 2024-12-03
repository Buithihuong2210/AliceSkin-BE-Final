<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $primaryKey = 'brand_id';
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'image',
        'total_products'
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id', 'brand_id');
    }
    public function getTotalProductsAttribute()
    {
        return $this->products()->count();
    }
}
