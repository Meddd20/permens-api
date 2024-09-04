<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\MasterDataVersion;
use App\Models\MasterKehamilan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MasterKehamilanController extends Controller
{
    public function createDataKehamilan(Request $request){
        $validated = $request->validate([
            'minggu_kehamilan' => 'required|integer',
            'berat_janin' => 'required|integer',
            'tinggi_badan_janin' => 'required|numeric',
            'ukuran_bayi_id' => 'required|string|max:50',
            'ukuran_bayi_en' => 'required|string|max:50',
            'poin_utama_id' => 'required|string',
            'poin_utama_en' => 'required|string',
            'perkembangan_bayi_id' => 'required|string',
            'perkembangan_bayi_en' => 'required|string',
            'perubahan_tubuh_id' => 'required|string',
            'perubahan_tubuh_en' => 'required|string',
            'gejala_umum_id' => 'required|string',
            'gejala_umum_en' => 'required|string',
            'tips_mingguan_id' => 'required|string',
            'tips_mingguan_en' => 'required|string',
            'bayi_img_path' => 'required|string|max:255',
            'ukuran_bayi_img_path' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();
            $new_pregnancy_data = new MasterKehamilan();
            $new_pregnancy_data->minggu_kehamilan = $validated['minggu_kehamilan'];
            $new_pregnancy_data->berat_janin = $validated['berat_janin'];
            $new_pregnancy_data->tinggi_badan_janin = $validated['tinggi_badan_janin'];
            $new_pregnancy_data->ukuran_bayi_id = $validated['ukuran_bayi_id'];
            $new_pregnancy_data->ukuran_bayi_en = $validated['ukuran_bayi_en'];
            $new_pregnancy_data->poin_utama_id = $validated['poin_utama_id'];
            $new_pregnancy_data->poin_utama_en = $validated['poin_utama_en'];
            $new_pregnancy_data->perkembangan_bayi_id = $validated['perkembangan_bayi_id'];
            $new_pregnancy_data->perkembangan_bayi_en = $validated['perkembangan_bayi_en'];
            $new_pregnancy_data->perubahan_tubuh_id = $validated['perubahan_tubuh_id'];
            $new_pregnancy_data->perubahan_tubuh_en = $validated['perubahan_tubuh_en'];
            $new_pregnancy_data->gejala_umum_id = $validated['gejala_umum_id'];
            $new_pregnancy_data->gejala_umum_en = $validated['gejala_umum_en'];
            $new_pregnancy_data->tips_mingguan_id = $validated['tips_mingguan_id'];
            $new_pregnancy_data->tips_mingguan_en = $validated['tips_mingguan_en'];
            $new_pregnancy_data->bayi_img_path = $validated['bayi_img_path'];
            $new_pregnancy_data->ukuran_bayi_img_path = $validated['ukuran_bayi_img_path'];
            $new_pregnancy_data->save();

            $master_kehamilan_version = MasterDataVersion::where('master_table', 'master_kehamilan')->first();
            $master_kehamilan_version->major_version += 1;
            $master_kehamilan_version->minor_version = 0;
            $master_kehamilan_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.data_kehamilan_created_success'),
                'data_kehamilan' => $new_pregnancy_data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateDataKehamilan(Request $request, $id){
        $validated = $request->validate([
            'minggu_kehamilan' => 'required|integer',
            'berat_janin' => 'required|integer',
            'tinggi_badan_janin' => 'required|numeric',
            'ukuran_bayi_id' => 'required|string|max:50',
            'ukuran_bayi_en' => 'required|string|max:50',
            'poin_utama_id' => 'required|string',
            'poin_utama_en' => 'required|string',
            'perkembangan_bayi_id' => 'required|string',
            'perkembangan_bayi_en' => 'required|string',
            'perubahan_tubuh_id' => 'required|string',
            'perubahan_tubuh_en' => 'required|string',
            'gejala_umum_id' => 'required|string',
            'gejala_umum_en' => 'required|string',
            'tips_mingguan_id' => 'required|string',
            'tips_mingguan_en' => 'required|string',
            'bayi_img_path' => 'required|string|max:255',
            'ukuran_bayi_img_path' => 'required|string|max:255',
        ]);

        $updated_data_kehamilan = MasterKehamilan::find($id);

        if (!$updated_data_kehamilan) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.data_kehamilan_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();
            $updated_data_kehamilan->fill($validated);
            $updated_data_kehamilan->save();
            
            $master_kehamilan_version = MasterDataVersion::where('master_table', 'master_kehamilan')->first();
            $master_kehamilan_version->minor_version += 1;
            $master_kehamilan_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.data_kehamilan_updated_success'),
                'data_kehamilan' => $updated_data_kehamilan,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteDataKehamilan($id) {
        $deleted_data_kehamilan = MasterKehamilan::find($id);

        if (!$deleted_data_kehamilan) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.data_kehamilan_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();
            $deleted_data_kehamilan->delete();

            $master_kehamilan_version = MasterDataVersion::where('master_table', 'master_kehamilan')->first();
            $master_kehamilan_version->major_version += 1;
            $master_kehamilan_version->minor_version = 0;
            $master_kehamilan_version->save();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.data_kehamilan_deleted_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllDataKehamilan() {
        try {
            $all_data_kehamilan = MasterKehamilan::all();

            return response()->json([
                'status' => 'success',
                'message' => __('response.data_kehamilan_fetched_success'),
                'data' => $all_data_kehamilan,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
