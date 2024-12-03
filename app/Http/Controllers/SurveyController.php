<?php
namespace App\Http\Controllers;

use App\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SurveyController extends Controller
{
    public function index()
    {
        try {
            $surveys = Survey::all();
            return response()->json($surveys, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve surveys.'], 500);
        }
    }

    public function show($survey_id)
    {
        try {
            $survey = Survey::findOrFail($survey_id);
            return response()->json($survey, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Survey not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve survey.'], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $survey = Survey::create($validated);
            return response()->json($survey, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create survey.'], 500);
        }
    }

    public function update(Request $request, $survey_id)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $survey = Survey::findOrFail($survey_id);
            $survey->update($validated);
            return response()->json($survey, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Survey not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update survey.'], 500);
        }
    }

    public function destroy($survey_id)
    {
        try {
            $survey = Survey::findOrFail($survey_id);
            $survey->delete();
            return response()->json(['message' => 'Survey deleted successfully.'], 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Survey not found.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete survey.'], 500);
        }
    }
}
