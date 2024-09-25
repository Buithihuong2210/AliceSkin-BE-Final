<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'title', 'description'];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
