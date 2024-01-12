<?php

namespace App\Http\Controllers\Engine;

use Carbon\Carbon;
use App\Models\Login;
use App\Models\RiwayatMens;
use App\Models\MasterGender;
use Illuminate\Http\Request;
use App\Models\MasterKehamilan;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\UToken;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| Menstruation Controller
|--------------------------------------------------------------------------
| 
| Calcucation with & without saving in database
| 
| Created by I Gede Hadi Darmawan
| MCA Final Project 2021
|
*/

class PeriodController extends Controller
{
    public function storePeriod(Request $request)
    {
        # Input Validation
        $rules = [
            'periods.*.first_period' => 'required|date',
            'periods.*.last_period' => 'required|date'
        ];
        $messages = [];
        $attributes = [
            'periods.*.first_period' => __('attribute.first_period'),
            'periods.*.last_period' => __('attribute.last_period')
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        # Start & End Period from Input
        $periods = $request->input('periods');

        if (is_null($periods)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid data format. periods is null.',
            ], 400);
        }

        # Mengurutkan array berdasarkan tanggal terlama ke terbaru
        usort($periods, function ($a, $b) {
            return strtotime($a['first_period']) - strtotime($b['first_period']);
        });

        if ($request->header('user_id') == null) {
            $email_regis = $request->input('email_regis');
            $user = Login::where('email', $email_regis)->first();
            $user_id = $user->id;
        } else {
            $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');
            $user = Login::where('id', $user_id)->first();
        }
        
        if ($user) {
            $age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->age;
            $lunar_age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->addMonth(9)->age;
        } else {
            // Handle the case when $user is null, e.g., return an error response.
            return response()->json([
                "status" => "failed",
                "message" => __('response.user_not_found'),
            ], 404);
        }

        for ($i = 0; $i < count($periods); $i++) {
            # Start & End Period from Input
            $period_start = date("Y-m-d", strtotime($periods[$i]['first_period']));
            $period_end = date("Y-m-d", strtotime($periods[$i]['last_period']));

            # Period Validation When Period History is Empty
            $period_history = RiwayatMens::where('user_id', $user_id)->get();
            if (count($period_history) < 1) {
                $period_cycle_rules = [
                    "period_cycle" => "required|regex:/^[0-9]+$/i|max:2",
                ];
                $period_cycle_messages = [];
                $period_cycle_attributes = [
                    'period_cycle' => __('attribute.period_cycle'),
                ];
                $period_cycle_validator = Validator::make($request->all(), $period_cycle_rules, $period_cycle_messages, $period_cycle_attributes);
                if ($period_cycle_validator->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $period_cycle_validator->errors()
                    ], 400);
                }
            } else {
                $last_period_start = $period_history
                    ->where('is_actual', '1')
                    ->where('haid_awal', '<=', $request->input('periods.0.first_period'))
                    ->max('haid_awal');

                $next_period_start = $period_history
                    ->where('is_actual', '1')
                    ->where('haid_awal', '>=', $request->input('periods.0.first_period'))
                    ->min('haid_awal');

                $period_start = Carbon::parse($request->input('periods.0.first_period'));

                if (!empty($last_period_start)) {
                    $period_gaps = $period_start->diffInDays(Carbon::parse($last_period_start));
                
                    if ($period_gaps < 20) {
                        return response()->json([
                            "status" => "failed",
                            "message" => __('response.period_too_fast'),
                        ], 400);
                    }
                }
            
                if (!empty($next_period_start)) {
                    $period_gaps = $period_start->diffInDays(Carbon::parse($next_period_start));
                
                    if ($period_gaps < 20) {
                        return response()->json([
                            "status" => "failed",
                            "message" => __('response.period_too_fast'),
                        ], 400);
                    }
                }
            }

            # Period Duration from Input
            $period_duration = Carbon::parse($period_end)->diffInDays(Carbon::parse($period_start)) + 1;

            # Check Period History when Empty
            if ($period_history->count() > 0) {
                # Check Period Before when Empty
                $period_before = RiwayatMens::where('user_id', $user_id)
                    ->where('haid_awal', '<', Carbon::parse($period_start)->toDateString())
                    ->orderBy('haid_awal', 'DESC')
                    ->first();

                if ($period_before == NULL) {
                    return response()->json([
                        "status" => "failed",
                        "message" => __('response.add_period_failed'),
                    ], 400);

                } else {
                    $period_cycle = Carbon::parse($period_before->haid_awal)->diffInDays(Carbon::parse($period_start));
                    $avg_period_cycle = ceil(($period_history->sum('lama_siklus')+$period_cycle) / ($period_history->count()+1));
                }
                
                $siklus_tengah_haid = ceil($avg_period_cycle / 2);

                $existingPrediction = RiwayatMens::where('user_id', $user_id)
                    ->where('haid_awal', '>=', Carbon::parse($period_start)->subDays(20))
                    ->where('haid_awal', '<=', Carbon::parse($period_start)->addDays(20))
                    ->where('is_actual', '0')
                    ->first();
    
                if ($existingPrediction) {
                    // Ada prediksi yang tumpang tindih, hapus prediksi tersebut
                    $existingPrediction->delete();
                }
            } else {
                $period_cycle = $request->period_cycle;
                $avg_period_cycle = $request->period_cycle;
                $siklus_tengah_haid = ceil($request->period_cycle / 2);
            }

            # Ovulasi
            $ovulasi = Carbon::parse($period_start)->addDays($siklus_tengah_haid)->toDateString();

            # Masa Subur Awal
            $masa_subur_awal = Carbon::parse($ovulasi)->subDays(5)->toDateString();

            # Masa Subur Akhir
            $masa_subur_akhir = Carbon::parse($ovulasi)->addDays(2)->toDateString();

            # Next Period End
            $next_period_start = Carbon::parse($period_start)->addDays($avg_period_cycle)->toDateString();

            # Next Period End
            $next_period_end = Carbon::parse($period_start)->addDays($avg_period_cycle + $period_duration)->toDateString();

            # Next Ovulasi
            $ovulasi_berikutnya = Carbon::parse($next_period_start)->addDays($siklus_tengah_haid)->toDateString();

            # Return Response
            try {
                DB::beginTransaction();
                    $period_history = RiwayatMens::create([
                        'user_id' => $user_id,
                        'usia' => $age,
                        'usia_lunar' => $lunar_age,
                        'haid_awal' => $period_start,
                        'haid_akhir' => $period_end,
                        'ovulasi' => $ovulasi,
                        'masa_subur_awal' => $masa_subur_awal,
                        'masa_subur_akhir' => $masa_subur_akhir,
                        'lama_siklus' => $period_cycle,
                        'durasi_haid' => $period_duration,
                        'haid_berikutnya_awal' => $next_period_start,
                        'haid_berikutnya_akhir' => $next_period_end,
                        'ovulasi_berikutnya' => $ovulasi_berikutnya,
                        'masa_subur_berikutnya_awal' => Carbon::parse($ovulasi_berikutnya)->subDays(5)->toDateString(),
                        'masa_subur_berikutnya_akhir' => Carbon::parse($ovulasi_berikutnya)->addDays(2)->toDateString(),
                        'is_actual' => "1"
                    ]);

                    if ($i === (count($periods) - 1)) {
                        $actualPeriod = RiwayatMens::find($period_history->id);
                    
                        // Ambil 3 prediksi berikutnya setelah data aktual, urutkan berdasarkan tanggal awal haid secara ascending
                        $nextPredictions = RiwayatMens::where('user_id', $user_id)
                            ->where('haid_awal', '>', $actualPeriod->haid_awal)
                            ->orderBy('haid_awal', 'ASC')
                            ->take(3)
                            ->get();

                        $nonActualCount = 0;

                        $isAllPrediction = false;

                        if (count($nextPredictions) < 3) {
                            foreach ($nextPredictions as $nextPrediction) {
                                if ($nextPrediction->is_actual == 0) {
                                    $isAllPrediction = true;
                                    $nonActualCount++;
                                    $nextPrediction->delete();
                                } else {
                                    $isAllPrediction = false;
                                    break;
                                }
                            }
                        } else {
                            foreach ($nextPredictions as $nextPrediction) {
                                if ($nextPrediction->is_actual == 0) {
                                    $nextPrediction->delete();
                                    $nonActualCount++;
                                } else {
                                    break;
                                }
                            }
                        }

                        if ($nextPredictions->count() == 0 || $isAllPrediction == true) {
                            $actualCount = 3;
                        } else {
                            $actualCount = $nonActualCount;
                        }
                    
                        $this->generateStorePredictionPeriod($user_id, $period_history, $actualCount);
                    }
                    
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollback();
                $errors[] = $th->getMessage();
            }
        }

        if (!empty($errors)) {
            return response()->json([
                "status" => "failed",
                "message" => $errors,
            ], 400);
        } else {
            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success'),
            ], 200);
        }
    }

    public function generateStorePredictionPeriod($user_id, $storedPeriod, $actualCount) 
    {
        $period_start = $storedPeriod->haid_berikutnya_awal;

        for ($i = 0; $i < $actualCount; $i++) {
            $period_history = RiwayatMens::where('user_id', $user_id)->get();
            $user = Login::where('id', $user_id)->first();
            if ($user) {
                $age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->age;
                $lunar_age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->addMonth(9)->age;
            } else {
                // Handle the case when $user is null, e.g., return an error response.
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.user_not_found'),
                ], 404);
            }

            $avg_period_duration = ceil(($period_history->sum('durasi_haid')) / ($period_history->count()));
            $avg_period_cycle = ceil(($period_history->sum('lama_siklus')) / ($period_history->count()));
            $siklus_tengah_haid = ceil($avg_period_cycle / 2);

            // Calculate dates based on the previous period data
            $ovulasi = Carbon::parse($period_start)->addDays($siklus_tengah_haid)->toDateString();
            $masa_subur_awal = Carbon::parse($ovulasi)->subDays(5)->toDateString();
            $masa_subur_akhir = Carbon::parse($ovulasi)->addDays(2)->toDateString();

            // Calculate dates for the next period
            $next_period_start = Carbon::parse($period_start)->addDays($avg_period_cycle)->toDateString();
            $next_period_end = Carbon::parse($period_start)->addDays($avg_period_cycle + $avg_period_duration)->toDateString();
            $ovulasi_berikutnya = Carbon::parse($next_period_start)->addDays($siklus_tengah_haid)->toDateString();
            $masa_subur_berikutnya_awal = Carbon::parse($ovulasi_berikutnya)->subDays(5)->toDateString();
            $masa_subur_berikutnya_akhir = Carbon::parse($ovulasi_berikutnya)->addDays(2)->toDateString();

            $generatedPrediction = [
                'user_id' => $user_id,
                'usia' => $age,
                'usia_lunar' => $lunar_age,
                'haid_awal' => $period_start,
                'haid_akhir' => $storedPeriod->haid_berikutnya_akhir,
                'ovulasi' => $ovulasi,
                'masa_subur_awal' => $masa_subur_awal,
                'masa_subur_akhir' => $masa_subur_akhir,
                'lama_siklus' => $avg_period_cycle,
                'durasi_haid' => $avg_period_duration,
                'haid_berikutnya_awal' => $next_period_start,
                'haid_berikutnya_akhir' => $next_period_end,
                'ovulasi_berikutnya' => $ovulasi_berikutnya,
                'masa_subur_berikutnya_awal' => $masa_subur_berikutnya_awal,
                'masa_subur_berikutnya_akhir' => $masa_subur_berikutnya_akhir,
                'is_actual' => "0"
            ];

            // Store the additional data
            $storedPeriod = RiwayatMens::create($generatedPrediction);

            // Update variables for the next iteration if needed
            $period_start = $storedPeriod->haid_berikutnya_awal;
        }    
    }

    public function updatePeriod(Request $request)
    {
        # Input Validation
        $rules = [
            "period_id" => "required",
            "first_period" => "required|date",
            "last_period" => "required|date"
        ];
        $messages = [];
        $attributes = [
            'period_id' => __('attribute.period_id'),
            'first_period' => __('attribute.first_period'),
            'last_period' => __('attribute.last_period')
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');
        # Get User Age from User Birthday
        $user = Login::where('id', $user_id)->first();
        $age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->age;
        $lunar_age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->addMonth(9)->age;
        
        # Start & End Period from Input
        $period_start = date("Y-m-d", strtotime($request->first_period));
        $period_end = date("Y-m-d", strtotime($request->last_period));
        
        # Period Duration from Input
        $period_duration = Carbon::parse($period_end)->diffInDays(Carbon::parse($period_start)) + 1;

        # Period Validation When Period History is Empty
        $period_history = RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'ASC')->get();
        if (count($period_history) < 1) {
            $period_cycle_rules = [
                "period_cycle" => "required|regex:/^[0-9]+$/i|max:2",
            ];
            $period_cycle_messages = [];
            $period_cycle_attributes = [
                'period_cycle' => __('attribute.period_cycle'),
            ];
            $period_cycle_validator = Validator::make($request->all(), $period_cycle_rules, $period_cycle_messages, $period_cycle_attributes);
            if ($period_cycle_validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $period_cycle_validator->errors()
                ], 400);
            }
        } else {
            $last_period = RiwayatMens::where('user_id', $user_id)
                            ->where('id', '<', $request->period_id)
                            ->where('haid_awal', '<', Carbon::parse($period_start)->toDateString())
                            ->where('is_actual', '1')
                            ->orderBy('haid_awal', 'DESC')
                            ->first();

            $next_period = RiwayatMens::where('user_id', $user_id)
                            ->where('id', '>', $request->period_id)
                            ->where('haid_awal', '>', Carbon::parse($period_start)->toDateString())
                            ->where('is_actual', '1')
                            ->orderBy('haid_awal', 'DESC')
                            ->first();

            
            
            if (!empty($last_period)) {
                $last_period_start = $last_period->haid_awal;
                $period_gaps = Carbon::parse($last_period_start)->diffInDays(Carbon::parse($period_start));
            
                if ($period_gaps < 20) {
                    return response()->json([
                        "status" => "failed",
                        "message" => __('response.period_too_fast'),
                    ], 400);
                }
            }

            if (!empty($next_period)) {
                $next_period_start = $next_period->haid_awal;
                $period_gaps = Carbon::parse($next_period_start)->diffInDays(Carbon::parse($period_start));
            
                if ($period_gaps < 20) {
                    return response()->json([
                        "status" => "failed",
                        "message" => __('response.period_too_fast'),
                    ], 400);
                }
            }
        }

        # Check Period History when Empty
        if (count($period_history) > 0) {
            # Check Period Before when Empty
            $period_before = RiwayatMens::where('user_id', $user_id)
                ->where('haid_awal', '<', Carbon::parse($period_start)->toDateString())
                ->orderBy('haid_awal', 'DESC')
                ->first();
        
            if ($period_before == NULL) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.add_period_failed'),
                ], 400);
            } else {
                $period_cycle = Carbon::parse($period_before->haid_awal)->diffInDays(Carbon::parse($period_start));
                $avg_period_cycle = ceil(($period_history->sum('lama_siklus')+$period_cycle) / ($period_history->count()+1));
                
                $siklus_tengah_haid = ceil($avg_period_cycle / 2);
        
                # Ovulasi
                $ovulasi = Carbon::parse($period_start)->addDays($siklus_tengah_haid)->toDateString();
        
                # Masa Subur Awal
                $masa_subur_awal = Carbon::parse($ovulasi)->subDays(5)->toDateString();
        
                # Masa Subur Akhir
                $masa_subur_akhir = Carbon::parse($ovulasi)->addDays(2)->toDateString();
        
                # Next Period End
                $next_period_start = Carbon::parse($period_start)->addDays($avg_period_cycle)->toDateString();
        
                # Next Period End
                $next_period_end = Carbon::parse($period_start)->addDays($avg_period_cycle + $period_duration)->toDateString();
        
                # Next Ovulasi
                $ovulasi_berikutnya = Carbon::parse($next_period_start)->addDays($siklus_tengah_haid)->toDateString();
        
            }
        } else {
            $period_cycle = $request->period_cycle;
            $avg_period_cycle = $request->period_cycle;
            $siklus_tengah_haid = ceil($request->period_cycle / 2);
            
            # Ovulasi
            $ovulasi = Carbon::parse($period_start)->addDays($siklus_tengah_haid)->toDateString();
        
            # Masa Subur Awal
            $masa_subur_awal = Carbon::parse($ovulasi)->subDays(5)->toDateString();
        
            # Masa Subur Akhir
            $masa_subur_akhir = Carbon::parse($ovulasi)->addDays(2)->toDateString();
        
            # Next Period End
            $next_period_start = Carbon::parse($period_start)->addDays($avg_period_cycle)->toDateString();
        
            # Next Period End
            $next_period_end = Carbon::parse($period_start)->addDays($avg_period_cycle + $period_duration)->toDateString();
        
            # Next Ovulasi
            $ovulasi_berikutnya = Carbon::parse($next_period_start)->addDays($siklus_tengah_haid)->toDateString();
        }

        # Return Response
        try {
            DB::beginTransaction();
                $period_update = RiwayatMens::where('id', $request->period_id)->update([
                    'user_id' => $user_id,
                    'usia' => $age,
                    'usia_lunar' => $lunar_age,
                    'haid_awal' => $period_start,
                    'haid_akhir' => $period_end,
                    'ovulasi' => $ovulasi,
                    'masa_subur_awal' => $masa_subur_awal,
                    'masa_subur_akhir' => $masa_subur_akhir,
                    'lama_siklus' => $period_cycle,
                    'durasi_haid' => $period_duration,
                    'haid_berikutnya_awal' => $next_period_start,
                    'haid_berikutnya_akhir' => $next_period_end,
                    'ovulasi_berikutnya' => $ovulasi_berikutnya,
                    'masa_subur_berikutnya_awal' => Carbon::parse($ovulasi_berikutnya)->subDays(5)->toDateString(),
                    'masa_subur_berikutnya_akhir' => Carbon::parse($ovulasi_berikutnya)->addDays(2)->toDateString(),
                    'is_actual' => "1"
                ]);

                $actualPeriod = RiwayatMens::find($request->period_id);

                $nextPredictions = RiwayatMens::where('user_id', $user_id)
                    ->where('haid_awal', '>', $actualPeriod->haid_awal)
                    ->orderBy('haid_awal', 'ASC')
                    ->take(3)
                    ->get();
                
                $isAllPrediction = false;
                $nonActualCount = 0;

                if (count($nextPredictions) < 3) {
                    foreach ($nextPredictions as $nextPrediction) {
                        if ($nextPrediction->is_actual == 0) {
                            $isAllPrediction = true;
                            $nonActualCount++;
                            $nextPrediction->delete();
                        } else {
                            $isAllPrediction = false;
                            break;
                        }
                    }
                } else {
                    foreach ($nextPredictions as $nextPrediction) {
                        if ($nextPrediction->is_actual == 0) {
                            $nextPrediction->delete();
                            $nonActualCount++;
                        } else {
                            break;
                        }
                    }
                }

                if ($nextPredictions->count() == 0 || $isAllPrediction == true) {
                    $actualCount = 3;
                } else {
                    $actualCount = $nonActualCount;
                }

                $this->generateStorePredictionPeriod($user_id, $actualPeriod, $actualCount);

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.update_success'),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => "Updated Data Failed".' | '.$th->getMessage()
            ], 400);
        }
    }

    public function generateEditPredictionPeriod($user_id) 
    {
        $period_history = RiwayatMens::where('user_id', $user_id)->get();
        $last_prediction = RiwayatMens::where('user_id', $user_id)
            ->where('is_actual', "0")
            ->orderByDesc('haid_awal')
            ->first();

        $period_start = $last_prediction->haid_berikutnya_awal;

        $user = Login::where('id', $user_id)->first();
        if ($user) {
            $age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->age;
            $lunar_age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->addMonth(9)->age;
        } else {
            // Handle the case when $user is null, e.g., return an error response.
            return response()->json([
                "status" => "failed",
                "message" => __('response.user_not_found'),
            ], 404);
        }

        $avg_period_duration = ceil(($period_history->sum('durasi_haid')) / ($period_history->count()));
        $avg_period_cycle = ceil(($period_history->sum('lama_siklus')) / ($period_history->count()));
        $siklus_tengah_haid = ceil($avg_period_cycle / 2);

        // Calculate dates based on the previous period data
        $ovulasi = Carbon::parse($period_start)->addDays($siklus_tengah_haid)->toDateString();
        $masa_subur_awal = Carbon::parse($ovulasi)->subDays(5)->toDateString();
        $masa_subur_akhir = Carbon::parse($ovulasi)->addDays(2)->toDateString();

        // Calculate dates for the next period
        $next_period_start = Carbon::parse($period_start)->addDays($avg_period_cycle)->toDateString();
        $next_period_end = Carbon::parse($period_start)->addDays($avg_period_cycle + $avg_period_duration)->toDateString();
        $ovulasi_berikutnya = Carbon::parse($next_period_start)->addDays($siklus_tengah_haid)->toDateString();
        $masa_subur_berikutnya_awal = Carbon::parse($ovulasi_berikutnya)->subDays(5)->toDateString();
        $masa_subur_berikutnya_akhir = Carbon::parse($ovulasi_berikutnya)->addDays(2)->toDateString();

        $generatedPrediction = [
            'user_id' => $user_id,
            'usia' => $age,
            'usia_lunar' => $lunar_age,
            'haid_awal' => $period_start,
            'haid_akhir' => $last_prediction->haid_berikutnya_akhir,
            'ovulasi' => $ovulasi,
            'masa_subur_awal' => $masa_subur_awal,
            'masa_subur_akhir' => $masa_subur_akhir,
            'lama_siklus' => $avg_period_cycle,
            'durasi_haid' => $avg_period_duration,
            'haid_berikutnya_awal' => $next_period_start,
            'haid_berikutnya_akhir' => $next_period_end,
            'ovulasi_berikutnya' => $ovulasi_berikutnya,
            'masa_subur_berikutnya_awal' => $masa_subur_berikutnya_awal,
            'masa_subur_berikutnya_akhir' => $masa_subur_berikutnya_akhir,
            'is_actual' => "0"
        ];

        // Store the additional data
        RiwayatMens::create($generatedPrediction);
    }

    public function storePrediction(Request $request)
    {
        # Input Validation
        $rules = [
            "latest_period_id" => "required"
        ];
        $messages = [];
        $attributes = [
            'latest_period_id' => "Latest Period"
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');

        # Get User Age from User Birthday
        $user = Login::where('id', $user_id)->first();
        
        if ($user) {
            $age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->age;
            $lunar_age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->addMonth(9)->age;
        } else {
            // Handle the case when $user is null, e.g., return an error response.
            return response()->json([
                "status" => "failed",
                "message" => __('response.user_not_found'),
            ], 404);
        }

        # Latest Period
        $period = RiwayatMens::where('id', $request->latest_period_id)->first();

        # Period Cycle & Average Period Cycle
        $period_history = RiwayatMens::where('user_id', $user_id)->get();
        $period_cycle = Carbon::parse($period->haid_awal)->diffInDays(Carbon::parse($period->haid_berikutnya_awal));
        $avg_period_cycle = ($period_history->sum('lama_siklus')+$period_cycle) / ($period_history->count()+1);

        # Period Duration
        $period_duration = Carbon::parse($period->haid_berikutnya_awal)->diffInDays(Carbon::parse($period->haid_berikutnya_akhir)) + 1;

        # Siklus Tengah Haid
        $siklus_tengah_haid = ceil($avg_period_cycle / 2);

        # Next Period End
        $next_period_start = Carbon::parse($period->haid_berikutnya_awal)->addDays($avg_period_cycle)->toDateString();

        # Next Period End
        $next_period_end = Carbon::parse($period->haid_berikutnya_awal)->addDays($avg_period_cycle + $period_duration)->toDateString();

        # Next Ovulasi
        $ovulasi_berikutnya = Carbon::parse($next_period_start)->addDays($siklus_tengah_haid)->toDateString();

        # Return Response
        try {
            DB::beginTransaction();
                $period_history = RiwayatMens::create([
                    'user_id' => $user_id,
                    'usia' => $age,
                    'usia_lunar' => $lunar_age,
                    'haid_awal' => date("Y-m-d", strtotime($period->haid_berikutnya_awal)),
                    'haid_akhir' => date("Y-m-d", strtotime($period->haid_berikutnya_akhir)),
                    'ovulasi' => date("Y-m-d", strtotime($period->ovulasi_berikutnya)),
                    'masa_subur_awal' => date("Y-m-d", strtotime($period->masa_subur_berikutnya_awal)),
                    'masa_subur_akhir' => date("Y-m-d", strtotime($period->masa_subur_berikutnya_akhir)),
                    'lama_siklus' => $period_cycle,
                    'durasi_haid' => $period_duration,
                    'haid_berikutnya_awal' => $next_period_start,
                    'haid_berikutnya_akhir' => $next_period_end,
                    'ovulasi_berikutnya' => $ovulasi_berikutnya,
                    'masa_subur_berikutnya_awal' => Carbon::parse($ovulasi_berikutnya)->subDays(5)->toDateString(),
                    'masa_subur_berikutnya_akhir' => Carbon::parse($ovulasi_berikutnya)->addDays(2)->toDateString(),
                    'is_actual' => '0'
                ]);
            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success'),
                "data" => [
                    "period_history" => $period_history
                ]
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.saving_failed').' | '.$th->getMessage()
            ], 400);
        }
    }
}
