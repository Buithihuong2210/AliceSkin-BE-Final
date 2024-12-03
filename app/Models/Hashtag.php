<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hashtag extends Model
{
    use HasFactory;
    protected $primaryKey = 'id';

    protected $fillable = ['name', 'usage_count'];

    public function blogs()
    {
        return $this->belongsToMany(Blog::class, 'hashtag_blog');
    }
}
