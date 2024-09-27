<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Komentar;
use App\Models\KomentarLike;
use App\Models\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CommentController extends Controller
{
    public function createComment(Request $request) 
    {
        $rules = [
            'parent_id' => 'nullable',
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
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = Login::where('token', $request->header('userToken'))->first();
        $parent_comment_user_id = null;

        if ($request->input('parent_id') != null) {
            $parentComment = Komentar::find($request->input('parent_id'));
            if ($parentComment) {
                $parent_comment_user_id = $parentComment->user_id;
            }
        }

        try {
            DB::beginTransaction();

            $create_comments = new Komentar();
            $create_comments->user_id = $user->id;
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
            ], Response::HTTP_OK);
        }catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $comment = Komentar::find($id);
            if (!$comment) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.comment_not_found'),
                ], Response::HTTP_NOT_FOUND);
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
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteComment($id) {
        try {
            $comment = Komentar::find($id);
            
            if (!$comment) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.comment_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }

            DB::beginTransaction();

            $comment->delete();
            if ($comment->parent_id == null) {
                $child_comments = Komentar::where("parent_id", $id)->get();
                foreach ($child_comments as $child_comment) {
                    $child_comment->delete();
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.comment_deleted_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
        ], Response::HTTP_OK);
    }

    public function likeComment(Request $request) {
        try {
            $rules = [
                'comment_id' => 'required|exists:tb_komentar,id',
            ];
            $messages = [];
            $attributes = [
                'user_id' => __('attribute.user_id'),
                'comment_id' => __('attribute.concomment_idtent'),
            ];
            $validator = Validator::make($request->all(), $rules, $messages, $attributes);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            $user = Login::where('token', $request->header('userToken'))->first();
            $userId = $user->id;
            $comment = Komentar::find($request->input('comment_id'));
            $commentId = $request->input('comment_id');
            $existingLike = KomentarLike::where('user_id', $userId)
                                    ->where('comment_id', $commentId)
                                    ->first();
            
            DB::beginTransaction();

            if ($existingLike) {
                $existingLike->delete();
                $comment->decrement('likes');
            } else {
                KomentarLike::create([
                    'user_id' => $userId,
                    'comment_id' => $commentId
                ]);
                $comment->increment('likes');
            }

            $comment->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.comment_liked_success'),
                'data' => KomentarLike::where('user_id', $userId)
                            ->where('comment_id', $commentId)
                            ->exists()
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
