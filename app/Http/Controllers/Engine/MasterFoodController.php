<?php

namespace App\Http\Controllers;

use App\Models\MasterDataVersion;
use App\Models\MasterFood;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MasterFoodController extends Controller
{
    public function createFood(Request $request){
        $food_safety = ["Caution", "Safe", "Unsafe"];

        $validated = $request->validate([
            'food_id' => 'required|string',
            'food_en' => 'required|string',
            'description_id' => 'required|string',
            'description_en' => 'required|string',
            'food_safety' => ["required", Rule::in($food_safety)],
        ]);

        try {
            DB::beginTransaction();
            $new_food = new MasterFood();
            $new_food->food_id = $validated['food_id'];
            $new_food->food_en = $validated['food_en'];
            $new_food->description_id = $validated['description_id'];
            $new_food->description_en = $validated['description_en'];
            $new_food->food_safety = $validated['food_safety'];
            $new_food->save();

            $master_food_version = MasterDataVersion::where('master_table', 'master_food')->first();
            $master_food_version->major_version += 1;
            $master_food_version->minor_version = 0;
            $master_food_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.food_created_success'),
                'food' => $new_food,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateFood(Request $request, $id){
        $food_safety = ["Caution", "Safe", "Unsafe"];

        $validated = $request->validate([
            'food_id' => 'required|string',
            'food_en' => 'required|string',
            'description_id' => 'required|string',
            'description_en' => 'required|string',
            'food_safety' => ["required", Rule::in($food_safety)],
        ]);

        $updated_food = MasterFood::find($id);

        if (!$updated_food) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.food_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();
            $updated_food->fill($validated);
            $updated_food->save();
            
            $master_food_version = MasterDataVersion::where('master_table', 'master_food')->first();
            $master_food_version->minor_version += 1;
            $master_food_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.food_updated_success'),
                'food' => $updated_food,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteFood($id) {
        $deleted_food = MasterFood::find($id);

        if (!$deleted_food) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.food_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();
            $deleted_food->delete();

            $master_food_version = MasterDataVersion::where('master_table', 'master_food')->first();
            $master_food_version->major_version += 1;
            $master_food_version->minor_version = 0;
            $master_food_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.food_deleted_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllFood() {
        try {
            $all_food = MasterFood::all();

            return response()->json([
                'status' => 'success',
                'message' => __('response.food_fetched_success'),
                'data' => $all_food,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
