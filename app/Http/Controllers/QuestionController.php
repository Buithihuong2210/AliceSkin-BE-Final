<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    // Create a new question for a specific survey
    public function store(Request $request, $survey_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'question_text' => 'required|string|max:255',
                'question_type' => 'required|string|in:multiple_choice',
                'options' => 'required_if:type,multiple_choice|array|min:2',
                'options.*' => 'required_if:type,multiple_choice|string',
                'category' => 'required|string|in:Interest,Goal,Factor',
                'code' => 'required|string|max:50|unique:questions,code',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $survey = Survey::findOrFail($survey_id);

            $question = $survey->questions()->create([
                'question_text' => $request->input('question_text'),
                'question_type' => $request->input('question_type'),
                'options' => $request->input('question_type') === 'multiple_choice' ? $request->input('options') : null,
                'category' => $request->input('category'),
                'code' => $request->input('code'),

            ]);

            $questionData = [
                "question_id" => $question->question_id,
                "survey_id" => $survey->survey_id,
                "question_text" => $question->question_text,
                "category" => $question->category,
                "question_type" => $question->question_type,
                "options" => $question->options,
                "code" => $question->code,
                "created_at" => $question->created_at->toIso8601String(),
                "updated_at" => $question->updated_at->toIso8601String(),
            ];

            return response()->json([$questionData], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // List all questions for a specific survey
    public function index($survey_id)
    {
        try {
            $survey = Survey::findOrFail($survey_id);

            $questions = $survey->questions->map(function ($question) {
                return [
                    "question_id" => $question->question_id,
                    "survey_id" => $question->survey_id,
                    "question_text" => $question->question_text,
                    "category" => $question->category,
                    "options" => $question->options,
                    "code" => $question->code,
                    "question_type" => $question->question_type,
                    "created_at" => $question->created_at->toIso8601String(),
                    "updated_at" => $question->updated_at->toIso8601String(),
                ];
            });

            return response()->json($questions, 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Show a specific question
    public function show($survey_id, $question_id)
    {
        try {
            $survey = Survey::findOrFail($survey_id);
            $question = $survey->questions()->findOrFail($question_id);

            $questionData = [
                "question_id" => $question->question_id,
                "survey_id" => $question->survey_id,
                "question_text" => $question->question_text,
                "category" => $question->category,
                "options" => $question->options,
                "code" => $question->code,
                "question_type" => $question->question_type,
                "created_at" => $question->created_at->toIso8601String(),
                "updated_at" => $question->updated_at->toIso8601String(),
            ];

            return response()->json($questionData, 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update a specific question
    public function update(Request $request, $survey_id, $question_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'question_text' => 'sometimes|required|string|max:255',
                'question_type' => 'sometimes|required|string|in:multiple_choice,text',
                'options' => 'sometimes|required_if:type,multiple_choice|array|min:2',
                'options.*' => 'required_if:type,multiple_choice|string',
                'category' => 'sometimes|required|string|in:Interest,Goal,Factor',
                'code' => 'sometimes|required|string|max:255|unique:questions,code,' . $question_id . ',question_id',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $survey = Survey::findOrFail($survey_id);
            $question = $survey->questions()->findOrFail($question_id);

            $question->update([
                'question_text' => $request->input('question_text', $question->question_text),
                'question_type' => $request->input('question_type', $question->question_type),
                'options' => $request->input('question_type') === 'multiple_choice' ? $request->input('options', $question->options) : null,
                'category' => $request->input('category', $question->category),
                'code' => $request->input('code', $question->code),
            ]);

            $questionData = [
                "question_id" => $question->question_id,
                "survey_id" => $question->survey_id,
                "question_text" => $question->question_text,
                "category" => $question->category,
                "options" => $question->options,
                "code" => $question->code,
                "question_type" => $question->question_type,
                "created_at" => $question->created_at->toIso8601String(),
                "updated_at" => $question->updated_at->toIso8601String(),
            ];

            return response()->json([
                'message' => 'Question updated successfully',
                'data' => $questionData,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete a specific question
    public function destroy($survey_id, $question_id)
    {
        try {
            $survey = Survey::findOrFail($survey_id);
            $question = $survey->questions()->findOrFail($question_id);

            $question->delete();

            return response()->json([
                'message' => 'Question deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete question',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}