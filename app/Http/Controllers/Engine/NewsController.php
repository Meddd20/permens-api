<?php

namespace App\Http\Controllers\Engine;

use App\Models\Artikel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\Komentar;
use App\Models\KomentarLike;
use App\Models\Login;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class NewsController extends Controller
{
    public function createNews(Request $request) {
        $news_tags = ["Premenstrual Syndrome (PMS)", "Pregnancy", "Ovulation", "Ovulation", "Menstruation", "Trying to conceive", "Fertility", "Sex", "Perimenopause", "Birth Control"];

        $rules = [
            "writter" => "required|string|max:100",
            "title_ind" => "required|string|max:190",
            "title_eng" => "required|string|max:190",
            "slug_title_ind" => "required|string|max:255",
            "slug_title_eng" => "required|string|max:255",
            "banner" => "required|file|mimes:jpeg,jpg,png|max:2048",
            "content_ind" => "required|string",
            "content_eng" => "required|string",
            "video_link" => "required|string|max:255",
            "source" => "required|string|max:255",
            "tags" => ["required", Rule::in($news_tags)],
        ];
        $messages = [];
        $attributes = [
            "writter" => __('attribute.writter'),
            "title_ind" => __('attribute.title_ind'),
            "title_eng" => __('attribute.title_eng'),
            "slug_title_ind" => __('attribute.slug_title_ind'),
            "slug_title_eng" => __('attribute.slug_title_eng'),
            "banner" => __('attribute.banner'),
            "content_ind" => __('attribute.content_ind'),
            "content_eng" => __('attribute.content_eng'),
            "video_link" => __('attribute.video_link'),
            "source" => __('attribute.source'),
            "tags" => __('attribute.tags'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        $tags = $request->input('tags');
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;

        try {
            DB::beginTransaction();
            $create_news = new Artikel();
            $create_news->user_id = $user_id;
            $create_news->writter = $request->input('writter');
            $create_news->title_ind = $request->input('title_ind');
            $create_news->title_eng = $request->input('title_eng');
            $create_news->slug_title_ind = $request->input('slug_title_ind');
            $create_news->slug_title_eng = $request->input('slug_title_eng');

            if ($request->hasFile('banner')) {
                $file = $request->file('banner');
                $filePath = 'banners/';
                $fileName = time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($filePath), $fileName);
                $create_news->banner = $filePath . $fileName;
            }

            $create_news->content_ind = $request->input('content_ind');
            $create_news->content_eng = $request->input('content_eng');
            $create_news->video_link = $request->input('video_link');
            $create_news->source = $request->input('source');
            $create_news->tags = $tags;
            $create_news->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.article_created_success'),
                'article' => $create_news,
            ], Response::HTTP_OK);
        }catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        };

    }

    public function updateNews(Request $request, $id) {
        $news_tags = ["Premenstrual Syndrome (PMS)", "Pregnancy", "Ovulation", "Ovulation", "Menstruation", "Trying to conceive", "Fertility", "Sex", "Perimenopause", "Birth Control"];

        $rules = [
            "writter" => "required|string|max:100",
            "title_ind" => "required|string|max:190",
            "title_eng" => "required|string|max:190",
            "slug_title_ind" => "required|string|max:255",
            "slug_title_eng" => "required|string|max:255",
            "banner" => "required|file|mimes:jpeg,jpg,png|max:2048",
            "content_ind" => "required|string",
            "content_eng" => "required|string",
            "video_link" => "required|string|max:255",
            "source" => "required|string|max:255",
            "tags" => ["required", Rule::in($news_tags)],
        ];
        $messages = [];
        $attributes = [
            "writter" => __('attribute.writter'),
            "title_ind" => __('attribute.title_ind'),
            "title_eng" => __('attribute.title_eng'),
            "slug_title_ind" => __('attribute.slug_title_ind'),
            "slug_title_eng" => __('attribute.slug_title_eng'),
            "banner" => __('attribute.banner'),
            "content_ind" => __('attribute.content_ind'),
            "content_eng" => __('attribute.content_eng'),
            "video_link" => __('attribute.video_link'),
            "source" => __('attribute.source'),
            "tags" => __('attribute.tags'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
    
        try {
            $article = Artikel::find($id);
            if (!$article) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.article_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }
    
            DB::beginTransaction();

            $updateData = [
                "writter" => $request->input('writter'),
                "title_ind" => $request->input('title_ind'),
                "title_eng" => $request->input('title_eng'),
                "slug_title_ind" => $request->input('slug_title_ind'),
                "slug_title_eng" => $request->input('slug_title_eng'),
                "content_ind" => $request->input('content_ind'),
                "content_eng" => $request->input('content_eng'),
                "video_link" => $request->input('video_link'),
                "source" => $request->input('source'),
                "tags" => $request->input('tags'),
            ];

            if ($request->hasFile('banner')) {
                $file = $request->file('banner');
                $filePath = 'banners/';
                $fileName = time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($filePath), $fileName);
                $updateData['banner'] = $filePath . $fileName;

                if ($article->banner && file_exists(public_path($article->banner))) {
                    unlink(public_path($article->banner));
                }
            }

            $article->update($updateData);
            
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.article_updated_success'),
                'article' => $article,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteNews($id) {
        try {
            $article = Artikel::find($id);
            if (!$article) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.article_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }

            DB::beginTransaction();

            if ($article->banner && Storage::exists($article->banner)) {
                Storage::delete($article->banner);
            }

            $article->delete();

            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.article_deleted_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    

    public function showNews($id)
    {
        try {
            $article = Artikel::find($id);
            $parent_comments = Komentar::where("article_id", $id)->where("parent_id", 0)->get();
            $child_comments = Komentar::where("article_id", $id)->whereNotNull("parent_id")->get();

            if (!$article) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.article_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }

            if (count($parent_comments) > 0) {
                $commentHierarchy = [];

                foreach ($parent_comments as $parentComment) {
                    $isLikedByActiveUser = KomentarLike::where('user_id', $parentComment['user_id'])
                                    ->where('comment_id', $parentComment['id'])
                                    ->exists();

                    $commentData = [
                        'id' => $parentComment['id'],
                        'article_id' => $parentComment['article_id'],
                        'user_id' => $parentComment['user_id'],
                        'username' => Login::where("id", $parentComment['user_id'])->value('nama'),
                        'parent_id' => $parentComment['parent_id'],
                        'parent_comment_user_id' => $parentComment['parent_comment_user_id'],
                        'content' => $parentComment['content'],
                        'likes' => $parentComment['likes'],
                        'created_at' => $parentComment['created_at'],
                        'updated_at' => $parentComment['updated_at'],
                        'is_liked_by_active_user' => $isLikedByActiveUser,
                        'children' => [],
                    ];

                    foreach ($child_comments as $childComment) {
                        if ($childComment['parent_id'] == $parentComment['id']) {
                            $isLikedByActiveUser = KomentarLike::where('user_id', $childComment['user_id'])
                                    ->where('comment_id', $childComment['id'])
                                    ->exists();
                            
                            $commentData['children'][] = [
                                'id' => $childComment['id'],
                                'article_id' => $childComment['article_id'],
                                'user_id' => $childComment['user_id'],
                                'username' => Login::where("id", $childComment['user_id'])->value('nama'),
                                'parent_id' => $childComment['parent_id'],
                                'parent_comment_user_id' => $childComment['parent_comment_user_id'],
                                'parent_comment_user_username' => Login::where("id", $childComment['parent_comment_user_id'])->value('nama'),
                                'content' => $childComment['content'],
                                'likes' => $childComment['likes'],
                                'created_at' => $childComment['created_at'],
                                'updated_at' => $childComment['updated_at'],
                                'is_liked_by_active_user' => $isLikedByActiveUser,
                            ];
                        }
                    }

                    usort($commentData['children'], function($a, $b) {
                        return strtotime($a['created_at']) - strtotime($b['created_at']);
                    });
                
                    $commentHierarchy[] = $commentData;
                }

                usort($commentHierarchy, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                
            } else {
                $commentHierarchy = [];
            }

            return response()->json([
                'status' => 'success',
                'message' => __('response.article_retrieved_success'),
                'data' => [
                    "article" =>  $article,
                    "comments" => $commentHierarchy
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function showAllNews(Request $request) {
        try {
            $query = Artikel::query();
    
            if ($request->has('tags')) {
                $tags = explode(',', $request->input('tags'));
                $query->whereIn('tags', $tags);
            }
    
            $articles = $query->get();
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.article_retrieved_success'),
                'data' => $articles,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'failed',
                'message' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }    
}
