<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogLike;
use App\Models\Hashtag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BlogController extends Controller
{
    // Retrieve all blog entries
    public function showAll()
    {
        try {
            // Retrieve blogs with related hashtags and user information
            $blogs = Blog::with(['hashtags', 'user'])->get();

            // Count the number of 'published' and 'draft' blogs
            $publishedCount = Blog::where('status', 'published')->count();
            $draftCount = Blog::where('status', 'draft')->count();

            // Create the status counts object
            $statusCounts = [
                'draft' => $draftCount,
                'published' => $publishedCount,
            ];

            // Map the blogs to include hashtags and user information
            $blogsWithHashtagsAndUser = $blogs->map(function ($blog) {
                return [
                    'blog_id' => $blog->blog_id,
                    'title' => $blog->title,
                    'content' => $blog->content,
                    'thumbnail' => $blog->thumbnail,
                    'like' => $blog->like,
                    'status' => $blog->status,
                    'created_at' => $blog->created_at,
                    'updated_at' => $blog->updated_at,
                    'hashtags' => $blog->hashtags->pluck('name')->toArray(), // Get hashtags as an array of names
                    'user' => [
                        'id' => $blog->user->id,
                        'name' => $blog->user->name,
                        'email' => $blog->user->email,
                        'dob' => $blog->user->dob,
                        'phone' => $blog->user->phone,
                        'gender' => $blog->user->gender,
                        'image' => $blog->user->image,
                    ]
                ];
            });

            // Return the blogs with user and hashtags, along with status counts
            return response()->json([
                'blogs' => $blogsWithHashtagsAndUser,
                'status_counts' => $statusCounts, // Return status counts
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving blogs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    // Create a new blog
    public function store(Request $request)
    {
        try {
            $isAdmin = auth()->user()->admin;

            // Validation rules
            $rules = [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'thumbnail' => 'nullable|url',
                'hashtags' => 'nullable|array',
                'hashtags.*' => 'string|max:255',
            ];

            if ($isAdmin) {
                $rules['status'] = 'required|in:draft,published';
            }

            // Validate the request
            $validatedData = Validator::make($request->all(), $rules)->validate();
            $hashtags = $validatedData['hashtags'] ?? [];

            // Create the blog
            $blog = Blog::create([
                'title' => $validatedData['title'],
                'user_id' => auth()->id(),
                'content' => $validatedData['content'],
                'status' => $isAdmin ? $validatedData['status'] : 'draft',
                'thumbnail' => $validatedData['thumbnail'] ?? '',
                'like' => 0 // Initialize likes to 0
              
            ]);

            // Handle hashtags
            $hashtagIds = [];
            foreach ($hashtags as $hashtagName) {
                $hashtag = Hashtag::firstOrCreate(['name' => $hashtagName]);
                $hashtag->increment('usage_count');
                $hashtagIds[] = $hashtag->id;
            }

            // Attach hashtags to the blog
            $blog->hashtags()->attach($hashtagIds);

            // Return the blog along with only the hashtag names
            // Reload blog with hashtags and user relationship to include in the response
            $blog->load(['hashtags', 'user']);

            // Return the blog with hashtags and user information directly, without the outer "blog" key
            return response()->json([
                'blog_id' => $blog->blog_id,
                'title' => $blog->title,
                'content' => $blog->content,
                'thumbnail' => $blog->thumbnail,
                'like' => $blog->like, // This will return 0 if there are no likes
                'status' => $blog->status,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'hashtags' => $blog->hashtags->pluck('name'), // Include hashtag names
                'user' => [
                    'id' => $blog->user->id,
                    'name' => $blog->user->name,
                    'email' => $blog->user->email,
                    'dob' => $blog->user->dob,
                    'phone' => $blog->user->phone,
                    'gender' => $blog->user->gender,
                    'image' => $blog->user->image,
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the blog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Show a specific blog by ID
    public function show($blog_id)
    {
        try {
            // Lấy blog với thông tin người dùng và hashtag
            $blog = Blog::with('user', 'hashtags')->findOrFail($blog_id);

            // Lấy người dùng hiện tại
            $user = Auth::user();

            // Kiểm tra nếu người dùng hiện tại đã like blog này hay chưa
            $liked_by_user = false;
            if ($user) {
                // Kiểm tra xem người dùng hiện tại đã like blog này chưa
                $liked_by_user = BlogLike::where('blog_id', $blog_id)->where('user_id', $user->id)->exists();
            }

            // Lấy danh sách hashtags liên kết với blog
            $hashtags = $blog->hashtags->pluck('name');

            // Trả về thông tin blog, thêm thuộc tính liked_by_user
            return response()->json([
                'blog_id' => $blog->blog_id,
                'title' => $blog->title,
                'content' => $blog->content,
                'thumbnail' => $blog->thumbnail,
                'like' => $blog->like,
                'status' => $blog->status,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'hashtags' => $hashtags,
                'liked_by_user' => $liked_by_user, // Thêm thuộc tính liked_by_user
                'user' => [
                    'id' => $blog->user->id,
                    'name' => $blog->user->name,
                    'email' => $blog->user->email,
                    'dob' => $blog->user->dob,
                    'phone' => $blog->user->phone,
                    'gender' => $blog->user->gender,
                    'image' => $blog->user->image,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Blog not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }
    public function showUserBlogs(Request $request)
    {
        try {
            // Lấy người dùng hiện tại từ token
            $user = $request->user();

            // Nếu không có người dùng, trả về lỗi
            if (!$user) {
                return response()->json(['message' => 'User not authenticated or invalid token.'], 401);
            }

            // Tìm tất cả blog của người dùng hiện tại
            $blogs = Blog::where('user_id', $user->id)->with('user')->get();

            // Kiểm tra nếu không có blog nào cho người dùng
            if ($blogs->isEmpty()) {
                return response()->json(['message' => 'No blogs found for this user.'], 404);
            }

            // Trả về danh sách blog của người dùng
            return response()->json($blogs->map(function ($blog) {
                return [
                    'blog_id' => $blog->blog_id,
                    'title' => $blog->title,
                    'content' => $blog->content,
                    'status' => $blog->status,
                    'thumbnail' => $blog->thumbnail,
                    'like' => $blog->like,
                    'created_at' => $blog->created_at,
                    'updated_at' => $blog->updated_at,
                    'user' => [
                        'id' => $blog->user->id,
                        'name' => $blog->user->name,
                        'email' => $blog->user->email,
                        'dob' => $blog->user->dob,
                        'phone' => $blog->user->phone,
                        'gender' => $blog->user->gender,
                        'image' => $blog->user->image,
                    ],
                    'hashtags' => $blog->hashtags->pluck('name')
                ];
            }), 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching user blogs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update a blog as a regular user (only if the blog is in draft status)
    public function updateUser(Request $request, $blog_id)
    {
        try {
            // Retrieve the blog using the provided blog_id
            $blog = Blog::findOrFail($blog_id);

            // Check if the authenticated user is the owner of the blog and if the status is draft
            if (auth()->user()->id !== $blog->user_id || $blog->status !== 'draft') {
                return response()->json([
                    'message' => 'Unauthorized or blog is not in draft status',
                ], 403);
            }

            // Validate the request data
            $validatedData = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'thumbnail' => 'nullable|url',
                'hashtags' => 'nullable|array',
                'hashtags.*' => 'string|max:50',
            ])->validate();

            // Update the blog
            $blog->update([
                'title' => $validatedData['title'],
                'content' => $validatedData['content'],
                'thumbnail' => $validatedData['thumbnail'] ?? '',
            ]);

            // Update hashtags
            $blog->hashtags()->detach();
            $hashtags = $validatedData['hashtags'] ?? [];

            foreach ($hashtags as $hashtagName) {
                $hashtag = Hashtag::firstOrCreate(['name' => $hashtagName]);
                $hashtag->increment('usage_count');
                $blog->hashtags()->attach($hashtag->id);
            }

            // Reload the blog with the hashtags relationship
            $blog->load('hashtags');

            // Return the blog details in the desired format without the outer "blog" key
            return response()->json([
                'blog_id' => $blog->blog_id,
                'title' => $blog->title,
                'content' => $blog->content,
                'thumbnail' => $blog->thumbnail,
                'status' => $blog->status,
                'like' => $blog->like,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'hashtags' => $blog->hashtags->pluck('name'), // Include hashtag names
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the blog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update a blog as an admin
    public function updateAdmin(Request $request, $blog_id)
    {
        try {
            if (!auth()->user()->admin && auth()->user()->role !== 'staff') {
                return response()->json([
                    'message' => 'Unauthorized. Only admins can perform this action.',
                ], 403);
            }

            $blog = Blog::findOrFail($blog_id);

            // Validate the request data
            $validatedData = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'status' => 'required|in:draft,published',
                'thumbnail' => 'nullable|url',
                'hashtags' => 'nullable|array',
                'hashtags.*' => 'string|max:50',
            ])->validate();

            // Update the blog
            $blog->update([
                'title' => $validatedData['title'],
                'content' => $validatedData['content'],
                'status' => $validatedData['status'],
                'thumbnail' => $validatedData['thumbnail'] ?? '',
            ]);

            // Update hashtags
            $blog->hashtags()->detach();
            $hashtags = $validatedData['hashtags'] ?? [];

            foreach ($hashtags as $hashtagName) {
                $hashtag = Hashtag::firstOrCreate(['name' => $hashtagName]);
                $hashtag->increment('usage_count');
                $blog->hashtags()->attach($hashtag->id);
            }

            // Reload the blog with its hashtags relationship
            $blog->load('hashtags');

            // Return the blog with the hashtags included in the blog object
            return response()->json([
                'blog' => [
                    'blog_id' => $blog->blog_id,
                    'title' => $blog->title,
                    'content' => $blog->content,
                    'status' => $blog->status,
                    'thumbnail' => $blog->thumbnail,
                    'like' => $blog->like,
                    'created_at' => $blog->created_at,
                    'updated_at' => $blog->updated_at,
                    'hashtags' => $blog->hashtags->pluck('name'), // Include hashtag names
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the blog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Change the blog status
    public function changeStatus(Request $request, $blog_id)
    {
        try {
            $blog = Blog::findOrFail($blog_id);

            $validatedData = $request->validate([
                'status' => 'required|in:draft,published',
            ]);

            $blog->update([
                'status' => $validatedData['status'],
            ]);

            return response()->json($blog);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while changing the blog status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function setLikes(Request $request, $blog_id)
    {
        // Kiểm tra xem người dùng có xác thực hay không
        $user = Auth::user(); // Lấy thông tin người dùng hiện tại
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401); // Trả về lỗi nếu người dùng chưa xác thực
        }

        // Xác thực và xử lý số lượng likes
        $validatedData = $request->validate([
            'like' => 'required|integer|min:0', // Yêu cầu số lượng likes phải là số nguyên không âm
        ]);

        // Tìm blog theo ID
        $blog = Blog::findOrFail($blog_id);

        // Kiểm tra xem người dùng đã like blog này chưa
        $existingLike = BlogLike::where('blog_id', $blog_id)->where('user_id', $user->id)->first();

        if ($existingLike) {
            return response()->json(['message' => 'You have already liked this blog'], 400); // Nếu người dùng đã like, trả về lỗi
        }

        // Cập nhật số lượt like
        $blog->like = $validatedData['like'];
        $blog->save();

        // Thêm like mới vào bảng blog_likes
        BlogLike::create([
            'blog_id' => $blog_id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Likes updated successfully!',
            'blog_id' => $blog->blog_id,
            'likes' => $blog->like,
        ], 200);
    }

// Tăng số lượt like lên 1 cho blog
    public function likeBlog($blog_id)
    {
        try {
            // Kiểm tra xem người dùng có xác thực hay không
            $user = Auth::user(); // Lấy thông tin người dùng hiện tại
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401); // Trả về lỗi nếu người dùng chưa xác thực
            }

            // Tìm blog theo ID
            $blog = Blog::findOrFail($blog_id);

            // Kiểm tra xem người dùng đã like blog này chưa
            $existingLike = BlogLike::where('blog_id', $blog_id)->where('user_id', $user->id)->first();

            if ($existingLike) {
                return response()->json(['message' => 'You have already liked this blog'], 400); // Nếu người dùng đã like, trả về lỗi
            }

            // Tăng số lượt like lên 1
            $blog->increment('like');

            // Thêm like mới vào bảng blog_likes
            BlogLike::create([
                'blog_id' => $blog_id,
                'user_id' => $user->id,
            ]);

            // Trả về thông tin blog sau khi update lượt like
            return response()->json([
                'message' => 'Blog liked successfully!',
                'blog_id' => $blog->blog_id,
                'title' => $blog->title,
                'like' => $blog->like,
            ], 200);
        } catch (\Exception $e) {
            // Log lỗi
            Log::error('Error liking blog', [
                'blog_id' => $blog_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while liking the blog',
                'error' => 'Internal Server Error',
            ], 500);
        }
    }

    // Hủy like cho blog
    public function unlikeBlog($blog_id)
    {
        try {
            // Kiểm tra xem người dùng đã xác thực chưa
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            // Tìm blog theo ID
            $blog = Blog::findOrFail($blog_id);

            // Kiểm tra xem người dùng đã like blog này chưa
            $like = BlogLike::where('blog_id', $blog_id)->where('user_id', $user->id)->first();

            if (!$like) {
                return response()->json(['message' => 'You have not liked this blog'], 400);
            }

            // Xóa like
            $like->delete();

            // Giảm số lượt like của blog
            $blog->decrement('like');

            return response()->json([
                'message' => 'Blog unliked successfully!',
                'blog_id' => $blog->blog_id,
                'like' => $blog->like,
            ], 200);
        } catch (\Exception $e) {
            // Log lỗi nếu có
            Log::error('Error unliking blog', [
                'blog_id' => $blog_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while unliking the blog',
                'error' => 'Internal Server Error',
            ], 500);
        }
    }

    // Delete a blog
    public function destroy($blog_id)
    {
        try {
            $blog = Blog::findOrFail($blog_id);
            $blog->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Blog not found or could not be deleted',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

// List all blogs with status 'draft'
    public function listDraftBlogs()
    {
        try {
            // Lấy tất cả các blog có trạng thái 'draft'
            $draftBlogs = Blog::where('status', 'draft')->get();

            // Kiểm tra nếu không có bài blog nào
            if ($draftBlogs->isEmpty()) {
                return response()->json([], 404); // Trả về mảng rỗng với mã lỗi 404
            }

            // Trả về danh sách các bài blog dạng draft
            return response()->json($draftBlogs->map(function ($blog) {
                return [
                    'blog_id' => $blog->blog_id,
                    'title' => $blog->title,
                    'content' => $blog->content,
                    'status' => $blog->status,
                    'thumbnail' => $blog->thumbnail,
                    'like' => $blog->like,
                    'created_at' => $blog->created_at,
                    'updated_at' => $blog->updated_at,
                    'user' => [
                        'user_id' => $blog->user->id, // Thông tin người dùng
                        'name' => $blog->user->name,
                        'email' => $blog->user->email,
                        'dob' => $blog->user->dob,
                        'phone' => $blog->user->phone,
                        'gender' => $blog->user->gender,
                        'image' => $blog->user->image,
                        ],
                    'hashtags' => $blog->hashtags->pluck('name'), // Lấy tên hashtag
                ];
            }), 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching draft blogs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

// Show all published blogs with user information
    public function showAllPublishedBlogs()
    {
        try {
            // Lấy tất cả các blog có trạng thái là 'published' kèm theo thông tin người dùng
            $blogs = Blog::with('user', 'hashtags') // 'user' là mối quan hệ giữa Blog và User
            ->where('status', 'published')
                ->get();

            // Kiểm tra nếu không tìm thấy blog nào
            if ($blogs->isEmpty()) {
                return response()->json([
                    'message' => 'No published blogs found',
                ], 404);
            }

            // Trả về danh sách các bài blog đã xuất bản kèm thông tin người dùng
            return response()->json($blogs->map(function ($blog) {
                return [
                    'blog_id' => $blog->blog_id,
                    'title' => $blog->title,
                    'content' => $blog->content,
                    'thumbnail' => $blog->thumbnail,
                    'like' => $blog->like,
                    'created_at' => $blog->created_at,
                    'updated_at' => $blog->updated_at,
                    'user' => [
                        'user_id' => $blog->user->id, // Giả sử user có id
                        'name' => $blog->user->name,
                        'email' => $blog->user->email,
                        'dob' => $blog->user->dob,
                        'phone' => $blog->user->phone,
                        'gender' => $blog->user->gender,
                        'image' => $blog->user->image,
                    ],
                    'hashtags' => $blog->hashtags->pluck('name'), // Lấy tên hashtag
                ];
            }), 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching published blogs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}