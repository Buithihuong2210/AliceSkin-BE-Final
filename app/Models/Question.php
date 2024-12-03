<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $table = 'questions';
    protected $primaryKey = 'question_id';
    public $incrementing = true;

    protected $fillable = [
        'survey_id',
        'question_text',
        'question_type',
        'options',
        'category',
        'code'
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id', 'survey_id');
    }

    public function responses()
    {
        return $this->hasMany(Response::class);
    }
}
