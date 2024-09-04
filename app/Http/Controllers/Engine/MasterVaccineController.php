<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\MasterDataVersion;
use App\Models\MasterVaccine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MasterVaccineController extends Controller
{
    public function createVaccineData(Request $request){
        $validated = $request->validate([
            'vaccines_id' => 'required|string',
            'vaccines_en' => 'required|string',
            'description_id' => 'required|string',
            'description_en' => 'required|string',
        ]);

        try {
            DB::beginTransaction();
            $new_vaccine_data = new MasterVaccine();
            $new_vaccine_data->vaccines_id = $validated['vaccines_id'];
            $new_vaccine_data->vaccines_en = $validated['vaccines_en'];
            $new_vaccine_data->description_id = $validated['description_id'];
            $new_vaccine_data->description_en = $validated['description_en'];
            $new_vaccine_data->save();

            $master_vaccines_version = MasterDataVersion::where('master_table', 'master_vaccines')->first();
            $master_vaccines_version->major_version += 1;
            $master_vaccines_version->minor_version = 0;
            $master_vaccines_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.vaccine_data_created_success'),
                'vaccine_data' => $new_vaccine_data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateVaccineData(Request $request, $id){
        $validated = $request->validate([
            'vaccines_id' => 'required|string',
            'vaccines_en' => 'required|string',
            'description_id' => 'required|string',
            'description_en' => 'required|string',
        ]);

        $updated_vaccine_data = MasterVaccine::find($id);

        if (!$updated_vaccine_data) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.vaccine_data_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();
            $updated_vaccine_data->fill($validated);
            $updated_vaccine_data->save();
            
            $master_vaccines_version = MasterDataVersion::where('master_table', 'master_vaccines')->first();
            $master_vaccines_version->minor_version += 1;
            $master_vaccines_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.vaccine_data_updated_success'),
                'vaccine_data' => $updated_vaccine_data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteVaccineData($id) {
        $deleted_vaccine_data = MasterVaccine::find($id);

        if (!$deleted_vaccine_data) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.vaccine_data_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();
            $deleted_vaccine_data->delete();

            $master_vaccines_version = MasterDataVersion::where('master_table', 'master_vaccines')->first();
            $master_vaccines_version->major_version += 1;
            $master_vaccines_version->minor_version = 0;
            $master_vaccines_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.vaccine_data_deleted_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllVaccineData() {
        try {
            $all_vaccine_data = MasterVaccine::all();

            return response()->json([
                'status' => 'success',
                'message' => __('response.vaccine_data_fetched_success'),
                'data' => $all_vaccine_data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
