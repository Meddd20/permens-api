<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\MasterDataVersion;
use App\Models\MasterVitamin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MasterVitaminController extends Controller
{
    public function createVitaminData(Request $request){
        $validated = $request->validate([
            'vitamins_id' => 'required|string',
            'vitamins_en' => 'required|string',
            'description_id' => 'required|string',
            'description_en' => 'required|string',
        ]);

        try {
            DB::beginTransaction();
            $new_vitamin_data = new MasterVitamin();
            $new_vitamin_data->vitamins_id = $validated['vitamins_id'];
            $new_vitamin_data->vitamins_en = $validated['vitamins_en'];
            $new_vitamin_data->description_id = $validated['description_id'];
            $new_vitamin_data->description_en = $validated['description_en'];
            $new_vitamin_data->save();

            $master_vitamins_version = MasterDataVersion::where('master_table', 'master_vitamins')->first();
            $master_vitamins_version->major_version += 1;
            $master_vitamins_version->minor_version = 0;
            $master_vitamins_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.vitamin_data_created_success'),
                'vitamin_data' => $new_vitamin_data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateVitaminData(Request $request, $id){
        $validated = $request->validate([
            'vitamins_id' => 'required|string',
            'vitamins_en' => 'required|string',
            'description_id' => 'required|string',
            'description_en' => 'required|string',
        ]);

        $updated_vitamin_data = MasterVitamin::find($id);

        if (!$updated_vitamin_data) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.vitamin_data_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();
            $updated_vitamin_data->fill($validated);
            $updated_vitamin_data->save();
            
            $master_vitamins_version = MasterDataVersion::where('master_table', 'master_vitamins')->first();
            $master_vitamins_version->minor_version += 1;
            $master_vitamins_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.vitamin_data_updated_success'),
                'vitamin_data' => $updated_vitamin_data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteVitaminData($id) {
        $deleted_vitamin_data = MasterVitamin::find($id);

        if (!$deleted_vitamin_data) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.vitamin_data_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();
            $deleted_vitamin_data->delete();

            $master_vitamins_version = MasterDataVersion::where('master_table', 'master_vitamins')->first();
            $master_vitamins_version->major_version += 1;
            $master_vitamins_version->minor_version = 0;
            $master_vitamins_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.vitamin_data_deleted_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllVitaminData() {
        try {
            $all_vitamin_data = MasterVitamin::all();

            return response()->json([
                'status' => 'success',
                'message' => __('response.vitamin_data_fetched_success'),
                'data' => $all_vitamin_data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
