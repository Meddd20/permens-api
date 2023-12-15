<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Komentar;
use App\Models\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function createComment(Request $request) 
    {
        $rules = [
            'parent_id' => 'sometimes|exists:tb_komentar,id',
            'article_id' => 'required',
            'content' => 'required|string|max:500',
        ];
        $messages = [];
        $attributes = [
            'parent_id' => __('attribute.parent_id'),
            'article_id' => __('attribute.article_id'),
            'content' => __('attribute.content'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        $user = Login::where('id', $request->header('user_id'))->first();
        $user_id = $user->id;
        $parent_comment_user_id = null;

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('respone.user_not_found'),
            ], 404);
        }

        if ($request->input('parent_id')) {
            $parentComment = Komentar::find($request->input('parent_id'));
            if ($parentComment) {
                $parent_comment_user_id = $parentComment->user_id;
            }
        }

        try {
            DB::beginTransaction();
            $create_comments = new Komentar();
            $create_comments->id = Str::uuid();
            $create_comments->user_id = $user_id;
            $create_comments->parent_id = $request->input('parent_id');
            $create_comments->article_id = $request->input('article_id');
            $create_comments->parent_comment_user_id = $parent_comment_user_id;
            $create_comments->content = $request->input('content');
            $create_comments->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.comment_created_success'),
                'comment' => $create_comments,
            ], 200);
        }catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        };
    }

    public function updateComment(Request $request, $id) {
        $rules = [
            'content' => 'required|string|max:500',
        ];
        $messages = [];
        $attributes = [
            'content' => __('attribute.content'),
        ];

        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        try {
            $comment = Komentar::find($id);
            if (!$comment) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.comment_not_found'),
                ], 404);
            }

            DB::beginTransaction();
            $comment->update([
                "content" => $request->input('content'),
            ]);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.comment_updated_success'),
                'comment' => $comment,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }

    public function deleteComment($id) {
        try {
            $comment = Komentar::find($id);
            if (!$comment) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.comment_not_found'),
                ], 404);
            }

            DB::beginTransaction();

            $comment->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.comment_deleted_success'),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }

    public function showCommentsArticle($article_id)
    {
        $comments = Komentar::where('article_id', $article_id)
            ->orderBy('upvotes', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => __('response.get_comments_success'),
            'comments' => $comments,
        ], 200);
    }

    public function upvotes($id) {
        try {
            $comment = Komentar::find($id);
            DB::beginTransaction();
            $comment->increment('upvotes');
            $comment->save();
            DB::commit();

            return response()->json([
                'message' => __('response.comment_liked_success'),
                'upvotes' => $comment->upvotes,
            ], 200);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }

    public function downvotes($id) {
        try {
            $comment = Komentar::find($id);
            DB::beginTransaction();
            $comment->increment('downvotes');
            $comment->save();
            DB::commit();

            return response()->json([
                'message' => __('response.comment_disliked_success'),
                'downvotes' => $comment->downvotes,
            ], 200);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }

    public function togglePinned($id)
    {
        try {
            $comment = Komentar::find($id);
            DB::beginTransaction();
            $comment->is_pinned = $comment->is_pinned === '0' ? '1' : '0';
            $comment->save();
            DB::commit();

            $message = $comment->is_pinned 
                ? __('response.pinned_comments_success') 
                : __('response.unpinned_comments_success');

            return response()->json([
                'message' => $message
            ], 200);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }

    public function toggleHidden($id)
    {
        try {
            $comment = Komentar::find($id);
            DB::beginTransaction();
            $comment->is_hidden = $comment->is_hidden === '0' ? '1' : '0';
            $comment->save();
            DB::commit();

            $message = $comment->is_hidden 
                ? __('response.hide_comments_success')
                : __('response.unhide_comments_success');

            return response()->json([
                'message' => $message
            ], 200);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }

    // public function toggleFlag($id)
    // {
    //     try {
    //         $comment = Komentar::find($id);
    //         DB::beginTransaction();
    //         $comment->is_flagged = $comment->is_flagged === '0' ? '1' : '0';
    //         $comment->save();
    //         DB::commit();

    //         $message = $comment->is_flagged 
    //             ? 'Comment flagged successfully' 
    //             : 'Comment unflagged successfully';

    //         return response()->json([
    //             'message' => $message
    //         ], 200);

    //     } catch (\Throwable $th) {
    //         DB::rollback();
    //         return response()->json([
    //             "status" => "failed",
    //             "message" => $th->getMessage()
    //         ], 400);
    //     }
    // }

    // public function flagCount($id) {
    //     try {
    //         $comment = Komentar::find($id);
    //         DB::beginTransaction();
    //         $comment->increment('flag_count');
    //         $comment->save();
    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Comment flagged successfully',
    //             'flag_count' => $comment->flag_count,
    //         ], 200);

    //     } catch (\Throwable $th) {
    //         DB::rollback();
    //         return response()->json([
    //             "status" => "failed",
    //             "message" => $th->getMessage()
    //         ], 400);
    //     }
    // }
}
