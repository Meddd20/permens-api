<?php

namespace App\Http\Controllers\Engine;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\RiwayatKehamilan;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\BeratIdealIbuHamil;
use App\Models\Login;
use App\Models\RiwayatMens;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PregnancyController extends Controller
{
    public function pregnancyBegin(Request $request)
    {
        # Input Validation
        $validated = $request->validate([
            'hari_pertama_haid_terakhir' => 'required|date_format:Y-m-d|before_or_equal:today'
        ]);

        if ($request->header('user_id') == null) {
            $email_regis = $request->input('email_regis');
            $user = Login::where('email', $email_regis)->first();
            $user_id = $user->id;
        } else {
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
        }

        $estimated_due_dates = Carbon::parse(Carbon::parse($request->hari_pertama_haid_terakhir)->subMonth(3))->addYear(1)->addDays(7);

        $riwayatMensData = RiwayatMens::where('user_id', $user_id)
                ->where('is_actual', '1')
                ->get();

        $isCurrentKehamilanAvailable = RiwayatKehamilan::where('user_id', $user_id)
                ->where('status', 'Hamil')
                ->first();

        $successResponse = [
            "status" => "success",
            "message" => __('response.saving_success')
        ];
    
        # Return Response
        try {
            DB::beginTransaction();
                # Update the last period cycle data with the average cycle length to end the last period cycle before the current pregnancy
                if ($riwayatMensData->isNotEmpty()) {
                    $last_period_data = $riwayatMensData->sortByDesc('haid_awal')->first();
                
                    $valid_cycles = $riwayatMensData->whereNotNull('lama_siklus')->pluck('lama_siklus');
                
                    $avg_period_cycle = $valid_cycles->isNotEmpty() ? ceil($valid_cycles->sum() / $valid_cycles->count()) : 0;
                
                    if ($last_period_data) {
                        $last_period_data->lama_siklus = $avg_period_cycle;
                        $last_period_data->hari_terakhir_siklus = Carbon::parse($last_period_data->haid_awal)->addDays($avg_period_cycle);
                        $last_period_data->save();
                    }
                }

                # Check if the user is already marked as pregnant and if there is a current pregnancy record
                if ($user->is_pregnant == 1 && $isCurrentKehamilanAvailable != null) {
                    # Update the current pregnancy record with the new last menstruation date and estimated due date
                    $isCurrentKehamilanAvailable->update([
                        "hari_pertama_haid_terakhir" => $request->hari_pertama_haid_terakhir,
                        "tanggal_perkiraan_lahir" => $estimated_due_dates,
                    ]);

                    DB::commit();
                    
                    return response()->json($successResponse, Response::HTTP_OK);
                } else {
                    # Create a new pregnancy record if the user is not currently marked as pregnant
                    $current_new_pregnancy = RiwayatKehamilan::create([
                        "user_id" => $user_id,
                        "status" => 'Hamil',
                        "hari_pertama_haid_terakhir" => $request->hari_pertama_haid_terakhir,
                        "tanggal_perkiraan_lahir" => $estimated_due_dates,
                        "kehamilan_akhir" => NULL,
                    ]);

                    Login::where('id', $user_id)->update([
                        "is_pregnant" => '1'
                    ]);

                    DB::commit();

                    return response()->json(array_merge($successResponse, ["data" => $current_new_pregnancy]), Response::HTTP_OK);
                }
            DB::commit();

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => __('response.saving_failed').' | '.$th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function pregnancyEnd(Request $request)
    {
        # Input Validation
        $validated = $request->validate([
            'pregnancy_end' => 'required|date_format:Y-m-d|before_or_equal:today',
            'gender' => 'required|in:Boy,Girl'
        ]);

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;

        # Return Response
        try {
            $pregnancy = RiwayatKehamilan::where('user_id', $user_id)
                            ->where('status', "Hamil")
                            ->first();

            if (!$pregnancy) {
                return response()->json([
                    'status' => 'failed',
                    'message' => __('response.pregnancy_not_found')
                ], Response::HTTP_NOT_FOUND);
            }

            DB::beginTransaction();

                $pregnancy->update([
                    "status" => "Melahirkan",
                    "kehamilan_akhir" => $request->pregnancy_end,
                    "gender" => $request->gender,
                ]);

                Login::where('id', $user_id)->update([
                    "is_pregnant" => '0'
                ]);

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success')
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.saving_failed') . ' | ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deletePregnancy(Request $request)
    {
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $pregnancy = RiwayatKehamilan::where('user_id', $user_id)
                            ->where('status', "Hamil")
                            ->first();
        $weight_history = BeratIdealIbuHamil::where('user_id', $user_id)
                            ->where('riwayat_kehamilan_id', $pregnancy->id)
                            ->get();    

        if (!$pregnancy) {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.pregnancy_not_found')
            ], Response::HTTP_NOT_FOUND);
        }

        # Return Response
        try {
            DB::beginTransaction();
                Login::where('id', $user_id)->update([
                    "is_pregnant" => '0'
                ]);

                foreach ($weight_history as $weight) {
                    $weight->delete();
                }
                
                $pregnancy->delete();
            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success')
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.saving_failed') . ' | ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function initWeightPregnancyTracking(Request $request) {
        # Input Validation
        $validated = $request->validate([
            'tinggi_badan' => 'required|numeric|between:50,250',
            'berat_badan' => 'required|numeric|between:30,200',
            'is_twin' => 'nullable|integer|between:0,1',
        ]);

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)
                    ->where('status', 'Hamil')
                    ->first();

        if (!$current_pregnancy) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.pregnancy_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $tinggi_badan = $request->tinggi_badan;
        $berat_badan = $request->berat_badan;
        $tinggi_badan_in_meters = $tinggi_badan / 100;

        $bmi_prapregnancy = $berat_badan / pow($tinggi_badan_in_meters, 2);

        if ($bmi_prapregnancy < 18.5) {
            $kategori_bmi = 'underweight';
        } elseif ($bmi_prapregnancy >= 18.5 && $bmi_prapregnancy <= 24.9) {
            $kategori_bmi = 'normal';
        } elseif ($bmi_prapregnancy >= 25 && $bmi_prapregnancy <= 29.9) {
            $kategori_bmi = 'overweight';
        } else {
            $kategori_bmi = 'obese';
        }

        # Return Response
        try {
            DB::beginTransaction();
                $current_pregnancy->update([
                    "tinggi_badan" => $tinggi_badan,
                    "berat_prakehamilan" => $berat_badan,
                    "bmi_prakehamilan" => $bmi_prapregnancy,
                    "kategori_bmi" => $kategori_bmi,
                    "is_twin" => $request->is_twin,
                ]);

                $existing_init_weight = BeratIdealIbuHamil::where('user_id', $user_id)
                    ->where('riwayat_kehamilan_id', $current_pregnancy->id)
                    ->where('minggu_kehamilan', 0)
                    ->first();
                
                if ($existing_init_weight) {
                    $existing_init_weight->update([
                        "berat_badan" => $berat_badan,
                        "pertambahan_berat" => 0,
                    ]);
                    $init_weight = $existing_init_weight;
                } else {
                    $init_weight =  BeratIdealIbuHamil::create([
                        "user_id" => $user_id,
                        "riwayat_kehamilan_id" => $current_pregnancy->id,
                        "berat_badan" => $berat_badan,
                        "minggu_kehamilan" => 0,
                        "tanggal_pencatatan" => Carbon::parse($current_pregnancy->hari_pertama_haid_terakhir)->subDays(1)->toDateString(),
                        "pertambahan_berat" => 0,
                    ]);
                }

                $weight = BeratIdealIbuHamil::where('user_id', $user_id)
                    ->orderBy('minggu_kehamilan', 'asc')
                    ->orderBy('tanggal_pencatatan', 'asc')
                    ->get();
                $previous_index = 0;
                $next_index = 0;
                
                foreach ($weight as $key => $w) {
                    if ($w->id == $init_weight->id) {
                        if ($key != 0) {
                            $previous_index = $key - 1; 
                        } 
                        if ($key < count($weight) - 1) {
                            $next_index = $key + 1; 
                        }
                        break;
                    }
                }

                if ($next_index != 0) {
                    $weight_gain = $weight[$next_index]->berat_badan - $weight[$previous_index]->berat_badan;
                    $weight[$next_index]->pertambahan_berat = $weight_gain;
                    $weight[$next_index]->save();
                }

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success'),
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.pregnancy_deleted_failed') . ' | ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function weeklyWeightGain(Request $request) {
        # Input Validation
        $validated = $request->validate([
            'berat_badan' => 'required|numeric|between:30,200',
            'minggu_kehamilan' => 'required|integer|between:0,40',
            'tanggal_pencatatan' => 'required|date_format:Y-m-d',
        ]);

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;

        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)
                    ->where('status', 'Hamil')
                    ->first();

        if (!$current_pregnancy) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.pregnancy_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $first_day_last_period = Carbon::parse($current_pregnancy->hari_pertama_haid_terakhir);
        $estimated_due_date = Carbon::parse($current_pregnancy->tanggal_perkiraan_lahir);
        $tanggal_pencatatan = Carbon::parse($request->tanggal_pencatatan);
        
        if ($tanggal_pencatatan->lessThan($first_day_last_period) || $tanggal_pencatatan->greaterThan($estimated_due_date)) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.tanggal_pencatatan_not_in_range_of_pregnancy'),
            ], Response::HTTP_BAD_REQUEST);
        }

        # Return Response
        try {
            DB::beginTransaction();
                $weight_entry_history = BeratIdealIbuHamil::where('user_id', $user_id)
                    ->where('riwayat_kehamilan_id', $current_pregnancy->id)
                    ->orderBy('minggu_kehamilan', 'asc')
                    ->orderBy('tanggal_pencatatan', 'asc')
                    ->get();

                $existing_entry = $weight_entry_history->where('tanggal_pencatatan', $request->tanggal_pencatatan)->first();

                if ($existing_entry) {
                    $existing_entry->berat_badan = $request->berat_badan;
                    $existing_entry->save();

                    $entry_to_create = $existing_entry;
                } else {
                    $entry_to_create = BeratIdealIbuHamil::create([
                        "user_id" => $user_id,
                        "riwayat_kehamilan_id" => $current_pregnancy->id,
                        "berat_badan" => $request->berat_badan,
                        "minggu_kehamilan" => $request->minggu_kehamilan,
                        "tanggal_pencatatan" => $request->tanggal_pencatatan,
                    ]);
                }

                $updated_weight_history = BeratIdealIbuHamil::where('user_id', $user_id)
                        ->orderBy('minggu_kehamilan', 'asc')
                        ->orderBy('tanggal_pencatatan', 'asc')
                        ->get();

                $previous_index = 0;
                $next_index = 0;
                
                foreach ($updated_weight_history as $key => $w) {
                    if ($w->id == $entry_to_create->id) {
                        if ($key != 0) {
                            $previous_index = $key - 1; 
                        } 
                        if ($key < count($updated_weight_history) - 1) {
                            $next_index = $key + 1; 
                        }
                        break;
                    }
                }

                if ($next_index != 0) {
                    $weight_gain = $updated_weight_history[$next_index]->berat_badan - $updated_weight_history[$previous_index + 1]->berat_badan;
                    
                    $updated_weight_history[$next_index]->pertambahan_berat = $weight_gain;
                    $updated_weight_history[$next_index]->save();
                }

                $entry_to_create->pertambahan_berat = $request->berat_badan - $updated_weight_history[$previous_index]->berat_badan;
                $entry_to_create->save();
            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success'),
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.pregnancy_deleted_failed') . ' | ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteWeeklyWeightGain(Request $request) {
        $validated = $request->validate([
            'tanggal_pencatatan' => 'required|date_format:Y-m-d',
        ]);

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $entry_to_delete = BeratIdealIbuHamil::where('user_id', $user_id)->where('tanggal_pencatatan', $request->tanggal_pencatatan)->first();
    
        if (!$entry_to_delete) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.entry_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $weight = BeratIdealIbuHamil::where('user_id', $user_id)
                ->orderBy('minggu_kehamilan', 'asc')
                ->orderBy('tanggal_pencatatan', 'asc')
                ->get();
        
        $weight_deleted = $weight->where('tanggal_pencatatan', $request->tanggal_pencatatan)->first();

        if (!$weight_deleted) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.weight_data_cannot_be_delete'),
            ], Response::HTTP_NOT_FOUND);
        }

        $previous_index = 0;
        $next_index = 0;
        
        foreach ($weight as $key => $w) {
            if ($w->id == $weight_deleted->id) {
                if ($key != 0) {
                    $previous_index = $key - 1; 
                } 
                if ($key < count($weight) - 1) {
                    $next_index = $key + 1; 
                }
                break;
            }
        }

        # Return Response
        try {
            DB::beginTransaction();
                if ($next_index != 0) {
                    $weight[$next_index]->pertambahan_berat = $weight[$next_index]->berat_badan - $weight[$previous_index]->berat_badan;
                    $weight[$next_index]->save();
                }

                $entry_to_delete->delete();

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success'),
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.pregnancy_deleted_failed') . ' | ' . $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function PregnancyWeightGainIndex(Request $request) {
        try {
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)
                    ->where('status', 'Hamil')
                    ->first();
            
            if (!$current_pregnancy) {
                return response()->json([
                    "status" => "error",
                    "message" => __('response.pregnancy_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }
            
            $weight_history = BeratIdealIbuHamil::where('user_id', $user_id)
                    ->where('riwayat_kehamilan_id', $current_pregnancy->id)
                    ->orderBy('minggu_kehamilan', 'DESC')
                    ->orderBy('tanggal_pencatatan', 'DESC')
                    ->get();
    
            $first_weight = $weight_history->first();
            $last_weight = $weight_history->last();
            
            $total_gain = 0;
            if ($first_weight && $last_weight && $first_weight != $last_weight) {
                $total_gain = ($first_weight->berat_badan ?? 0) - ($last_weight->berat_badan ?? 0);
            }

            $hari_pertama_haid_terakhir = $current_pregnancy->hari_pertama_haid_terakhir;
            $usia_kehamilan_sekarang = Carbon::now()->diffInWeeks($hari_pertama_haid_terakhir) + 1;

            $weight_data = [
                'underweight' => [
                    ['week' => 1, 'lower_weight_gain' => 0, 'upper_weight_gain' => 0],
                    ['week' => 2, 'lower_weight_gain' => 0.04, 'upper_weight_gain' => 0.2],
                    ['week' => 3, 'lower_weight_gain' => 0.08, 'upper_weight_gain' => 0.3],
                    ['week' => 4, 'lower_weight_gain' => 0.1, 'upper_weight_gain' => 0.5],
                    ['week' => 5, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.7],
                    ['week' => 6, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.8],
                    ['week' => 7, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 1.0],
                    ['week' => 8, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.2],
                    ['week' => 9, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.3],
                    ['week' => 10, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.5],
                    ['week' => 11, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.7],
                    ['week' => 12, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 1.8],
                    ['week' => 13, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 2.0],
                    ['week' => 14, 'lower_weight_gain' => 1.0, 'upper_weight_gain' => 2.6],
                    ['week' => 15, 'lower_weight_gain' => 1.4, 'upper_weight_gain' => 3.2],
                    ['week' => 16, 'lower_weight_gain' => 1.9, 'upper_weight_gain' => 3.8],
                    ['week' => 17, 'lower_weight_gain' => 2.3, 'upper_weight_gain' => 4.4],
                    ['week' => 18, 'lower_weight_gain' => 2.8, 'upper_weight_gain' => 5.0],
                    ['week' => 19, 'lower_weight_gain' => 3.2, 'upper_weight_gain' => 5.6],
                    ['week' => 20, 'lower_weight_gain' => 3.7, 'upper_weight_gain' => 6.2],
                    ['week' => 21, 'lower_weight_gain' => 4.1, 'upper_weight_gain' => 6.8],
                    ['week' => 22, 'lower_weight_gain' => 4.6, 'upper_weight_gain' => 7.4],
                    ['week' => 23, 'lower_weight_gain' => 5.0, 'upper_weight_gain' => 8.0],
                    ['week' => 24, 'lower_weight_gain' => 5.5, 'upper_weight_gain' => 8.6],
                    ['week' => 25, 'lower_weight_gain' => 5.9, 'upper_weight_gain' => 9.2],
                    ['week' => 26, 'lower_weight_gain' => 6.4, 'upper_weight_gain' => 9.8],
                    ['week' => 27, 'lower_weight_gain' => 6.8, 'upper_weight_gain' => 10.4],
                    ['week' => 28, 'lower_weight_gain' => 7.3, 'upper_weight_gain' => 11.0],
                    ['week' => 29, 'lower_weight_gain' => 7.7, 'upper_weight_gain' => 11.6],
                    ['week' => 30, 'lower_weight_gain' => 8.2, 'upper_weight_gain' => 12.2],
                    ['week' => 31, 'lower_weight_gain' => 8.6, 'upper_weight_gain' => 12.8],
                    ['week' => 32, 'lower_weight_gain' => 9.1, 'upper_weight_gain' => 13.4],
                    ['week' => 33, 'lower_weight_gain' => 9.5, 'upper_weight_gain' => 14.0],
                    ['week' => 34, 'lower_weight_gain' => 10.0, 'upper_weight_gain' => 14.6],
                    ['week' => 35, 'lower_weight_gain' => 10.4, 'upper_weight_gain' => 15.2],
                    ['week' => 36, 'lower_weight_gain' => 10.9, 'upper_weight_gain' => 15.8],
                    ['week' => 37, 'lower_weight_gain' => 11.3, 'upper_weight_gain' => 16.3],
                    ['week' => 38, 'lower_weight_gain' => 11.8, 'upper_weight_gain' => 16.9],
                    ['week' => 39, 'lower_weight_gain' => 12.2, 'upper_weight_gain' => 17.5],
                    ['week' => 40, 'lower_weight_gain' => 12.7, 'upper_weight_gain' => 18.1],
                ],
                'normal' => [
                    ['week' => 1, 'lower_weight_gain' => 0, 'upper_weight_gain' => 0],
                    ['week' => 2, 'lower_weight_gain' => 0.04, 'upper_weight_gain' => 0.2],
                    ['week' => 3, 'lower_weight_gain' => 0.08, 'upper_weight_gain' => 0.3],
                    ['week' => 4, 'lower_weight_gain' => 0.1, 'upper_weight_gain' => 0.5],
                    ['week' => 5, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.7],
                    ['week' => 6, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.8],
                    ['week' => 7, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 1.0],
                    ['week' => 8, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.2],
                    ['week' => 9, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.3],
                    ['week' => 10, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.5],
                    ['week' => 11, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.7],
                    ['week' => 12, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 1.8],
                    ['week' => 13, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 2.0],
                    ['week' => 14, 'lower_weight_gain' => 0.9, 'upper_weight_gain' => 2.5],
                    ['week' => 15, 'lower_weight_gain' => 1.3, 'upper_weight_gain' => 3.0],
                    ['week' => 16, 'lower_weight_gain' => 1.7, 'upper_weight_gain' => 3.5],
                    ['week' => 17, 'lower_weight_gain' => 2.1, 'upper_weight_gain' => 4.1],
                    ['week' => 18, 'lower_weight_gain' => 2.5, 'upper_weight_gain' => 4.6],
                    ['week' => 19, 'lower_weight_gain' => 2.9, 'upper_weight_gain' => 5.1],
                    ['week' => 20, 'lower_weight_gain' => 3.3, 'upper_weight_gain' => 5.6],
                    ['week' => 21, 'lower_weight_gain' => 3.7, 'upper_weight_gain' => 6.1],
                    ['week' => 22, 'lower_weight_gain' => 4.1, 'upper_weight_gain' => 6.6],
                    ['week' => 23, 'lower_weight_gain' => 4.5, 'upper_weight_gain' => 7.1],
                    ['week' => 24, 'lower_weight_gain' => 4.9, 'upper_weight_gain' => 7.7],
                    ['week' => 25, 'lower_weight_gain' => 5.3, 'upper_weight_gain' => 8.2],
                    ['week' => 26, 'lower_weight_gain' => 5.7, 'upper_weight_gain' => 8.7],
                    ['week' => 27, 'lower_weight_gain' => 6.1, 'upper_weight_gain' => 9.2],
                    ['week' => 28, 'lower_weight_gain' => 6.5, 'upper_weight_gain' => 9.7],
                    ['week' => 29, 'lower_weight_gain' => 6.9, 'upper_weight_gain' => 10.2],
                    ['week' => 30, 'lower_weight_gain' => 7.3, 'upper_weight_gain' => 10.7],
                    ['week' => 31, 'lower_weight_gain' => 7.7, 'upper_weight_gain' => 11.2],
                    ['week' => 32, 'lower_weight_gain' => 8.1, 'upper_weight_gain' => 11.8],
                    ['week' => 33, 'lower_weight_gain' => 8.5, 'upper_weight_gain' => 12.3],
                    ['week' => 34, 'lower_weight_gain' => 8.9, 'upper_weight_gain' => 12.8],
                    ['week' => 35, 'lower_weight_gain' => 9.3, 'upper_weight_gain' => 13.3],
                    ['week' => 36, 'lower_weight_gain' => 9.7, 'upper_weight_gain' => 13.8],
                    ['week' => 37, 'lower_weight_gain' => 10.1, 'upper_weight_gain' => 14.3],
                    ['week' => 38, 'lower_weight_gain' => 10.5, 'upper_weight_gain' => 14.8],
                    ['week' => 39, 'lower_weight_gain' => 10.9, 'upper_weight_gain' => 15.4],
                    ['week' => 40, 'lower_weight_gain' => 11.3, 'upper_weight_gain' => 15.9],
                ],
                'overweight' => [
                    ['week' => 1, 'lower_weight_gain' => 0, 'upper_weight_gain' => 0],
                    ['week' => 2, 'lower_weight_gain' => 0.04, 'upper_weight_gain' => 0.2],
                    ['week' => 3, 'lower_weight_gain' => 0.08, 'upper_weight_gain' => 0.3],
                    ['week' => 4, 'lower_weight_gain' => 0.1, 'upper_weight_gain' => 0.5],
                    ['week' => 5, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.7],
                    ['week' => 6, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.8],
                    ['week' => 7, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 1.0],
                    ['week' => 8, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.2],
                    ['week' => 9, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.3],
                    ['week' => 10, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.5],
                    ['week' => 11, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.7],
                    ['week' => 12, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 1.8],
                    ['week' => 13, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 2.0],
                    ['week' => 14, 'lower_weight_gain' => 0.7, 'upper_weight_gain' => 2.3],
                    ['week' => 15, 'lower_weight_gain' => 1.0, 'upper_weight_gain' => 2.7],
                    ['week' => 16, 'lower_weight_gain' => 1.2, 'upper_weight_gain' => 3.0],
                    ['week' => 17, 'lower_weight_gain' => 1.4, 'upper_weight_gain' => 3.4],
                    ['week' => 18, 'lower_weight_gain' => 1.7, 'upper_weight_gain' => 3.7],
                    ['week' => 19, 'lower_weight_gain' => 1.9, 'upper_weight_gain' => 4.1],
                    ['week' => 20, 'lower_weight_gain' => 2.1, 'upper_weight_gain' => 4.4],
                    ['week' => 21, 'lower_weight_gain' => 2.4, 'upper_weight_gain' => 4.8],
                    ['week' => 22, 'lower_weight_gain' => 2.6, 'upper_weight_gain' => 5.1],
                    ['week' => 23, 'lower_weight_gain' => 2.8, 'upper_weight_gain' => 5.5],
                    ['week' => 24, 'lower_weight_gain' => 3.1, 'upper_weight_gain' => 5.8],
                    ['week' => 25, 'lower_weight_gain' => 3.3, 'upper_weight_gain' => 6.1],
                    ['week' => 26, 'lower_weight_gain' => 3.5, 'upper_weight_gain' => 6.5],
                    ['week' => 27, 'lower_weight_gain' => 3.8, 'upper_weight_gain' => 6.8],
                    ['week' => 28, 'lower_weight_gain' => 4.0, 'upper_weight_gain' => 7.2],
                    ['week' => 29, 'lower_weight_gain' => 4.2, 'upper_weight_gain' => 7.5],
                    ['week' => 30, 'lower_weight_gain' => 4.5, 'upper_weight_gain' => 7.9],
                    ['week' => 31, 'lower_weight_gain' => 4.7, 'upper_weight_gain' => 8.2],
                    ['week' => 32, 'lower_weight_gain' => 4.9, 'upper_weight_gain' => 8.6],
                    ['week' => 33, 'lower_weight_gain' => 5.2, 'upper_weight_gain' => 8.9],
                    ['week' => 34, 'lower_weight_gain' => 5.4, 'upper_weight_gain' => 9.3],
                    ['week' => 35, 'lower_weight_gain' => 5.6, 'upper_weight_gain' => 9.6],
                    ['week' => 36, 'lower_weight_gain' => 5.9, 'upper_weight_gain' => 10.0],
                    ['week' => 37, 'lower_weight_gain' => 6.1, 'upper_weight_gain' => 10.3],
                    ['week' => 38, 'lower_weight_gain' => 6.3, 'upper_weight_gain' => 10.6],
                    ['week' => 39, 'lower_weight_gain' => 6.6, 'upper_weight_gain' => 11.0],
                    ['week' => 40, 'lower_weight_gain' => 6.8, 'upper_weight_gain' => 11.3],
                ],
                'obese' => [
                    ['week' => 1, 'lower_weight_gain' => 0, 'upper_weight_gain' => 0],
                    ['week' => 2, 'lower_weight_gain' => 0.04, 'upper_weight_gain' => 0.2],
                    ['week' => 3, 'lower_weight_gain' => 0.08, 'upper_weight_gain' => 0.3],
                    ['week' => 4, 'lower_weight_gain' => 0.1, 'upper_weight_gain' => 0.5],
                    ['week' => 5, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.7],
                    ['week' => 6, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.8],
                    ['week' => 7, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 1.0],
                    ['week' => 8, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.2],
                    ['week' => 9, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.3],
                    ['week' => 10, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.5],
                    ['week' => 11, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.7],
                    ['week' => 12, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 1.8],
                    ['week' => 13, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 2.0],
                    ['week' => 14, 'lower_weight_gain' => 0.7, 'upper_weight_gain' => 2.3],
                    ['week' => 15, 'lower_weight_gain' => 0.8, 'upper_weight_gain' => 2.5],
                    ['week' => 16, 'lower_weight_gain' => 1.0, 'upper_weight_gain' => 2.8],
                    ['week' => 17, 'lower_weight_gain' => 1.2, 'upper_weight_gain' => 3.0],
                    ['week' => 18, 'lower_weight_gain' => 1.3, 'upper_weight_gain' => 3.3],
                    ['week' => 19, 'lower_weight_gain' => 1.5, 'upper_weight_gain' => 3.6],
                    ['week' => 20, 'lower_weight_gain' => 1.7, 'upper_weight_gain' => 3.8],
                    ['week' => 21, 'lower_weight_gain' => 1.8, 'upper_weight_gain' => 4.1],
                    ['week' => 22, 'lower_weight_gain' => 2.0, 'upper_weight_gain' => 4.4],
                    ['week' => 23, 'lower_weight_gain' => 2.2, 'upper_weight_gain' => 4.6],
                    ['week' => 24, 'lower_weight_gain' => 2.3, 'upper_weight_gain' => 4.9],
                    ['week' => 25, 'lower_weight_gain' => 2.5, 'upper_weight_gain' => 5.1],
                    ['week' => 26, 'lower_weight_gain' => 2.7, 'upper_weight_gain' => 5.4],
                    ['week' => 27, 'lower_weight_gain' => 2.8, 'upper_weight_gain' => 5.7],
                    ['week' => 28, 'lower_weight_gain' => 3.0, 'upper_weight_gain' => 5.9],
                    ['week' => 29, 'lower_weight_gain' => 3.2, 'upper_weight_gain' => 6.2],
                    ['week' => 30, 'lower_weight_gain' => 3.3, 'upper_weight_gain' => 6.5],
                    ['week' => 31, 'lower_weight_gain' => 3.5, 'upper_weight_gain' => 6.7],
                    ['week' => 32, 'lower_weight_gain' => 3.7, 'upper_weight_gain' => 7.0],
                    ['week' => 33, 'lower_weight_gain' => 3.8, 'upper_weight_gain' => 7.2],
                    ['week' => 34, 'lower_weight_gain' => 4.0, 'upper_weight_gain' => 7.5],
                    ['week' => 35, 'lower_weight_gain' => 4.2, 'upper_weight_gain' => 7.8],
                    ['week' => 36, 'lower_weight_gain' => 4.3, 'upper_weight_gain' => 8.0],
                    ['week' => 37, 'lower_weight_gain' => 4.5, 'upper_weight_gain' => 8.3],
                    ['week' => 38, 'lower_weight_gain' => 4.7, 'upper_weight_gain' => 8.5],
                    ['week' => 39, 'lower_weight_gain' => 4.8, 'upper_weight_gain' => 8.8],
                    ['week' => 40, 'lower_weight_gain' => 5.0, 'upper_weight_gain' => 9.1],
                ],
                'underweight_twin' => [
                    ['week' => 1, 'lower_weight_gain' => 0, 'upper_weight_gain' => 0],
                    ['week' => 2, 'lower_weight_gain' => 0.04, 'upper_weight_gain' => 0.2],
                    ['week' => 3, 'lower_weight_gain' => 0.08, 'upper_weight_gain' => 0.3],
                    ['week' => 4, 'lower_weight_gain' => 0.1, 'upper_weight_gain' => 0.5],
                    ['week' => 5, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.7],
                    ['week' => 6, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.8],
                    ['week' => 7, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 1.0],
                    ['week' => 8, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.2],
                    ['week' => 9, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.3],
                    ['week' => 10, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.5],
                    ['week' => 11, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.7],
                    ['week' => 12, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 1.8],
                    ['week' => 13, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 2.0],
                    ['week' => 14, 'lower_weight_gain' => 1.1, 'upper_weight_gain' => 2.8],
                    ['week' => 15, 'lower_weight_gain' => 1.7, 'upper_weight_gain' => 3.7],
                    ['week' => 16, 'lower_weight_gain' => 2.3, 'upper_weight_gain' => 4.5],
                    ['week' => 17, 'lower_weight_gain' => 2.9, 'upper_weight_gain' => 5.3],
                    ['week' => 18, 'lower_weight_gain' => 3.5, 'upper_weight_gain' => 6.2],
                    ['week' => 19, 'lower_weight_gain' => 4.1, 'upper_weight_gain' => 7.0],
                    ['week' => 20, 'lower_weight_gain' => 4.7, 'upper_weight_gain' => 7.8],
                    ['week' => 21, 'lower_weight_gain' => 5.3, 'upper_weight_gain' => 8.7],
                    ['week' => 22, 'lower_weight_gain' => 5.9, 'upper_weight_gain' => 9.5],
                    ['week' => 23, 'lower_weight_gain' => 6.5, 'upper_weight_gain' => 10.3],
                    ['week' => 24, 'lower_weight_gain' => 7.1, 'upper_weight_gain' => 11.2],
                    ['week' => 25, 'lower_weight_gain' => 7.7, 'upper_weight_gain' => 12.0],
                    ['week' => 26, 'lower_weight_gain' => 8.3, 'upper_weight_gain' => 12.8],
                    ['week' => 27, 'lower_weight_gain' => 8.9, 'upper_weight_gain' => 13.7],
                    ['week' => 28, 'lower_weight_gain' => 9.5, 'upper_weight_gain' => 14.5],
                    ['week' => 29, 'lower_weight_gain' => 10.1, 'upper_weight_gain' => 15.3],
                    ['week' => 30, 'lower_weight_gain' => 10.8, 'upper_weight_gain' => 16.2],
                    ['week' => 31, 'lower_weight_gain' => 11.4, 'upper_weight_gain' => 17.0],
                    ['week' => 32, 'lower_weight_gain' => 12.0, 'upper_weight_gain' => 17.8],
                    ['week' => 33, 'lower_weight_gain' => 12.6, 'upper_weight_gain' => 18.7],
                    ['week' => 34, 'lower_weight_gain' => 13.2, 'upper_weight_gain' => 19.5],
                    ['week' => 35, 'lower_weight_gain' => 13.8, 'upper_weight_gain' => 20.3],
                    ['week' => 36, 'lower_weight_gain' => 14.4, 'upper_weight_gain' => 21.2],
                    ['week' => 37, 'lower_weight_gain' => 15.0, 'upper_weight_gain' => 22.0],
                    ['week' => 38, 'lower_weight_gain' => 15.6, 'upper_weight_gain' => 22.8],
                    ['week' => 39, 'lower_weight_gain' => 16.2, 'upper_weight_gain' => 23.7],
                    ['week' => 40, 'lower_weight_gain' => 16.8, 'upper_weight_gain' => 24.5],
                ],
                'normal_twin' => [
                    ['week' => 1, 'lower_weight_gain' => 0, 'upper_weight_gain' => 0],
                    ['week' => 2, 'lower_weight_gain' => 0.04, 'upper_weight_gain' => 0.2],
                    ['week' => 3, 'lower_weight_gain' => 0.08, 'upper_weight_gain' => 0.3],
                    ['week' => 4, 'lower_weight_gain' => 0.1, 'upper_weight_gain' => 0.5],
                    ['week' => 5, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.7],
                    ['week' => 6, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.8],
                    ['week' => 7, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 1.0],
                    ['week' => 8, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.2],
                    ['week' => 9, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.3],
                    ['week' => 10, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.5],
                    ['week' => 11, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.7],
                    ['week' => 12, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 1.8],
                    ['week' => 13, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 2.0],
                    ['week' => 14, 'lower_weight_gain' => 1.1, 'upper_weight_gain' => 2.8],
                    ['week' => 15, 'lower_weight_gain' => 1.7, 'upper_weight_gain' => 3.7],
                    ['week' => 16, 'lower_weight_gain' => 2.3, 'upper_weight_gain' => 4.5],
                    ['week' => 17, 'lower_weight_gain' => 2.9, 'upper_weight_gain' => 5.3],
                    ['week' => 18, 'lower_weight_gain' => 3.5, 'upper_weight_gain' => 6.2],
                    ['week' => 19, 'lower_weight_gain' => 4.1, 'upper_weight_gain' => 7.0],
                    ['week' => 20, 'lower_weight_gain' => 4.7, 'upper_weight_gain' => 7.8],
                    ['week' => 21, 'lower_weight_gain' => 5.3, 'upper_weight_gain' => 8.7],
                    ['week' => 22, 'lower_weight_gain' => 5.9, 'upper_weight_gain' => 9.5],
                    ['week' => 23, 'lower_weight_gain' => 6.5, 'upper_weight_gain' => 10.3],
                    ['week' => 24, 'lower_weight_gain' => 7.1, 'upper_weight_gain' => 11.2],
                    ['week' => 25, 'lower_weight_gain' => 7.7, 'upper_weight_gain' => 12.0],
                    ['week' => 26, 'lower_weight_gain' => 8.3, 'upper_weight_gain' => 12.8],
                    ['week' => 27, 'lower_weight_gain' => 8.9, 'upper_weight_gain' => 13.7],
                    ['week' => 28, 'lower_weight_gain' => 9.5, 'upper_weight_gain' => 14.5],
                    ['week' => 29, 'lower_weight_gain' => 10.1, 'upper_weight_gain' => 15.3],
                    ['week' => 30, 'lower_weight_gain' => 10.8, 'upper_weight_gain' => 16.2],
                    ['week' => 31, 'lower_weight_gain' => 11.4, 'upper_weight_gain' => 17.0],
                    ['week' => 32, 'lower_weight_gain' => 12.0, 'upper_weight_gain' => 17.8],
                    ['week' => 33, 'lower_weight_gain' => 12.6, 'upper_weight_gain' => 18.7],
                    ['week' => 34, 'lower_weight_gain' => 13.2, 'upper_weight_gain' => 19.5],
                    ['week' => 35, 'lower_weight_gain' => 13.8, 'upper_weight_gain' => 20.3],
                    ['week' => 36, 'lower_weight_gain' => 14.4, 'upper_weight_gain' => 21.2],
                    ['week' => 37, 'lower_weight_gain' => 15.0, 'upper_weight_gain' => 22.0],
                    ['week' => 38, 'lower_weight_gain' => 15.6, 'upper_weight_gain' => 22.8],
                    ['week' => 39, 'lower_weight_gain' => 16.2, 'upper_weight_gain' => 23.7],
                    ['week' => 40, 'lower_weight_gain' => 16.8, 'upper_weight_gain' => 24.5],
                ],
                'overweight_twin' => [
                    ['week' => 1, 'lower_weight_gain' => 0, 'upper_weight_gain' => 0],
                    ['week' => 2, 'lower_weight_gain' => 0.04, 'upper_weight_gain' => 0.2],
                    ['week' => 3, 'lower_weight_gain' => 0.08, 'upper_weight_gain' => 0.3],
                    ['week' => 4, 'lower_weight_gain' => 0.1, 'upper_weight_gain' => 0.5],
                    ['week' => 5, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.7],
                    ['week' => 6, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.8],
                    ['week' => 7, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 1.0],
                    ['week' => 8, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.2],
                    ['week' => 9, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.3],
                    ['week' => 10, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.5],
                    ['week' => 11, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.7],
                    ['week' => 12, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 1.8],
                    ['week' => 13, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 2.0],
                    ['week' => 14, 'lower_weight_gain' => 1.0, 'upper_weight_gain' => 2.8],
                    ['week' => 15, 'lower_weight_gain' => 1.5, 'upper_weight_gain' => 3.5],
                    ['week' => 16, 'lower_weight_gain' => 2.0, 'upper_weight_gain' => 4.3],
                    ['week' => 17, 'lower_weight_gain' => 2.5, 'upper_weight_gain' => 5.1],
                    ['week' => 18, 'lower_weight_gain' => 3.0, 'upper_weight_gain' => 5.8],
                    ['week' => 19, 'lower_weight_gain' => 3.5, 'upper_weight_gain' => 6.6],
                    ['week' => 20, 'lower_weight_gain' => 4.0, 'upper_weight_gain' => 7.4],
                    ['week' => 21, 'lower_weight_gain' => 4.5, 'upper_weight_gain' => 8.1],
                    ['week' => 22, 'lower_weight_gain' => 5.0, 'upper_weight_gain' => 8.9],
                    ['week' => 23, 'lower_weight_gain' => 5.5, 'upper_weight_gain' => 9.7],
                    ['week' => 24, 'lower_weight_gain' => 6.0, 'upper_weight_gain' => 10.4],
                    ['week' => 25, 'lower_weight_gain' => 6.5, 'upper_weight_gain' => 11.2],
                    ['week' => 26, 'lower_weight_gain' => 7.0, 'upper_weight_gain' => 12.0],
                    ['week' => 27, 'lower_weight_gain' => 7.5, 'upper_weight_gain' => 12.7],
                    ['week' => 28, 'lower_weight_gain' => 8.0, 'upper_weight_gain' => 13.5],
                    ['week' => 29, 'lower_weight_gain' => 8.5, 'upper_weight_gain' => 14.3],
                    ['week' => 30, 'lower_weight_gain' => 9.0, 'upper_weight_gain' => 15.0],
                    ['week' => 31, 'lower_weight_gain' => 9.5, 'upper_weight_gain' => 15.8],
                    ['week' => 32, 'lower_weight_gain' => 10.0, 'upper_weight_gain' => 16.6],
                    ['week' => 33, 'lower_weight_gain' => 10.5, 'upper_weight_gain' => 17.3],
                    ['week' => 34, 'lower_weight_gain' => 11.0, 'upper_weight_gain' => 18.1],
                    ['week' => 35, 'lower_weight_gain' => 11.5, 'upper_weight_gain' => 18.8],
                    ['week' => 36, 'lower_weight_gain' => 12.1, 'upper_weight_gain' => 19.6],
                    ['week' => 37, 'lower_weight_gain' => 12.6, 'upper_weight_gain' => 20.4],
                    ['week' => 38, 'lower_weight_gain' => 13.1, 'upper_weight_gain' => 21.1],
                    ['week' => 39, 'lower_weight_gain' => 13.6, 'upper_weight_gain' => 21.9],
                    ['week' => 40, 'lower_weight_gain' => 14.1, 'upper_weight_gain' => 22.7],
                ],
                'obese_twin' => [
                    ['week' => 1, 'lower_weight_gain' => 0, 'upper_weight_gain' => 0],
                    ['week' => 2, 'lower_weight_gain' => 0.04, 'upper_weight_gain' => 0.2],
                    ['week' => 3, 'lower_weight_gain' => 0.08, 'upper_weight_gain' => 0.3],
                    ['week' => 4, 'lower_weight_gain' => 0.1, 'upper_weight_gain' => 0.5],
                    ['week' => 5, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.7],
                    ['week' => 6, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 0.8],
                    ['week' => 7, 'lower_weight_gain' => 0.2, 'upper_weight_gain' => 1.0],
                    ['week' => 8, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.2],
                    ['week' => 9, 'lower_weight_gain' => 0.3, 'upper_weight_gain' => 1.3],
                    ['week' => 10, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.5],
                    ['week' => 11, 'lower_weight_gain' => 0.4, 'upper_weight_gain' => 1.7],
                    ['week' => 12, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 1.8],
                    ['week' => 13, 'lower_weight_gain' => 0.5, 'upper_weight_gain' => 2.0],
                    ['week' => 14, 'lower_weight_gain' => 0.9, 'upper_weight_gain' => 2.6],
                    ['week' => 15, 'lower_weight_gain' => 1.3, 'upper_weight_gain' => 3.3],
                    ['week' => 16, 'lower_weight_gain' => 1.7, 'upper_weight_gain' => 3.9],
                    ['week' => 17, 'lower_weight_gain' => 2.1, 'upper_weight_gain' => 4.5],
                    ['week' => 18, 'lower_weight_gain' => 2.5, 'upper_weight_gain' => 5.2],
                    ['week' => 19, 'lower_weight_gain' => 2.9, 'upper_weight_gain' => 5.8],
                    ['week' => 20, 'lower_weight_gain' => 3.3, 'upper_weight_gain' => 6.4],
                    ['week' => 21, 'lower_weight_gain' => 3.7, 'upper_weight_gain' => 7.0],
                    ['week' => 22, 'lower_weight_gain' => 4.1, 'upper_weight_gain' => 7.7],
                    ['week' => 23, 'lower_weight_gain' => 4.5, 'upper_weight_gain' => 8.3],
                    ['week' => 24, 'lower_weight_gain' => 4.9, 'upper_weight_gain' => 8.9],
                    ['week' => 25, 'lower_weight_gain' => 5.3, 'upper_weight_gain' => 9.6],
                    ['week' => 26, 'lower_weight_gain' => 5.7, 'upper_weight_gain' => 10.2],
                    ['week' => 27, 'lower_weight_gain' => 6.1, 'upper_weight_gain' => 10.8],
                    ['week' => 28, 'lower_weight_gain' => 6.5, 'upper_weight_gain' => 11.5],
                    ['week' => 29, 'lower_weight_gain' => 6.9, 'upper_weight_gain' => 12.1],
                    ['week' => 30, 'lower_weight_gain' => 7.3, 'upper_weight_gain' => 12.7],
                    ['week' => 31, 'lower_weight_gain' => 7.7, 'upper_weight_gain' => 13.4],
                    ['week' => 32, 'lower_weight_gain' => 8.1, 'upper_weight_gain' => 14.0],
                    ['week' => 33, 'lower_weight_gain' => 8.5, 'upper_weight_gain' => 14.6],
                    ['week' => 34, 'lower_weight_gain' => 8.9, 'upper_weight_gain' => 15.3],
                    ['week' => 35, 'lower_weight_gain' => 9.3, 'upper_weight_gain' => 15.9],
                    ['week' => 36, 'lower_weight_gain' => 9.7, 'upper_weight_gain' => 16.5],
                    ['week' => 37, 'lower_weight_gain' => 10.1, 'upper_weight_gain' => 17.2],
                    ['week' => 38, 'lower_weight_gain' => 10.5, 'upper_weight_gain' => 17.8],
                    ['week' => 39, 'lower_weight_gain' => 10.9, 'upper_weight_gain' => 18.4],
                    ['week' => 40, 'lower_weight_gain' => 11.3, 'upper_weight_gain' => 19.1],
                ],
            ];

            
            $bmi_category = $current_pregnancy->kategori_bmi ?? '';
            if ($current_pregnancy->is_twin == 1) {
                $bmi_category .= '_twin';
            }
            $weight_gain_data = $weight_data[$bmi_category] ?? [];

            foreach ($weight_gain_data as &$data) {
                $data['reccomend_weight_lower'] = ($data['lower_weight_gain'] ?? 0) + ($last_weight->berat_badan ?? 0);
                $data['reccomend_weight_upper'] = ($data['upper_weight_gain'] ?? 0) + ($last_weight->berat_badan ?? 0);
            }

            $current_weight = $first_weight->berat_badan ?? 0;

            $current_week_data  = $weight_gain_data[$usia_kehamilan_sekarang - 1] ?? [];
            $next_week_data  = $weight_gain_data[($usia_kehamilan_sekarang + 1) - 1] ?? [];

            $current_week_recommend_weight_lower = ($current_week_data['lower_weight_gain'] ?? 0) + ($current_pregnancy->berat_prakehamilan ?? 0);
            $current_week_recommend_weight_upper = ($current_week_data['upper_weight_gain'] ?? 0) + ($current_pregnancy->berat_prakehamilan ?? 0);

            $next_week_recommend_weight_lower = ($next_week_data['lower_weight_gain'] ?? 0) + ($current_pregnancy->berat_prakehamilan ?? 0);
            $next_week_recommend_weight_upper = ($next_week_data['upper_weight_gain'] ?? 0) + ($current_pregnancy->berat_prakehamilan ?? 0);

            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => [
                    "week" => $usia_kehamilan_sekarang,
                    "prepregnancy_weight" => $current_pregnancy->berat_prakehamilan ?? null,
                    "current_weight" => $current_weight ?? null,
                    "total_gain" => round($total_gain, 1) ?? null,
                    "prepregnancy_bmi" => $current_pregnancy->bmi_prakehamilan ?? null,
                    "prepregnancy_height" => $current_pregnancy->tinggi_badan ?? null,
                    "bmi_category" => $current_pregnancy->kategori_bmi ?? null,
                    "is_twin" => $current_pregnancy->is_twin ?? null,
                    "current_week_reccomend_weight" => "$current_week_recommend_weight_lower - $current_week_recommend_weight_upper" ?? null,
                    "next_week_reccomend_weight" => "$next_week_recommend_weight_lower - $next_week_recommend_weight_upper" ?? null,
                    "weight_history" => $weight_history ?? null,
                    "reccomend_weight_gain" => $weight_gain_data
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}