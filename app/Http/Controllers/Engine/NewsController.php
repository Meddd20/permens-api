<?php

namespace App\Http\Controllers\Engine;

use App\Models\Artikel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Models\Login;

class NewsController extends Controller
{
    public function createNews(Request $request) {

        # Input Validations
        $rules = [
            "writter" => "required|string|max:100",
            "title_ind" => "required|string|max:190",
            "title_eng" => "required|string|max:190",
            "slug_title_ind" => "required|string|max:255",
            "slug_title_eng" => "required|string|max:255",
            "banner" => "required|string|max:190",
            "content_ind" => "required|string",
            "content_eng" => "required|string",
            "video_link" => "required|string|max:255",
            "source" => "required|string|max:255",
            "tags" => "required|string",
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
            ], 400);
        }

        $tags = $request->input('tags');

        try {
            DB::beginTransaction();
            $create_news = new Artikel();
            $create_news->id = Str::uuid();
            $create_news->writter = $request->input('writter');
            $create_news->title_ind = $request->input('title_ind');
            $create_news->title_eng = $request->input('title_eng');
            $create_news->slug_title_ind = $request->input('slug_title_ind');
            $create_news->slug_title_eng = $request->input('slug_title_eng');
            $create_news->banner = $request->input('banner');
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
            ], 200);
        }catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        };

    }

    public function updateNews(Request $request, $id) {
        # Input Validations
        $rules = [
            "writter" => "required|string|max:100",
            "title_ind" => "required|string|max:190",
            "title_eng" => "required|string|max:190",
            "slug_title_ind" => "required|string|max:255",
            "slug_title_eng" => "required|string|max:255",
            "banner" => "required|string|max:190",
            "content_ind" => "required|string",
            "content_eng" => "required|string",
            "video_link" => "required|string|max:255",
            "source" => "required|string|max:255",
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
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }
    
        try {
            $article = Artikel::find($id);
            if (!$article) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.article_not_found'),
                ], 404);
            }
    
            DB::beginTransaction();
            $article->update([
                "writter" => $request->input('writter'),
                "title_ind" => $request->input('title_ind'),
                "title_eng" => $request->input('title_eng'),
                "slug_title_ind" => $request->input('slug_title_ind'),
                "slug_title_eng" => $request->input('slug_title_eng'),
                "banner" => $request->input('banner'),
                "content_ind" => $request->input('content_ind'),
                "content_eng" => $request->input('content_eng'),
                "video_link" => $request->input('video_link'),
                "source" => $request->input('source'),
            ]);
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.article_updated_success'),
                'article' => $article,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }

    public function deleteNews($id) {
        try {
            $article = Artikel::find($id);
            if (!$article) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.article_not_found'),
                ], 404);
            }

            DB::beginTransaction();
    
            $article->delete();

            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.article_deleted_success'),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }
    

    public function showNews($id)
    {
        try {
            $article = Artikel::find($id);

            if (!$article) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.article_not_found'),
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => __('response.article_retrieved_success'),
                'user' => $article,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
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
                'articles' => $articles,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'failed',
                'message' => $th->getMessage(),
            ], 400);
        }
    }    
}
