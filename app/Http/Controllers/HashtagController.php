<?php

namespace App\Http\Controllers;

use App\Models\Hashtag;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;


class HashtagController extends Controller
{
    public function index()
    {
        return Hashtag::all();
    }

    // Phương thức tìm kiếm hashtag và tự động thêm nếu không có
    public function search(Request $request)
    {
        // Validate query parameter
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        try {
            // Lấy chuỗi tìm kiếm từ yêu cầu
            $query = $request->input('query');

            // Loại bỏ dấu ngoặc nhọn và các ký tự không mong muốn
            $cleanedQuery = str_replace(['{', '}'], '', $query);

            // Tìm kiếm hashtag có chứa chuỗi đã loại bỏ dấu ngoặc
            $hashtags = Hashtag::where('name', 'like', '%' . $cleanedQuery . '%')
                ->orderBy('name')
                ->limit(10) // Giới hạn kết quả trả về nếu cần
                ->get();

            // Trả về danh sách các hashtag tìm được
            return response()->json($hashtags, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching for hashtags.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Phương thức lưu hashtag mới
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:hashtags,name',
        ]);

        try {
            $hashtag = Hashtag::create([
                'name' => $request->input('name'),
            ]);

            return response()->json([
                'hashtag_id' => $hashtag->id,
                'name' => $hashtag->name,
                "ususage_count" => $hashtag->usage_count ?? 0,
                'created_at' => $hashtag->created_at,
                'updated_at' => $hashtag->updated_at,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Could not create hashtag. Details: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $hashtag = Hashtag::findOrFail($id);
            return response()->json($hashtag);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Hashtag not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|unique:hashtags,name,' . $id . ',id',
            ]);

            $hashtag = Hashtag::findOrFail($id);

            $hashtag->update($request->all());

            return response()->json($hashtag);

        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Hashtag not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred', 'details' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $hashtag = Hashtag::findOrFail($id);

            $hashtag->delete();

            $response = [
                'message' => 'Hashtag deleted successfully',
                'hashtag_id' => $id
            ];

            return response()->json($response, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Hashtag not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred'], 500);
        }
    }

    public function getByID($id)
    {
        try {
            $blogs = DB::table('hashtag_blog')
                ->join('blogs', 'hashtag_blog.blog_id', '=', 'blogs.blog_id')
                ->where('hashtag_blog.hashtag_id', $id)
                ->select('blogs.*')
                ->get();

            if ($blogs->isEmpty()) {
                return response()->json([
                    'message' => 'No blogs found for this hashtag.',
                ], 404);
            }

            return response()->json($blogs, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving blogs.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}