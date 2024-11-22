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
            // Find the response by its ID
            $response = Response::findOrFail($response_id);

            // Return the response data with a 200 status code
            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            // Return a 404 error if the response is not found
            return response()->json(['error' => 'Response not found.'], 404);
        } catch (\Exception $e) {
            // Return a 500 error for any other failure
            return response()->json(['error' => 'Failed to retrieve response.'], 500);
        }
    }

    // List all responses
    public function index()
    {
        try {
            // Retrieve all response records with related questions and surveys
            $responses = Response::with(['question', 'survey'])->get();

            // Check if responses are empty
            if ($responses->isEmpty()) {
                return response()->json(['error' => 'No responses found.'], 404);
            }

            // Format the response to include question_text and survey title
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

            // Return the list of formatted responses with a 200 status code
            return response()->json($formattedResponses, 200);

        } catch (\Exception $e) {
            // Log the exception message
            \Log::error('Failed to retrieve responses: ' . $e->getMessage());

            // Return a 500 error if fetching responses fails
            return response()->json(['error' => 'Failed to retrieve responses.'], 500);
        }
    }

    public function showResponse()
    {
        // Lấy user_id của người dùng đã đăng nhập
        $userId = auth()->id();

        // Kiểm tra nếu người dùng đã đăng nhập
        if (!$userId) {
            return response()->json([
                'message' => 'Unauthorized. Please log in.',
            ], 401);
        }

        // Lấy tất cả các câu trả lời của người dùng này cùng với thông tin câu hỏi
        $responses = Response::with('question:question_id,question_text,category,code') // Thay 'id' bằng 'question_id'
        ->where('user_id', $userId)
            ->get(['response_id', 'user_id', 'question_id', 'answer_text']); // Thay 'id' bằng 'response_id' nếu cần

        // Kiểm tra nếu không có câu trả lời nào
        if ($responses->isEmpty()) {
            return response()->json([
                'message' => 'No responses found for this user.',
            ], 404);
        }

        // Trả về kết quả dưới dạng JSON
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
        // Validate rằng 'responses' đã được cung cấp và là một mảng
        $validated = $request->validate([
            'responses' => 'required|array',
        ]);

        try {
            // Kiểm tra xem khảo sát có tồn tại không
            $survey = Survey::findOrFail($survey_id);

            // Lặp qua các phản hồi và cập nhật
            foreach ($validated['responses'] as $response) {
                // Tìm câu hỏi dựa trên code thay vì question_id
                $question = Question::where('code', $response['code'])
                    ->where('survey_id', $survey_id)
                    ->firstOrFail();

                // Cập nhật hoặc tạo mới bản ghi phản hồi
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

            // Lọc sản phẩm dựa trên các câu trả lời
            $answers = array_column($validated['responses'], 'answer', 'code');

            $recommendedProducts = Product::query()
                ->when(isset($answers['Q1']), function ($query) use ($answers) {
                    return $query->where('target_skin_type', $answers['Q1']); // Lọc theo loại da
                })
                ->when(isset($answers['Q2']), function ($query) use ($answers) {
                    return $query->where('product_type', $answers['Q2']); // Lọc theo loại sản phẩm
                })
                ->when(isset($answers['Q6']), function ($query) use ($answers) {
                    return $query->where('main_ingredient', $answers['Q6']); // Lọc theo thành phần chứa
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
            // Find the response by its ID
            $response = Response::findOrFail($response_id);

            // Delete the response from the database
            $response->delete();

            // Return a success message with a 204 No Content status
            return response()->json(['message' => 'Response deleted successfully.'], 204);

        } catch (ModelNotFoundException $e) {
            // Return a 404 error if the response is not found
            return response()->json(['error' => 'Response not found.'], 404);
        } catch (\Exception $e) {
            // Return a 500 error for any other failure
            return response()->json(['error' => 'Failed to delete response.'], 500);
        }
    }
}