<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Models\RiwayatLog;

class LogsController extends Controller
{
    public function storeLog(Request $request) {

        $sexOptions = ["Sex", "Didn't have sex", "Unprotected sex", "Protected sex"];
        $vaginalDischargeOptions = ["No discharges", "Creamy", "Spotting", "Eggwhite", "Sticky", "Watery", "Unusual"];

        $rules = [
            "date" => "required|date",
            "pregnancy_signs" => "required|json",
            "sex_activity" => ["required", Rule::in($sexOptions)],
            "symptoms" => "required|json",
            "vaginal_discharge" => ["required", Rule::in($vaginalDischargeOptions)],
            "moods" => "required|json",
            "others" => "required|json",
            "temperature" => "nullable|regex:/^\d+(\.\d{1,2})?$/",
            "weight" => "nullable|numeric",
            "reminder" => "required|json",
            "notes" => "nullable|string"
        ];
        $messages = [];
        $attributes = [
            "date" => __('attribute.date'),
            "pregnancy_signs" => __('attribute.pregnancy_signs'),
            "sex_activity" => __('attribute.sex_activity'),
            "symptoms" => __('attribute.symptoms'),
            "vaginal_discharge" => __('attribute.vaginal_discharge'),
            "moods" => __('attribute.moods'),
            "others" => __('attribute.others'),
            "temperature" => __('attribute.temperature'),
            "weight" => __('attribute.weight'),
            "reminder" => __('attribute.reminder'),
            "notes" => __('attribute.notes'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }
    
        $uid = $request->header('user_id');
        $dateToCheck = $request->input('date');
    
        $userData = RiwayatLog::where('user_id', $uid)->first();
    
        $newData = [
            "date" => $dateToCheck,
            "pregnancy_signs" => json_decode($request->input('pregnancy_signs')),
            "sex_activity" => $request->input('sex_activity'),
            "symptoms" => json_decode($request->input('symptoms')),
            "vaginal_discharge" => $request->input('vaginal_discharge'),
            "moods" => json_decode($request->input('moods')),
            "others" => json_decode($request->input('others')),
            "temperature" => $request->input('temperature'),
            "weight" => $request->input('weight'),
            "reminder" => json_decode($request->input('reminder')),
            "notes" => $request->input('notes')
        ];
    
        if ($userData) {
            $message = "updated";
            // User data exists, update the JSON with the new data
            $userDataArray = $userData->data_harian;
            $userDataArray[$dateToCheck] = $newData;
    
            // Update the user data
            $userData->data_harian = $userDataArray;
            $userData->save();
        } else {
            $message = "created";
            // User data doesn't exist, create a new record with the data
            $userDataArray = [
                $dateToCheck => $newData
            ];

            $userData = new RiwayatLog();
            $userData->user_id = $uid;
            $userData->data_harian = $userDataArray;
            $userData->save();
        }
    
        return response()->json([
            'status' => 'success',
            'message' => 'Daily log '.$message.' successfully',
            'user_id' => $uid,
            'data' => $userDataArray
        ]);
    }

    public function deleteLogByDate(Request $request) {
        $uid = $request->header('user_id');
        $dateToDelete = $request->input('date');
    
        // Cari data pengguna berdasarkan ID pengguna dan tanggal yang akan dihapus
        $userData = RiwayatLog::where('user_id', $uid)->first();
    
        if ($userData) {
            $userDataArray = $userData->data_harian;
    
            // Periksa apakah data dengan tanggal yang akan dihapus ada dalam array data pengguna
            if (isset($userDataArray[$dateToDelete])) {
                // Hapus data dengan tanggal yang sesuai
                unset($userDataArray[$dateToDelete]);
    
                // Update data pengguna di database
                $userData->data_harian = $userDataArray;
                $userData->save();
    
                return response()->json([
                    'status' => 'success',
                    'message' => __('response.log_deleted_success'),
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.log_not_found'),
                ], 404);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('response.user_data_not_found'),
            ], 404);
        }
    }    

    public function allLogs(Request $request) {
        $uid = $request->header('user_id');
        $userData = RiwayatLog::where('user_id', $uid)->first();
    
        if (!$userData) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.user_data_not_found'),
            ], 404);
        }
    
        return response()->json([
            'user_id' => $uid,
            'data' => $userData->data_harian
        ]);
    }

    public function logsByDate(Request $request, $date) {
        $uid = $request->header('user_id');
        $userData = RiwayatLog::where('user_id', $uid)->first();
    
        if (!$userData) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.user_data_not_found'),
            ], 404);
        }
    
        if (isset($userData->data_harian[$date])) {
            return response()->json([
                'status' => 'success',
                'message' => __('response.log_retrieved_success'),
                'user_id' => $uid,
                'date' => $date,
                'data' => $userData->data_harian[$date]
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('response.log_not_found'),
            ], 404);
        }
    }

    public function logsByTags(Request $request, $tags) {
        $uid = $request->header('user_id');
        $userData = RiwayatLog::where('user_id', $uid)->first();
    
        if (!$userData) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.user_data_not_found'),
            ], 404);
        }
    
        $tagsValues = [];
    
        foreach ($userData->data_harian as $date => $data) {
            if (isset($data[$tags])) {
                $tagsValues[$date] = $data[$tags];
            }
        }
    
        return response()->json([
            'status' => 'success',
            'message' => __('response.log_retrieved_success'),
            'user_id' => $uid,
            'tags' => $tags,
            'values' => $tagsValues
        ]);
    }
    
}
