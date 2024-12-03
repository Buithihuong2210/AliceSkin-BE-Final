<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;

    protected $table = 'blogs';
    protected $primaryKey = 'blog_id';

    protected $fillable = [
        'title',
        'user_id',
        'thumbnail',
        'content',
        'status',
        'like'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function hashtags()
    {
        return $this->belongsToMany(Hashtag::class, 'hashtag_blog','blog_id','hashtag_id');
    }

    public function likes()
    {
        return $this->hasMany(BlogLike::class, 'blog_id');
    }

}
