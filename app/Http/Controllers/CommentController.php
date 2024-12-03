<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    // Create a new comment for a specific blog
    public function store(Request $request, $blog_id)
    {
        try {
            $validatedData = $request->validate([
                'content' => 'required|string',
                'parent_id' => 'nullable|exists:comments,comment_id',
            ]);

            // Create a new comment
            $comment = Comment::create([
                'blog_id' => $blog_id,
                'user_id' => auth()->id(),
                'content' => $validatedData['content'],
                'parent_id' => $validatedData['parent_id'] ?? null,
            ]);

            $comment->load('user:id,name,image,dob,role,phone,gender,email', 'replies');

            return response()->json($comment, 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the comment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Get all comments for a specific blog
    public function index($blog_id)
    {
        try {
            $comments = Comment::where('blog_id', $blog_id)
                ->whereNull('parent_id')
                ->with('user:id,name,image,dob,role,phone,gender,email')
                ->get()
                ->each(function($comment) {
                    $comment->setRelation('replies', $comment->getRepliesWithUsers());
                });

            if ($comments->isEmpty()) {
                return response()->json([], 200);
            }

            return response()->json($comments, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving comments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update a specific comment
    public function update(Request $request, $blog_id, $comment_id)
    {
        try {
            $validatedData = $request->validate([
                'content' => 'required|string',
            ]);

            $comment = Comment::findOrFail($comment_id);

            if ($comment->user_id !== auth()->id()) {
                return response()->json([
                    'message' => 'You are not authorized to update this comment.',
                ], 403);
            }

            $comment->update([
                'content' => $validatedData['content'],
            ]);

            $comment->load('user');

            return response()->json($comment, 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Comment not found.',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the comment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete a specific comment
    public function destroy($blog_id, $comment_id)
    {
        try {
            $comment = Comment::findOrFail($comment_id);

            if ($comment->user_id !== auth()->id()) {
                return response()->json([
                    'message' => 'You are not authorized to delete this comment.',
                ], 403);
            }

            $comment->delete();

            return response()->json([
                'message' => "Comment {$comment_id} deleted successfully."
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Comment not found.',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the comment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}