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

            $blogs = Blog::with(['hashtags', 'user'])->get();

            $publishedCount = Blog::where('status', 'published')->count();
            $draftCount = Blog::where('status', 'draft')->count();

            $statusCounts = [
                'draft' => $draftCount,
                'published' => $publishedCount,
            ];

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
                    'hashtags' => $blog->hashtags->pluck('name')->toArray(),
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

            return response()->json([
                'blogs' => $blogsWithHashtagsAndUser,
                'status_counts' => $statusCounts,
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

            $validatedData = Validator::make($request->all(), $rules)->validate();
            $hashtags = $validatedData['hashtags'] ?? [];

            $blog = Blog::create([
                'title' => $validatedData['title'],
                'user_id' => auth()->id(),
                'content' => $validatedData['content'],
                'status' => $isAdmin ? $validatedData['status'] : 'draft',
                'thumbnail' => $validatedData['thumbnail'] ?? '',
                'like' => 0
              
            ]);

            $hashtagIds = [];
            foreach ($hashtags as $hashtagName) {
                $hashtag = Hashtag::firstOrCreate(['name' => $hashtagName]);
                $hashtag->increment('usage_count');
                $hashtagIds[] = $hashtag->id;
            }

            $blog->hashtags()->attach($hashtagIds);

            $blog->load(['hashtags', 'user']);

            return response()->json([
                'blog_id' => $blog->blog_id,
                'title' => $blog->title,
                'content' => $blog->content,
                'thumbnail' => $blog->thumbnail,
                'like' => $blog->like,
                'status' => $blog->status,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'hashtags' => $blog->hashtags->pluck('name'),
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
            $blog = Blog::with('user', 'hashtags')->findOrFail($blog_id);

            $user = Auth::user();

            $liked_by_user = false;
            if ($user) {
                $liked_by_user = BlogLike::where('blog_id', $blog_id)->where('user_id', $user->id)->exists();
            }

            $hashtags = $blog->hashtags->pluck('name');

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
                'liked_by_user' => $liked_by_user,
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
            $user = $request->user();

            if (!$user) {
                return response()->json(['message' => 'User not authenticated or invalid token.'], 401);
            }

            $blogs = Blog::where('user_id', $user->id)->with('user')->get();

            if ($blogs->isEmpty()) {
                return response()->json(['message' => 'No blogs found for this user.'], 404);
            }

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
            $blog = Blog::findOrFail($blog_id);

            if (auth()->user()->id !== $blog->user_id || $blog->status !== 'draft') {
                return response()->json([
                    'message' => 'Unauthorized or blog is not in draft status',
                ], 403);
            }

            $validatedData = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'thumbnail' => 'nullable|url',
                'hashtags' => 'nullable|array',
                'hashtags.*' => 'string|max:50',
            ])->validate();

            $blog->update([
                'title' => $validatedData['title'],
                'content' => $validatedData['content'],
                'thumbnail' => $validatedData['thumbnail'] ?? '',
            ]);

            $blog->hashtags()->detach();
            $hashtags = $validatedData['hashtags'] ?? [];

            foreach ($hashtags as $hashtagName) {
                $hashtag = Hashtag::firstOrCreate(['name' => $hashtagName]);
                $hashtag->increment('usage_count');
                $blog->hashtags()->attach($hashtag->id);
            }

            $blog->load('hashtags');

            return response()->json([
                'blog_id' => $blog->blog_id,
                'title' => $blog->title,
                'content' => $blog->content,
                'thumbnail' => $blog->thumbnail,
                'status' => $blog->status,
                'like' => $blog->like,
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
                'hashtags' => $blog->hashtags->pluck('name'),
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

            $validatedData = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'status' => 'required|in:draft,published',
                'thumbnail' => 'nullable|url',
                'hashtags' => 'nullable|array',
                'hashtags.*' => 'string|max:50',
            ])->validate();

            $blog->update([
                'title' => $validatedData['title'],
                'content' => $validatedData['content'],
                'status' => $validatedData['status'],
                'thumbnail' => $validatedData['thumbnail'] ?? '',
            ]);

            $blog->hashtags()->detach();
            $hashtags = $validatedData['hashtags'] ?? [];

            foreach ($hashtags as $hashtagName) {
                $hashtag = Hashtag::firstOrCreate(['name' => $hashtagName]);
                $hashtag->increment('usage_count');
                $blog->hashtags()->attach($hashtag->id);
            }

            $blog->load('hashtags');

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
                    'hashtags' => $blog->hashtags->pluck('name'),
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
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $validatedData = $request->validate([
            'like' => 'required|integer|min:0',
        ]);

        $blog = Blog::findOrFail($blog_id);

        $existingLike = BlogLike::where('blog_id', $blog_id)->where('user_id', $user->id)->first();

        if ($existingLike) {
            return response()->json(['message' => 'You have already liked this blog'], 400);
        }

        $blog->like = $validatedData['like'];
        $blog->save();

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

// Tăng số lượt like lên cho blog
    public function likeBlog($blog_id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            $blog = Blog::findOrFail($blog_id);

            $existingLike = BlogLike::where('blog_id', $blog_id)->where('user_id', $user->id)->first();

            if ($existingLike) {
                return response()->json(['message' => 'You have already liked this blog'], 400);
            }

            $blog->increment('like');

            BlogLike::create([
                'blog_id' => $blog_id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Blog liked successfully!',
                'blog_id' => $blog->blog_id,
                'title' => $blog->title,
                'like' => $blog->like,
            ], 200);
        } catch (\Exception $e) {
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
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }

            $blog = Blog::findOrFail($blog_id);

            $like = BlogLike::where('blog_id', $blog_id)->where('user_id', $user->id)->first();

            if (!$like) {
                return response()->json(['message' => 'You have not liked this blog'], 400);
            }

            $like->delete();

            $blog->decrement('like');

            return response()->json([
                'message' => 'Blog unliked successfully!',
                'blog_id' => $blog->blog_id,
                'like' => $blog->like,
            ], 200);
        } catch (\Exception $e) {
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

//     Delete a blog
    public function destroy($blog_id)
    {
        try {
            $blog = Blog::findOrFail($blog_id);

            // Xóa blog
            $blog->delete();

            return response()->json([
                'message' => 'Blog deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the blog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

// List all blogs with status 'draft'
    public function listDraftBlogs()
    {
        try {
            $draftBlogs = Blog::where('status', 'draft')->get();

            if ($draftBlogs->isEmpty()) {
                return response()->json([], 404);
            }

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
                        'user_id' => $blog->user->id,
                        'name' => $blog->user->name,
                        'email' => $blog->user->email,
                        'dob' => $blog->user->dob,
                        'phone' => $blog->user->phone,
                        'gender' => $blog->user->gender,
                        'image' => $blog->user->image,
                        ],
                    'hashtags' => $blog->hashtags->pluck('name'),
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
            $blogs = Blog::with('user', 'hashtags')
            ->where('status', 'published')
                ->get();

            if ($blogs->isEmpty()) {
                return response()->json([
                    'message' => 'No published blogs found',
                ], 404);
            }

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
                        'user_id' => $blog->user->id,
                        'name' => $blog->user->name,
                        'email' => $blog->user->email,
                        'dob' => $blog->user->dob,
                        'phone' => $blog->user->phone,
                        'gender' => $blog->user->gender,
                        'image' => $blog->user->image,
                    ],
                    'hashtags' => $blog->hashtags->pluck('name'),
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