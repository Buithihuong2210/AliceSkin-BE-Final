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
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'question_text' => 'required|string|max:255',
                'question_type' => 'required|string|in:multiple_choice',
                'options' => 'required_if:type,multiple_choice|array|min:2',
                'options.*' => 'required_if:type,multiple_choice|string',
                'category' => 'required|string|in:Interest,Goal,Factor',
                'code' => 'required|string|max:50|unique:questions,code',
            ]);

            // If validation fails, return errors
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            // Find the survey
            $survey = Survey::findOrFail($survey_id);

            // Create the question
            $question = $survey->questions()->create([
                'question_text' => $request->input('question_text'),
                'question_type' => $request->input('question_type'),
                'options' => $request->input('question_type') === 'multiple_choice' ? $request->input('options') : null,
                'category' => $request->input('category'),
                'code' => $request->input('code'), // Thêm code

            ]);

            // Prepare the response structure with only the necessary data fields
            $questionData = [
                "question_id" => $question->question_id,
                "survey_id" => $survey->survey_id,
                "question_text" => $question->question_text,
                "category" => $question->category,
                "question_type" => $question->question_type,
                "options" => $question->options,
                "code" => $question->code, // Thêm code vào response
                "created_at" => $question->created_at->toIso8601String(),
                "updated_at" => $question->updated_at->toIso8601String(),
            ];

            // Return response as a plain array without additional wrapper
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
            // Find the survey
            $survey = Survey::findOrFail($survey_id);

            // Get all questions for the survey
            $questions = $survey->questions->map(function ($question) {
                return [
                    "question_id" => $question->question_id,
                    "survey_id" => $question->survey_id,
                    "question_text" => $question->question_text,
                    "category" => $question->category,
                    "options" => $question->options,
                    "code" => $question->code, // Thêm code vào response
                    "question_type" => $question->question_type,
                    "created_at" => $question->created_at->toIso8601String(),
                    "updated_at" => $question->updated_at->toIso8601String(),
                ];
            });

            // Return the questions as a plain array
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
            // Find the survey and question
            $survey = Survey::findOrFail($survey_id);
            $question = $survey->questions()->findOrFail($question_id);

            // Format the question data to match the desired structure
            $questionData = [
                "question_id" => $question->question_id,
                "survey_id" => $question->survey_id,
                "question_text" => $question->question_text,
                "category" => $question->category,
                "options" => $question->options,
                "code" => $question->code, // Thêm code vào response
                "question_type" => $question->question_type,
                "created_at" => $question->created_at->toIso8601String(),
                "updated_at" => $question->updated_at->toIso8601String(),
            ];

            // Return the formatted question data directly
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
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'question_text' => 'sometimes|required|string|max:255',
                'question_type' => 'sometimes|required|string|in:multiple_choice,text',
                'options' => 'sometimes|required_if:type,multiple_choice|array|min:2',
                'options.*' => 'required_if:type,multiple_choice|string',
                'category' => 'sometimes|required|string|in:Interest,Goal,Factor',
                'code' => 'sometimes|required|string|max:255|unique:questions,code,' . $question_id . ',question_id', // Đảm bảo sử dụng question_id
            ]);

            // If validation fails, return errors
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            // Find the survey and question
            $survey = Survey::findOrFail($survey_id);
            $question = $survey->questions()->findOrFail($question_id);

            // Update the question
            $question->update([
                'question_text' => $request->input('question_text', $question->question_text),
                'question_type' => $request->input('question_type', $question->question_type),
                'options' => $request->input('question_type') === 'multiple_choice' ? $request->input('options', $question->options) : null,
                'category' => $request->input('category', $question->category),
                'code' => $request->input('code', $question->code),
            ]);

            // Prepare the response with the updated question details
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

            // Return the updated question data
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