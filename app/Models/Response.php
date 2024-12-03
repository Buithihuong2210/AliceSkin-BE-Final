<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Response extends Model
{
    use HasFactory;

    protected $table = 'responses';
    protected $primaryKey = 'response_id';

    protected $fillable = ['survey_id', 'question_id', 'user_id', 'answer_text'];

    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id', 'question_id');
    }
    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id');
    }
}
