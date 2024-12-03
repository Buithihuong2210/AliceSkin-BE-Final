<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $primaryKey = 'comment_id';

    protected $fillable = ['blog_id', 'user_id', 'content', 'parent_id'];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function getRepliesWithUsers()
    {
        return $this->replies()
            ->with('user:id,name,image,dob,role,phone,gender,email', 'replies.user:id,name,image,dob,role,phone,gender,email')
            ->get()
            ->each(function($reply) {
                $reply->setRelation('replies', $reply->getRepliesWithUsers());
            });
    }

}

