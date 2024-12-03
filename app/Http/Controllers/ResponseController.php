<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Response;
use App\Models\Survey;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class ResponseController extends Controller
{
    // Store a new response for a survey
    public function store(Request $request, $survey_id)
    {
        $validated = $request->validate([
            'responses' => 'required|array',
        ]);

        try {
            $survey = Survey::findOrFail($survey_id);
            $allQuestionsCodes = Question::where('survey_id', $survey_id)->pluck('code')->toArray();
            $answeredQuestionCodes = array_column($validated['responses'], 'code');
            if (array_diff($allQuestionsCodes, $answeredQuestionCodes)) {
                return response()->json(['error' => 'You must answer all questions in the survey.'], 400);
            }
            $answers = [];
            foreach ($validated['responses'] as $response) {
                $question = Question::where('code', $response['code'])
                    ->where('survey_id', $survey_id)
                    ->firstOrFail();
                $answers[$question->code] = $response['answer'];
                Response::create([
                    'survey_id' => $survey_id,
                    'question_id' => $question->question_id,
                    'user_id' => auth()->id(),
                    'answer_text' => $response['answer'],
                ]);
            }
            $recommendedProducts = Product::query()
                ->when(isset($answers['Q1']), function ($query) use ($answers) {
                    return $query->where('target_skin_type', $answers['Q1']);
                })
                ->when(isset($answers['Q2']), function ($query) use ($answers) {
                    return $query->where('product_type', $answers['Q2']);
                })
                ->when(isset($answers['Q6']), function ($query) use ($answers) {
                    return $query->where('main_ingredient', $answers['Q6']);
                })
                ->get();

            return response()->json($recommendedProducts, 201);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Survey or question not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to submit response.', 'details' => $e->getMessage()], 500);
        }
    }

    // Show a specific response by its ID
    public function show($response_id)
    {
        try {
            $response = Response::findOrFail($response_id);

            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Response not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve response.'], 500);
        }
    }

    // List all responses
    public function index()
    {
        try {
            $responses = Response::with(['question', 'survey'])->get();

            if ($responses->isEmpty()) {
                return response()->json(['error' => 'No responses found.'], 404);
            }

            $formattedResponses = $responses->map(function ($response) {
                return [
                    'response_id' => $response->response_id,
                    'question_id' => $response->question_id,
                    'question_text' => $response->question->question_text,
                    'category' => $response->question->category,
                    'user_id' => $response->user_id,
                    'survey_id' => $response->survey_id,
                    'title' => $response->survey->title,
                    'answer_text' => $response->answer_text,
                    'created_at' => $response->created_at,
                    'updated_at' => $response->updated_at,
                ];
            });

            return response()->json($formattedResponses, 200);

        } catch (\Exception $e) {
            \Log::error('Failed to retrieve responses: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to retrieve responses.'], 500);
        }
    }

    public function showResponse()
    {
        $userId = auth()->id();

        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. Please log in.',
            ], 401);
        }

        $responses = Response::with('question:question_id,question_text,category,code')
        ->where('user_id', $userId)
            ->get(['response_id', 'user_id', 'question_id', 'answer_text']);

        if ($responses->isEmpty()) {
            return response()->json([
                'message' => 'No responses found for this user.',
            ], 404);
        }

        return response()->json($responses);
    }

    public function recommendItem()
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. Please log in.',
            ], 401);
        }
        $responses = Response::with('question')
            ->where('user_id', $userId)
            ->get();

        if ($responses->isEmpty()) {
            return response()->json([
                'message' => 'No responses found for this user.',
            ], 404);
        }
        $answers = [];
        foreach ($responses as $response) {
            $answers[$response->question->code] = $response->answer_text;
        }
        $recommendedProducts = Product::query()
            ->when(isset($answers['Q1']), function ($query) use ($answers) {
                return $query->where('target_skin_type', $answers['Q1']);
            })
            ->when(isset($answers['Q2']), function ($query) use ($answers) {
                return $query->where('product_type', $answers['Q2']);
            })
            ->when(isset($answers['Q6']), function ($query) use ($answers) {
                return $query->where('main_ingredient', $answers['Q6']);
            })
            ->get();
        return response()->json($recommendedProducts, 200);
    }

    // Update a specific response by its ID
    public function update(Request $request, $survey_id)
    {
        $validated = $request->validate([
            'responses' => 'required|array',
        ]);

        try {
            $survey = Survey::findOrFail($survey_id);

            foreach ($validated['responses'] as $response) {
                $question = Question::where('code', $response['code'])
                    ->where('survey_id', $survey_id)
                    ->firstOrFail();

                Response::updateOrCreate(
                    [
                        'survey_id' => $survey_id,
                        'question_id' => $question->question_id,
                        'user_id' => auth()->id(),
                    ],
                    [
                        'answer_text' => $response['answer'],
                    ]
                );
            }

            $answers = array_column($validated['responses'], 'answer', 'code');

            $recommendedProducts = Product::query()
                ->when(isset($answers['Q1']), function ($query) use ($answers) {
                    return $query->where('target_skin_type', $answers['Q1']);
                })
                ->when(isset($answers['Q2']), function ($query) use ($answers) {
                    return $query->where('product_type', $answers['Q2']);
                })
                ->when(isset($answers['Q6']), function ($query) use ($answers) {
                    return $query->where('main_ingredient', $answers['Q6']);
                })
                ->get();

            return response()->json($recommendedProducts, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Survey or question not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update responses.', 'details' => $e->getMessage()], 500);
        }
    }

    // Delete a specific response by its ID
    public function destroy($response_id)
    {
        try {
            $response = Response::findOrFail($response_id);

            $response->delete();

            return response()->json(['message' => 'Response deleted successfully.'], 204);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Response not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete response.'], 500);
        }
    }
}