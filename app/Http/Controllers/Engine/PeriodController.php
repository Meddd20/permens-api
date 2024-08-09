<?php

namespace App\Http\Controllers\Engine;

use Carbon\Carbon;
use App\Models\Login;
use App\Models\RiwayatMens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

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
            'periods.*.first_period' => 'required|date_format:Y-m-d|before_or_equal:' . Carbon::today()->toDateString(),
            'periods.*.last_period' => 'required|date_format:Y-m-d|after:periods.*.first_period',
        ];
        $messages = [
            'periods.*.first_period.required' => __('attribute.first_period_required'),
            'periods.*.first_period.date_format' => __('attribute.first_period_format'),
            'periods.*.first_period.before_or_equal' => __('attribute.first_period_max_today'),
            
            'periods.*.last_period.required' => __('attribute.last_period_required'),
            'periods.*.last_period.date_format' => __('attribute.last_period_format'),
            'periods.*.last_period.after' => __('attribute.last_period_after_first'),
        ];
        $attributes = [
            'periods.*.first_period' => __('attribute.first_period'),
            'periods.*.last_period' => __('attribute.last_period'),
        ];
        $validator = Validator::make($request->all(), $rules, [], $attributes);
        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            $formattedError = ucfirst($errors);
            return response()->json([
                'status' => 'error',
                'message' => $formattedError
            ], Response::HTTP_BAD_REQUEST);
        }

        # Start & End Period from Input
        $periods = $request->get('periods');

        if (is_null($periods)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid data format. periods is null.',
            ], Response::HTTP_BAD_REQUEST);
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
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
        }
        
        if (!$user) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.user_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $periodsCount = count($periods);
        $newPeriods = [];

        $validating_period = RiwayatMens::where('user_id', $user_id)
                    ->where('is_actual', '1')
                    ->get();
        
        if (count($validating_period) < 1) {
            $validated = $request->validate([
                'period_cycle' => 'required|numeric'
            ]);
        }

        foreach ($validating_period as $period) {
            for ($i = 0; $i < $periodsCount; $i++) {
                $period_start = Carbon::parse($periods[$i]['first_period']);
                $period_end = Carbon::parse($periods[$i]['last_period']);
    
                if ($period_start->diffInDays($period_end) < 1) {
                    return response()->json([
                        "status" => "failed",
                        "message" => __('response.period.diffInDays'),
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            if (($period_start->between($period->haid_awal, $period->haid_akhir)) || 
                ($period_end->between($period->haid_awal, $period->haid_akhir))) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.period.overlap'),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            DB::beginTransaction();

            for ($i = 0; $i < $periodsCount; $i++) {
                # Start & End Period from Input
                $period_start = Carbon::parse($periods[$i]['first_period']);
                $period_end = Carbon::parse($periods[$i]['last_period']);
                
                # Period Duration from Input
                $period_duration = Carbon::parse($period_end)->diffInDays(Carbon::parse($period_start));

                $period_history = RiwayatMens::where('user_id', $user_id)
                    ->where('is_actual', '1')
                    ->get();

                # Inisialisasi variabel-variabel
                $period_cycle = null;
                $avg_period_cycle = $request->period_cycle;
                $avg_period_duration = $period_duration;
                $siklus_tengah_haid = null;
                $last_period_cycle = null;
                $last_day_cycle = null;
                
                if (!empty($period_history)) {
                    $total_cycle = 0;
                    $count_cycle = 0;
                    
                    $total_duration = $period_duration; 
                    $count_duration = 1;
                    
                    foreach ($period_history as $period) {
                        if (!is_null($period->lama_siklus)) {
                            $total_cycle += $period->lama_siklus;
                            $count_cycle++;
                        }
                        if (!is_null($period->durasi_haid)) {
                            $total_duration += $period->durasi_haid;
                            $count_duration++;
                        }
                    }
                    
                    $avg_period_cycle = $count_cycle > 0 ? intval($total_cycle / $count_cycle) : $request->period_cycle; 
                    $avg_period_duration = intval($total_duration / $count_duration);
                }
                
                $siklus_tengah_haid = ceil($avg_period_cycle / 2);

                $previous_periods = $period_history->where('haid_awal', '<=', $period_start);

                # Period Validation When Period History is Empty
                if (count($previous_periods) < 1 && $i == 0) {
                    $period_cycle = $request->period_cycle;

                    $next_period = $period_history->where('haid_awal', '>', $period_start)
                        ->sortBy('haid_awal')
                        ->first();

                    if (!empty($next_period)) {
                        $next_cycle_period_start = Carbon::parse($next_period->haid_awal);

                        if ($period_start->diffInDays($next_cycle_period_start->subDay(1)) > 0) {
                            $period_cycle = $period_start->diffInDays($next_cycle_period_start);
                            $last_day_cycle = $next_cycle_period_start->subDay(1);
                        }
                    }

                } else {
                    $previous_period = $period_history->where('haid_awal', '<=', $period_start)
                            ->sortByDesc('haid_awal')
                            ->first();
                    $next_period = $period_history->where('haid_awal', '>=', $period_start)
                            ->sortBy('haid_awal')
                            ->first();

                    if (!empty($previous_period)) {
                        $last_period_cycle = Carbon::parse($previous_period->haid_awal)->diffInDays(Carbon::parse($period_start)->subDay(1));

                        $previous_period->update([
                            'lama_siklus' => $last_period_cycle,
                            'hari_terakhir_siklus' => Carbon::parse($period_start)->subDay(1),
                            'hari_terakhir_siklus_berikutnya' => Carbon::parse($previous_period->haid_berikutnya_awal)->addDay($avg_period_cycle),
                        ]);
                    }

                    if (!empty($next_period)) {
                        $next_cycle_period_start = Carbon::parse($next_period->haid_awal);
                        $period_cycle = Carbon::parse($period_start)->diffInDays(Carbon::parse($next_cycle_period_start)->subDay(1));
                        $last_day_cycle = Carbon::parse($next_cycle_period_start)->subDay(1);
                    }
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
                $next_period_end = Carbon::parse($next_period_start)->addDays($avg_period_duration)->toDateString();

                # Next Ovulasi
                $next_ovulation = Carbon::parse($next_period_start)->addDays($siklus_tengah_haid)->toDateString();

                # Next Cycle End
                $next_cycle_end = Carbon::parse($next_period_start)->addDay($avg_period_cycle);

                $new_period = RiwayatMens::create([
                    'user_id' => $user_id,
                    'haid_awal' => $period_start,
                    'haid_akhir' => $period_end,
                    'ovulasi' => $ovulasi,
                    'masa_subur_awal' => $masa_subur_awal,
                    'masa_subur_akhir' => $masa_subur_akhir,
                    'hari_terakhir_siklus' => $last_day_cycle,
                    'lama_siklus' => $period_cycle,
                    'durasi_haid' => $period_duration,
                    'haid_berikutnya_awal' => $next_period_start,
                    'haid_berikutnya_akhir' => $next_period_end,
                    'ovulasi_berikutnya' => $next_ovulation,
                    'masa_subur_berikutnya_awal' => Carbon::parse($next_ovulation)->subDays(5)->toDateString(),
                    'masa_subur_berikutnya_akhir' => Carbon::parse($next_ovulation)->addDays(2)->toDateString(),
                    'hari_terakhir_siklus_berikutnya' => $next_cycle_end,
                    'is_actual' => "1"
                ]);

                $newPeriods[] = $new_period;

                if ($i === ($periodsCount - 1)) {
                    $latest_period = RiwayatMens::where('user_id', $user_id)
                                        ->where('is_actual', '1')
                                        ->orderBy('haid_awal', 'DESC')
                                        ->first();

                    $period_history = RiwayatMens::where('user_id', $user_id)
                                        ->where('is_actual', '0')
                                        ->delete();

                    $this->generateStorePredictionPeriod($user_id, $latest_period, 3);
                }
            }

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success'),
                "data" => $newPeriods,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updatePeriod(Request $request)
    {
        # Input Validation
        $rules = [
            'period_id' => 'required',
            'first_period' => 'required|date_format:Y-m-d|before_or_equal:' . Carbon::today()->toDateString(),
            'last_period' => 'required|date_format:Y-m-d|after:first_period',
        ];
        $messages = [
            'first_period.required' => __('attribute.first_period_required'),
            'first_period.date_format' => __('attribute.first_period_format'),
            'first_period.before_or_equal' => __('attribute.first_period_max_today'),
            
            'last_period.required' => __('attribute.last_period_required'),
            'last_period.date_format' => __('attribute.last_period_format'),
            'last_period.after' => __('attribute.last_period_after_first'),
        ];
        $attributes = [
            'period_id' => __('attribute.period_id'),
            'first_period' => __('attribute.first_period'),
            'last_period' => __('attribute.last_period'),
        ];
        $validator = Validator::make($request->all(), $rules, [], $attributes);
        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            $formattedError = ucfirst($errors);
            return response()->json([
                'status' => 'error',
                'message' => $formattedError
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        
        $period_start = Carbon::parse($request->first_period);
        $period_end = Carbon::parse($request->last_period);

        $period_history = RiwayatMens::where('user_id', $user_id)
                    ->where('is_actual', '1')
                    ->get();

        foreach ($period_history as $period) {
            if ($period->id != $request->period_id && (($period_start->between($period->haid_awal, $period->haid_akhir)) || 
                ($period_end->between($period->haid_awal, $period->haid_akhir)))) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.period.overlap'),
                ], Response::HTTP_BAD_REQUEST);
            }
        }
        
        # Period Duration from Input
        $period_duration = Carbon::parse($period_end)->diffInDays(Carbon::parse($period_start));
        
        try {
            
            $edited_period = $period_history->where('id', $request->period_id)->first();

            if (!$edited_period) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.period_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }

            $period_cycle = null;
            $avg_period_cycle = $request->period_cycle;
            $avg_period_duration = $period_duration;
            $siklus_tengah_haid = null;
            $last_period_cycle = null;
            $last_day_cycle = null;

            if (!empty($period_history)) {
                $total_cycle = 0;
                $count_cycle = 0;
                
                $total_duration = $period_duration; 
                $count_duration = 1;
                
                foreach ($period_history as $period) {
                    if (!is_null($period->lama_siklus)) {
                        $total_cycle += $period->lama_siklus;
                        $count_cycle++;
                    }
                    if (!is_null($period->durasi_haid)) {
                        $total_duration += $period->durasi_haid;
                        $count_duration++;
                    }
                }
                
                $avg_period_cycle = $count_cycle > 0 ? intval($total_cycle / $count_cycle) : $request->period_cycle; 
                $avg_period_duration = intval($total_duration / $count_duration);
            }

            $siklus_tengah_haid = ceil($avg_period_cycle / 2);

            $previous_periods = $period_history->where('haid_awal', '<', $period_start)->where('id', '!=', $request->period_id);

            # Period Validation When Period History is Empty
            if (count($previous_periods) < 1) {
                $validated = $request->validate([
                    'period_cycle' => 'required|numeric'
                ]);

                $period_cycle = $request->period_cycle;

                $next_period = $period_history->where('haid_awal', '>', $period_start)
                        ->where('id', '!=', $request->period_id)
                        ->sortBy('haid_awal')
                        ->first();

                if (!empty($next_period)) {
                    $next_period_start = Carbon::parse($next_period->haid_awal);

                    if ($period_start->diffInDays($next_period_start->subDay(1)) > 0) { 
                        $period_cycle = $period_start->diffInDays($next_period_start);
                        $last_day_cycle = $next_period_start->subDay(1);
                    }
                }
            } else {
                $last_period_new_index = $period_history->where('haid_awal', '<=', $period_start)
                        ->where('id', '!=', $request->period_id)
                        ->sortByDesc('haid_awal')
                        ->first();
                $next_period_new_index = $period_history->where('haid_awal', '>=', $period_start)
                        ->where('id', '!=', $request->period_id)
                        ->sortBy('haid_awal')
                        ->first();
                $last_period_last_index = $period_history->where('haid_awal', '<=', $edited_period->haid_awal)
                        ->where('id', '!=', $request->period_id)
                        ->sortByDesc('haid_awal')
                        ->first();
                $next_period_last_index = $period_history->where('haid_awal', '>=', $edited_period->haid_awal)
                        ->where('id', '!=', $request->period_id)
                        ->sortBy('haid_awal')
                        ->first();
                
                if (!empty($last_period_last_index)) {
                    if (!empty($next_period_last_index)) {
                        $last_period_cycle = Carbon::parse($last_period_last_index->haid_awal)->diffInDays(Carbon::parse($next_period_last_index->haid_awal)->subDay(1));
                        $last_period_last_index->update([
                            'lama_siklus' => $last_period_cycle,
                            'hari_terakhir_siklus' => Carbon::parse($next_period_last_index->haid_awal)->subDay(1),
                            'hari_terakhir_siklus_berikutnya' => Carbon::parse($next_period_last_index->haid_awal)->addDay($avg_period_cycle),
                        ]);
                    } else {
                        $last_period_last_index->update([
                            'lama_siklus' => null,
                            'hari_terakhir_siklus' => null,
                        ]);
                    }
                }

                if (!empty($last_period_new_index)) {
                    $last_period_cycle = Carbon::parse($last_period_new_index->haid_awal)->diffInDays(Carbon::parse($period_start)->subDay(1));
                    $last_period_new_index->update([
                        'lama_siklus' => $last_period_cycle,
                        'hari_terakhir_siklus' => Carbon::parse($period_start)->subDay(1),
                        'hari_terakhir_siklus_berikutnya' => Carbon::parse($last_period_new_index->haid_berikutnya_awal)->addDay($avg_period_cycle),
                    ]);
                }
                
                if (!empty($next_period_new_index)) {
                    $next_cycle_period_start = Carbon::parse($next_period_new_index->haid_awal);
                    $period_cycle = Carbon::parse($period_start)->diffInDays(Carbon::parse($next_cycle_period_start)->subDay(1));
                    $last_day_cycle = Carbon::parse($next_cycle_period_start)->subDay(1);
                }
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

            # Next Cycle End
            $next_cycle_end = Carbon::parse($next_period_start)->addDay($avg_period_cycle);

            RiwayatMens::where('id', $request->period_id)->update([
                'user_id' => $user_id,
                'haid_awal' => $period_start,
                'haid_akhir' => $period_end,
                'ovulasi' => $ovulasi,
                'masa_subur_awal' => $masa_subur_awal,
                'masa_subur_akhir' => $masa_subur_akhir,
                'hari_terakhir_siklus' => $last_day_cycle,
                'lama_siklus' => $period_cycle,
                'durasi_haid' => $period_duration,
                'haid_berikutnya_awal' => $next_period_start,
                'haid_berikutnya_akhir' => $next_period_end,
                'ovulasi_berikutnya' => $ovulasi_berikutnya,
                'masa_subur_berikutnya_awal' => Carbon::parse($ovulasi_berikutnya)->subDays(5)->toDateString(),
                'masa_subur_berikutnya_akhir' => Carbon::parse($ovulasi_berikutnya)->addDays(2)->toDateString(),
                'hari_terakhir_siklus_berikutnya' => $next_cycle_end,
                'is_actual' => "1"
            ]);

            $latest_period = RiwayatMens::where('user_id', $user_id)
                                        ->where('is_actual', '1')
                                        ->orderBy('haid_awal', 'DESC')
                                        ->first();

            $period_history = RiwayatMens::where('user_id', $user_id)
                        ->where('is_actual', '0')
                        ->delete();

            $this->generateStorePredictionPeriod($user_id, $latest_period, 3);

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.update_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => "Updated Data Failed".' | '.$th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function generateStorePredictionPeriod($user_id, $storedPeriod, $actualCount) 
    {
        $period_start = $storedPeriod->haid_berikutnya_awal;

        for ($i = 0; $i < $actualCount; $i++) {

            $user = Login::where('id', $user_id)->first();

            if (!$user) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.user_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }

            $period_history = RiwayatMens::where('user_id', $user_id)
                ->where('is_actual', '1')
                ->where('haid_awal', '<', $period_start)
                ->get();

            $avg_period_duration = ceil(($period_history->sum('durasi_haid')) / ($period_history->count()));
            $avg_period_cycle = ceil(
                RiwayatMens::where('user_id', $user_id)
                    ->where('is_actual', '1')
                    ->whereNotNull('lama_siklus')
                    ->where('haid_awal', '<', $period_start)
                    ->sum('lama_siklus') /
                RiwayatMens::where('user_id', $user_id)
                    ->where('is_actual', '1')
                    ->whereNotNull('lama_siklus')
                    ->where('haid_awal', '<', $period_start)
                    ->count()
            );

            $period_end = Carbon::parse($period_start)->addDays($avg_period_duration)->toDateString();

            $siklus_tengah_haid = ceil($avg_period_cycle / 2);
            $last_day_cycle = Carbon::parse($period_start)->addDays($avg_period_cycle)->subDays(1)->toDateString();

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
            $last_day_next_cycle = Carbon::parse($next_period_start)->addDays($avg_period_cycle)->toDateString();

            $generatedPrediction = [
                'user_id' => $user_id,
                'haid_awal' => $period_start,
                'haid_akhir' => $period_end,
                'ovulasi' => $ovulasi,
                'masa_subur_awal' => $masa_subur_awal,
                'masa_subur_akhir' => $masa_subur_akhir,
                'hari_terakhir_siklus' => $last_day_cycle,
                'lama_siklus' => $avg_period_cycle,
                'durasi_haid' => $avg_period_duration,
                'haid_berikutnya_awal' => $next_period_start,
                'haid_berikutnya_akhir' => $next_period_end,
                'ovulasi_berikutnya' => $ovulasi_berikutnya,
                'masa_subur_berikutnya_awal' => $masa_subur_berikutnya_awal,
                'masa_subur_berikutnya_akhir' => $masa_subur_berikutnya_akhir,
                'hari_terakhir_siklus_berikutnya' => $last_day_next_cycle,
                'is_actual' => "0"
            ];

            // Store the additional data
            $storedPeriod = RiwayatMens::create($generatedPrediction);

            // Update variables for the next iteration if needed
            $period_start = $storedPeriod->haid_berikutnya_awal;
        }    
    }

    // public function storePrediction(Request $request)
    // {
    //     # Input Validation
    //     $rules = [
    //         "latest_period_id" => "required"
    //     ];
    //     $messages = [];
    //     $attributes = [
    //         'latest_period_id' => "Latest Period"
    //     ];
    //     $validator = Validator::make($request->all(), $rules, $messages, $attributes);
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $validator->errors()
    //         ], 400);
    //     }

    //     $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');

    //     # Get User Age from User Birthday
    //     $user = Login::where('id', $user_id)->first();
        
    //     if ($user) {
    //         $age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->age;
    //         $lunar_age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->addMonth(9)->age;
    //     } else {
    //         // Handle the case when $user is null, e.g., return an error response.
    //         return response()->json([
    //             "status" => "failed",
    //             "message" => __('response.user_not_found'),
    //         ], 404);
    //     }

    //     # Latest Period
    //     $period = RiwayatMens::where('id', $request->latest_period_id)->first();

    //     # Period Cycle & Average Period Cycle
    //     $period_history = RiwayatMens::where('user_id', $user_id)->get();
    //     $period_cycle = Carbon::parse($period->haid_awal)->diffInDays(Carbon::parse($period->haid_berikutnya_awal));
    //     $avg_period_cycle = ($period_history->sum('lama_siklus')+$period_cycle) / ($period_history->count()+1);

    //     # Period Duration
    //     $period_duration = Carbon::parse($period->haid_berikutnya_awal)->diffInDays(Carbon::parse($period->haid_berikutnya_akhir)) + 1;

    //     # Siklus Tengah Haid
    //     $siklus_tengah_haid = ceil($avg_period_cycle / 2);

    //     # Next Period End
    //     $next_period_start = Carbon::parse($period->haid_berikutnya_awal)->addDays($avg_period_cycle)->toDateString();

    //     # Next Period End
    //     $next_period_end = Carbon::parse($period->haid_berikutnya_awal)->addDays($avg_period_cycle + $period_duration)->toDateString();

    //     # Next Ovulasi
    //     $ovulasi_berikutnya = Carbon::parse($next_period_start)->addDays($siklus_tengah_haid)->toDateString();

    //     # Return Response
    //     try {
    //         DB::beginTransaction();
    //             $period_history = RiwayatMens::create([
    //                 'user_id' => $user_id,
    //                 'usia' => $age,
    //                 'usia_lunar' => $lunar_age,
    //                 'haid_awal' => date("Y-m-d", strtotime($period->haid_berikutnya_awal)),
    //                 'haid_akhir' => date("Y-m-d", strtotime($period->haid_berikutnya_akhir)),
    //                 'ovulasi' => date("Y-m-d", strtotime($period->ovulasi_berikutnya)),
    //                 'masa_subur_awal' => date("Y-m-d", strtotime($period->masa_subur_berikutnya_awal)),
    //                 'masa_subur_akhir' => date("Y-m-d", strtotime($period->masa_subur_berikutnya_akhir)),
    //                 'lama_siklus' => $period_cycle,
    //                 'durasi_haid' => $period_duration,
    //                 'haid_berikutnya_awal' => $next_period_start,
    //                 'haid_berikutnya_akhir' => $next_period_end,
    //                 'ovulasi_berikutnya' => $ovulasi_berikutnya,
    //                 'masa_subur_berikutnya_awal' => Carbon::parse($ovulasi_berikutnya)->subDays(5)->toDateString(),
    //                 'masa_subur_berikutnya_akhir' => Carbon::parse($ovulasi_berikutnya)->addDays(2)->toDateString(),
    //                 'is_actual' => '0'
    //             ]);
    //         DB::commit();

    //         return response()->json([
    //             "status" => "success",
    //             "message" => __('response.saving_success'),
    //             "data" => [
    //                 "period_history" => $period_history
    //             ]
    //         ], 200);
    //     } catch (\Throwable $th) {
    //         DB::rollback();

    //         return response()->json([
    //             "status" => "failed",
    //             "message" => __('response.saving_failed').' | '.$th->getMessage()
    //         ], 400);
    //     }
    // }
}
