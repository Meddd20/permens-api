<?php

namespace App\Http\Controllers\Engine;

use Carbon\Carbon;
use App\Models\Login;
use App\Models\RiwayatMens;
use App\Models\MasterGender;
use Illuminate\Http\Request;
use App\Models\MasterKehamilan;
use App\Models\RiwayatKehamilan;
use App\Http\Controllers\Controller;
use App\Models\UToken;
use Illuminate\Support\Facades\Validator;

class MainController extends Controller
{
    public function index(Request $request)
    {
        try {
            # Get User Data (Current Year, User ID, User Age, User Lunar Age)
            $current_year = Carbon::now()->format('Y');
            $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');
            $user = Login::where('id', $user_id)->first();
            $user_age = Carbon::parse($user->tanggal_lahir)->age;
            $user_lunar_age = Carbon::parse($user->tanggal_lahir)->subMonth(9)->age;
    
            # Get Chart Data (Period Duration Chart, Period Cycle Chart, Chart Date)
            $period_history = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->get();
            if (count($period_history) > 0) {
                foreach ($period_history as $data) {
                    $period_duration_chart[] = $data->durasi_haid;
                    $period_cycle_chart[] = $data->lama_siklus;
                    $period_chart_date[] = [
                        "start_date" => Carbon::parse($data->haid_awal ?? '')->format('Y-m-d'),
                        "end_date" => Carbon::parse($data->haid_akhir)->format('Y-m-d')
                    ];
                }
    
                $latest_period_month = Carbon::parse($period_history->first()->haid_awal ?? '')->format('m');
                if ($latest_period_month < Carbon::now()->format('m')) {
                    $confirm_predictive_data = [
                        "year" => Carbon::parse($period_history->first()->haid_awal ?? '')->addMonth(1)->format('Y'),
                        "month" => Carbon::parse($period_history->first()->haid_awal ?? '')->addMonth(1)->format('m'),
                        "id" => $period_history->first()->id
                    ];
                } else {
                    $confirm_predictive_data = "hide";
                }
            } else {
                $period_duration_chart[] = NULL;
                $period_cycle_chart[] = NULL;
                $period_chart_date[] = NULL;
                $confirm_predictive_data = "hide";
            }

            
            # Get Period Data (Latest Period History, Shortest Period, Longest Period, Average Period Duration, Averatge Period Cycle)
            $latest_period_history = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->orderBy('haid_awal', 'DESC')->first();
            $actual_period_history = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->orderBy('haid_awal', 'DESC')->get();
            $prediction_period_history = RiwayatMens::where('user_id', $user_id)->where('is_actual', '0')->orderBy('haid_awal', 'DESC')->get();
            $shortest_period = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('lama_siklus', 'ASC')->select('lama_siklus')->first();
            $longest_period = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('lama_siklus', 'DESC')->select('lama_siklus')->first();
            $avg_period_duration = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->avg('durasi_haid');
            $avg_period_cycle = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->avg('lama_siklus');
            // return response()->json([
            //     "status" => "failed",
            //     "message" => "gagal disini",
            // ], 400);
    
            # Get Pregnancy Data
            if ($user->is_pregnant == '0') {
                $pregnant_begin = NULL;
                $pregnant_end = NULL;
                $usia_kehamilan = NULL;
                $berat_janin = NULL;
                $pertambahan_berat_ibu = NULL;
                $tinggi_badan_janin = NULL;
            } else {
                $pregnant_begin = RiwayatKehamilan::where('user_id', $user_id)->orderBy('created_at', 'DESC')->first()->kehamilan_awal;
                $pregnant_end = Carbon::parse($pregnant_begin)->addYear(1)->addDays(7)->subMonth(3)->toDateString();
                $usia_kehamilan = Carbon::now()->diffInWeeks($pregnant_begin);

                $master_kehamilan = MasterKehamilan::where('minggu_kehamilan', $usia_kehamilan)->first();
                if ($master_kehamilan != NULL) {
                    $berat_janin = $master_kehamilan->berat_janin;
                    $pertambahan_berat_ibu = $master_kehamilan->pertambahan_berat_ibu;
                    $tinggi_badan_janin = $master_kehamilan->tinggi_badan_janin;
                } else {
                    $berat_janin = 0;
                    $pertambahan_berat_ibu = 0;
                    $tinggi_badan_janin = 0;
                }
            }
    
            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => [
                    "first_year" => Carbon::parse(RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'ASC')->first()->haid_awal ?? '')->format('Y'),
                    "last_year" => $current_year,
                    "current_year" => $current_year,
                    "user_age" => $user_age,
                    "user_lunar_age" => $user_lunar_age,
                    "shortest_period" => $shortest_period,
                    "longest_period" => $longest_period,
                    "avg_period_duration" => $avg_period_duration,
                    "avg_period_cycle" => $avg_period_cycle,
                    "period_cycle_chart" => $period_cycle_chart,
                    "period_chart_date" => $period_chart_date,
                    "period_history" => $period_history,
                    "latest_period_history" => $latest_period_history,
                    "actual_period_history" => $actual_period_history,
                    "prediction_period_history" => $prediction_period_history,
                    "confirm_predictive_data" => $confirm_predictive_data,
                    "period_duration_chart" => $period_duration_chart,
                    "pregnancy_begin" => $pregnant_begin,
                    "pregnancy_end" => $pregnant_end,
                    "pregnancy_weeks" => $usia_kehamilan,
                    "bb_rata_rata_janin_gr" => $berat_janin,
                    "pertambahan_bb_rata_rata_ibu_kg" => $pertambahan_berat_ibu,
                    "tb_rata_rata_janin_cm" => $tinggi_badan_janin,
                    "gender" => MasterGender::where('usia', $user_lunar_age)->where('bulan', Carbon::parse($pregnant_begin)->format('m'))->first()
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], 400);
        }
    }

    public function filter(Request $request)
    {
        # Input Validation
        $rules = [
            "year" => "required"
        ];
        $messages = [];
        $attributes = [
            'year' => __('attribute.year'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        try {
            # Get User Data (Current Year, User ID, User Age, User Lunar Age)
            $current_year = $request->year;
            $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');
            $user = Login::where('id', $user_id)->first();
            $user_age = Carbon::parse($user->tanggal_lahir)->age;
            $user_lunar_age = Carbon::parse($user->tanggal_lahir)->subMonth(9)->age;
    
            # Get Chart Data (Period Duration Chart, Period Cycle Chart, Chart Date)
            $period_history = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->get();
            if (count($period_history) > 0) {
                foreach ($period_history as $data) {
                    $period_duration_chart[] = $data->durasi_haid;
                    $period_cycle_chart[] = $data->lama_siklus;
                    $period_chart_date[] = Carbon::parse($data->haid_awal)->format('d M').' - '.Carbon::parse($data->haid_akhir)->format('d M');
                }
    
                $latest_period_month = Carbon::parse($period_history->first()->haid_awal)->toDateString();
                if ($latest_period_month < Carbon::now()->toDateString()) {
                    $confirm_predictive_data = [
                        "year" => Carbon::parse($period_history->first()->haid_awal)->addMonth(1)->format('Y'),
                        "month" => Carbon::parse($period_history->first()->haid_awal)->addMonth(1)->format('m'),
                        "id" => $period_history->first()->id
                    ];
                } else {
                    $confirm_predictive_data = "hide";
                }
            } else {
                $period_duration_chart[] = NULL;
                $period_cycle_chart[] = NULL;
                $period_chart_date[] = NULL;
                $confirm_predictive_data = "hide";
            }
            
            # Get Period Data (Latest Period History, Shortest Period, Longest Period, Average Period Duration, Averatge Period Cycle)
            $latest_period_history = RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'DESC')->first();
            $shortest_period = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('lama_siklus', 'ASC')->select('lama_siklus')->first();
            $longest_period = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('lama_siklus', 'DESC')->select('lama_siklus')->first();
            $avg_period_duration = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->avg('durasi_haid');
            $avg_period_cycle = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->avg('lama_siklus');
    
            # Get Pregnancy Data
            if ($user->is_pregnant == '0') {
                $pregnant_begin = NULL;
                $pregnant_end = NULL;
                $usia_kehamilan = NULL;
                $berat_janin = NULL;
                $pertambahan_berat_ibu = NULL;
                $tinggi_badan_janin = NULL;
            } else {
                $pregnant_begin = RiwayatKehamilan::where('user_id', $user_id)->orderBy('created_at', 'DESC')->first()->kehamilan_awal;
                $pregnant_end = Carbon::parse($pregnant_begin)->addYear(1)->addDays(7)->subMonth(3)->toDateString();
                $usia_kehamilan = Carbon::now()->diffInWeeks($pregnant_begin);

                $master_kehamilan = MasterKehamilan::where('minggu_kehamilan', $usia_kehamilan)->first();
                if ($master_kehamilan != NULL) {
                    $berat_janin = $master_kehamilan->berat_janin;
                    $pertambahan_berat_ibu = $master_kehamilan->pertambahan_berat_ibu;
                    $tinggi_badan_janin = $master_kehamilan->tinggi_badan_janin;
                } else {
                    $berat_janin = 0;
                    $pertambahan_berat_ibu = 0;
                    $tinggi_badan_janin = 0;
                }
            }
    
            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => [
                    "current_year" => $current_year,
                    "first_year" => Carbon::parse(RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'ASC')->first()->haid_awal ?? '')->format('Y'),
                    "last_year" => Carbon::now()->format('Y'),
                    "user_age" => $user_age,
                    "user_lunar_age" => $user_lunar_age,
                    "shortest_period" => $shortest_period,
                    "longest_period" => $longest_period,
                    "avg_period_duration" => $avg_period_duration,
                    "avg_period_cycle" => $avg_period_cycle,
                    "period_cycle_chart" => $period_cycle_chart,
                    "period_chart_date" => $period_chart_date,
                    "period_history" => $period_history,
                    "latest_period_history" => $latest_period_history,
                    "confirm_predictive_data" => $confirm_predictive_data,
                    "period_duration_chart" => $period_duration_chart,
                    "pregnancy_begin" => $pregnant_begin,
                    "pregnancy_end" => $pregnant_end,
                    "pregnancy_weeks" => $usia_kehamilan,
                    "bb_rata_rata_janin_gr" => $berat_janin,
                    "pertambahan_bb_rata_rata_ibu_kg" => $pertambahan_berat_ibu,
                    "tb_rata_rata_janin_cm" => $tinggi_badan_janin,
                    "gender" => MasterGender::where('usia', $user_lunar_age)->where('bulan', Carbon::parse($pregnant_begin)->format('m'))->first()
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], 400);
        }
    }

    public function insight(Request $request)
    {
        # Input Validation
        $rules = [
            "period_id" => "required"
        ];
        $messages = [];
        $attributes = [
            'period_id' => __('attribute.period_id')
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        $latest_period_history = RiwayatMens::where('id', $request->period_id)->first();
        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');
        if ($latest_period_history->user_id != $user_id) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.period_history_not_found'),
            ], 400);
        }

        try {
            # Get User Data (Current Year, User ID, User Age, User Lunar Age)
            $last_year = Carbon::now()->format('Y');
            $current_year = Carbon::parse($latest_period_history->haid_awal)->format('Y');
            $user = Login::where('id', $user_id)->first();
            $user_age = Carbon::parse($user->tanggal_lahir)->age;
            $user_lunar_age = Carbon::parse($user->tanggal_lahir)->subMonth(9)->age;
    
            # Get Chart Data (Period Duration Chart, Period Cycle Chart, Chart Date)
            $period_history = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', Carbon::parse($latest_period_history->haid_awal)->format('Y'))->orderBy('haid_awal', 'DESC')->get();
            if (count($period_history) > 0) {
                foreach ($period_history as $data) {
                    $period_duration_chart[] = $data->durasi_haid;
                    $period_cycle_chart[] = $data->lama_siklus;
                    $period_chart_date[] = Carbon::parse($data->haid_awal)->format('d M').' - '.Carbon::parse($data->haid_akhir)->format('d M');
                }
    
                $latest_period_month = Carbon::parse($period_history->first()->haid_awal)->format('m');
                if ($latest_period_month < Carbon::now()->format('m')) {
                    $confirm_predictive_data = [
                        "month" => Carbon::parse($period_history->first()->haid_awal)->addMonth(1)->format('m'),
                        "year" => Carbon::parse($period_history->first()->haid_awal)->addMonth(1)->format('Y'),
                        "id" => $period_history->first()->id
                    ];
                } else {
                    $confirm_predictive_data = "hide";
                }
            } else {
                $period_duration_chart[] = NULL;
                $period_cycle_chart[] = NULL;
                $period_chart_date[] = NULL;
                $confirm_predictive_data = "hide";
            }
            
            # Get Period Data (Latest Period History, Shortest Period, Longest Period, Average Period Duration, Averatge Period Cycle)
            $shortest_period = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('lama_siklus', 'ASC')->select('lama_siklus')->first();
            $longest_period = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('lama_siklus', 'DESC')->select('lama_siklus')->first();
            $avg_period_duration = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->avg('durasi_haid');
            $avg_period_cycle = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->avg('lama_siklus');
    
            # Get Pregnancy Data
            $pregnant_begin = RiwayatKehamilan::where('user_id', $user_id)->orderBy('created_at', 'DESC')->first()->kehamilan_awal;
            $pregnant_end = Carbon::parse($pregnant_begin)->addYear(1)->addDays(7)->subMonth(3)->toDateString();
            $usia_kehamilan = Carbon::now()->diffInWeeks($pregnant_begin);

            $master_kehamilan = MasterKehamilan::where('minggu_kehamilan', $usia_kehamilan)->first();
            if ($master_kehamilan != NULL) {
                $berat_janin = $master_kehamilan->berat_janin;
                $pertambahan_berat_ibu = $master_kehamilan->pertambahan_berat_ibu;
                $tinggi_badan_janin = $master_kehamilan->tinggi_badan_janin;
            } else {
                $berat_janin = 0;
                $pertambahan_berat_ibu = 0;
                $tinggi_badan_janin = 0;
            }
    
            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => [
                    "period_id" => $request->period_id,
                    "first_year" => Carbon::parse(RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'ASC')->first()->haid_awal ?? '')->format('Y'),
                    "last_year" => Carbon::now()->toDateString(),
                    "current_year" => $current_year,
                    "user_age" => $user_age,
                    "user_lunar_age" => $user_lunar_age,
                    "shortest_period" => $shortest_period,
                    "longest_period" => $longest_period,
                    "avg_period_duration" => $avg_period_duration,
                    "avg_period_cycle" => $avg_period_cycle,
                    "period_cycle_chart" => $period_cycle_chart,
                    "period_chart_date" => $period_chart_date,
                    "period_history" => $period_history,
                    "latest_period_history" => $latest_period_history,
                    "confirm_predictive_data" => $confirm_predictive_data,
                    "period_duration_chart" => $period_duration_chart,
                    "pregnancy_begin" => $pregnant_begin,
                    "pregnancy_end" => $pregnant_end,
                    "pregnancy_weeks" => $usia_kehamilan,
                    "bb_rata_rata_janin_gr" => $berat_janin,
                    "pertambahan_bb_rata_rata_ibu_kg" => $pertambahan_berat_ibu,
                    "tb_rata_rata_janin_cm" => $tinggi_badan_janin,
                    "gender" => MasterGender::where('usia', $user_lunar_age)->where('bulan', Carbon::parse($pregnant_begin)->format('m'))->first(),
                    "master_gender" => MasterGender::where('usia', $user_lunar_age)->get()
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], 400);
        }
    }

    public function currentDateEvent(Request $request) {
        # Input Validation
        $rules = [
            "date_selected" => "required|date",
        ];
        $messages = [];
        $attributes = [
            'date_selected' => __('attribute.date'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');
        $periodHistory = RiwayatMens::where('user_id', $user_id)
            ->orderBy('haid_awal', 'asc')
            ->get();
        $recordsCount = count($periodHistory);

        $nextMenstruationStart = null;
        $nextMenstruationEnd = null;
        $nextOvulation = null;
        $nextFollicularStart = null;
        $nextFollicularEnd = null;
        $nextFertileStart = null;
        $nextFertileEnd = null;
        $nextLutealStart = null;
        $nextLutealEnd = null;
        $eventId = null;
        $pregnancy_chances = null;

        try {
            foreach ($periodHistory as $key => $period) {
                $currentIsActual = $period->is_actual;
            
                // Check if there is a next record
                if ($key < $recordsCount - 1) {
                    $nextRecord = $periodHistory[$key + 1];
                    $nextIsActual = $nextRecord->is_actual;
        
                    // Now you can check $currentIsActual and $nextIsActual
                    if ($currentIsActual == 1 && $nextIsActual == 1) {
                        // Case 1: LUTEALEND from haid_awal of $nextRecord
                        $lutealEnd = Carbon::parse($nextRecord->haid_awal);
                    } elseif ($currentIsActual == 0 && $nextIsActual == 0) {
                        // Case 2: LUTEALEND from haid_berikutnya_awal of current record
                        $lutealEnd = Carbon::parse($period->haid_berikutnya_awal);
                    } elseif ($currentIsActual == 0 && $nextIsActual == 1) {
                        // Case 3: LUTEALEND from haid_awal of $nextRecord
                        $lutealEnd = Carbon::parse($nextRecord->haid_awal);
                    }
                } else {
                    // Handling the last record
                    if ($currentIsActual == 0) {
                        // Case 2: LUTEALEND from haid_berikutnya_awal of current record
                        $lutealEnd = Carbon::parse($period->haid_berikutnya_awal);
                    }
                }

                $periodStart = Carbon::parse($period->haid_awal);
                $periodEnd = Carbon::parse($period->haid_akhir);
                $ovulation = Carbon::parse($period->ovulasi);
                $follicularStart = Carbon::parse($periodEnd);
                $follicularEnd = Carbon::parse($ovulation);
                $fertileStart = Carbon::parse($period->masa_subur_awal);
                $fertileEnd = Carbon::parse($period->masa_subur_akhir);
                $lutealStart = Carbon::parse($fertileEnd);

                // Determine events on the specified date
                $specifiedDate = Carbon::parse($request->input('date_selected'));

                if ($specifiedDate->between($periodStart, $periodEnd)) {
                    $event = 'Menstruation';
                    $is_actual = ($currentIsActual ? '1' : '0');
                    $eventId = $period->id;
                    $pregnancy_chances = "Low";
                    $firstDayOfMenstruation = Carbon::parse($period->haid_awal);
                    $dayOfCycle = $firstDayOfMenstruation->diffInDays($specifiedDate) + 1;
                    
                    // Check if it's the last record
                    if ($key == $recordsCount - 1) {
                        $nextMenstruationStart = Carbon::parse($period->haid_berikutnya_awal);
                        $nextMenstruationEnd = Carbon::parse($period->haid_berikutnya_akhir);
                    } else {
                        $nextMenstruationStart = Carbon::parse($nextRecord->haid_awal);
                        $nextMenstruationEnd = Carbon::parse($nextRecord->haid_akhir);
                    }
                    $daysUntilNextMenstruation = $nextMenstruationStart->diffInDays($specifiedDate);

                    $nextOvulation = Carbon::parse($period->ovulasi);
                    $daysUntilNextOvulation = $nextOvulation->diffInDays($specifiedDate);

                    $nextFollicularStart = Carbon::parse($period->haid_akhir);
                    $nextFollicularEnd = Carbon::parse($period->masa_subur_awal);
                    $daysUntilNextFollicular = $nextFollicularStart->diffInDays($specifiedDate);

                    $nextFertileStart = Carbon::parse($period->masa_subur_awal);
                    $nextFertileEnd = Carbon::parse($period->masa_subur_akhir);
                    $daysUntilNextFertile = $nextFertileStart->diffInDays($specifiedDate);

                    $nextLutealStart = Carbon::parse($period->masa_subur_akhir);
                    $nextLutealEnd = Carbon::parse($nextRecord->haid_awal);
                    $daysUntilNextLuteal = $nextLutealStart->diffInDays($specifiedDate);
                }

                if ($specifiedDate > $follicularStart && $specifiedDate->lte($follicularEnd)) {
                    $event = 'Follicular';
                    $is_actual = ($currentIsActual ? '1' : '0');
                    $eventId = $period->id;
                    $pregnancy_chances = "Low";
                    $firstDayOfMenstruation = Carbon::parse($period->haid_awal);
                    $dayOfCycle = $firstDayOfMenstruation->diffInDays($specifiedDate) + 1;
                    
                    // Check if it's the last record
                    if ($key == $recordsCount - 1) {
                        $nextMenstruationStart = Carbon::parse($period->haid_berikutnya_awal);
                        $nextMenstruationEnd = Carbon::parse($period->haid_berikutnya_akhir);
                        $nextFollicularStart = Carbon::parse($period->haid_berikutnya_akhir);
                        $nextFollicularEnd = Carbon::parse($period->masa_subur_berikutnya_awal);
                    } else {
                        $nextMenstruationStart = Carbon::parse($nextRecord->haid_awal);
                        $nextMenstruationEnd = Carbon::parse($nextRecord->haid_akhir);
                        $nextFollicularStart = Carbon::parse($nextRecord->haid_akhir);
                        $nextFollicularEnd = Carbon::parse($nextRecord->masa_subur_awal);
                    }
                    $daysUntilNextMenstruation = $nextMenstruationStart->diffInDays($specifiedDate);

                    $nextOvulation = Carbon::parse($period->ovulasi);
                    $daysUntilNextOvulation = $nextOvulation->diffInDays($specifiedDate);
                    $daysUntilNextFollicular = $nextFollicularStart->diffInDays($specifiedDate);

                    $nextFertileStart = Carbon::parse($period->masa_subur_awal);
                    $nextFertileEnd = Carbon::parse($period->masa_subur_akhir);
                    $daysUntilNextFertile = $nextFertileStart->diffInDays($specifiedDate);

                    $nextLutealStart = Carbon::parse($period->masa_subur_akhir);
                    $nextLutealEnd = Carbon::parse($nextRecord->haid_awal);
                    $daysUntilNextLuteal = $nextLutealStart->diffInDays($specifiedDate);
                }

                if ($specifiedDate->between($fertileStart, $fertileEnd)) {

                    if ($specifiedDate->equalTo($ovulation)) {
                        $event = 'Ovulation';
                    } else {
                        $event = 'Fertile';
                    }
                    $is_actual = ($currentIsActual ? '1' : '0');
                    $eventId = $period->id;
                    $pregnancy_chances = "High";
                    $firstDayOfMenstruation = Carbon::parse($period->haid_awal);
                    $dayOfCycle = $firstDayOfMenstruation->diffInDays($specifiedDate) + 1;

                    // Check if it's the last record
                    if ($key == $recordsCount - 1) {
                        $nextMenstruationStart = Carbon::parse($period->haid_berikutnya_awal);
                        $nextMenstruationEnd = Carbon::parse($period->haid_berikutnya_akhir);
                        $nextFollicularStart = Carbon::parse($period->haid_berikutnya_akhir);
                        $nextFollicularEnd = Carbon::parse($period->masa_subur_berikutnya_awal);
                        $nextFertileStart = Carbon::parse($period->masa_subur_berikutnya_awal);
                        $nextFertileEnd = Carbon::parse($period->masa_subur_berikutnya_akhir);
                        $nextLutealEnd = Carbon::parse($period->haid_berikutnya_awal);

                        if ($specifiedDate < $ovulation) {
                            $nextOvulation = Carbon::parse($period->ovulasi);
                        } else {
                            $nextOvulation = Carbon::parse($period->ovulasi_berikutnya);
                        }
                    } else {
                        $nextMenstruationStart = Carbon::parse($nextRecord->haid_awal);
                        $nextMenstruationEnd = Carbon::parse($nextRecord->haid_akhir);
                        $nextFollicularStart = Carbon::parse($nextRecord->haid_akhir);
                        $nextFollicularEnd = Carbon::parse($nextRecord->masa_subur_awal);
                        $nextFertileStart = Carbon::parse($nextRecord->masa_subur_awal);
                        $nextFertileEnd = Carbon::parse($nextRecord->masa_subur_akhir);
                        $nextLutealEnd = Carbon::parse($nextRecord->haid_awal);

                        if ($specifiedDate < $ovulation) {
                            $nextOvulation = Carbon::parse($period->ovulasi);
                        } else {
                            $nextOvulation = Carbon::parse($nextRecord->ovulasi);
                        }
                    }
                    $daysUntilNextMenstruation = $nextMenstruationStart->diffInDays($specifiedDate);
                    $daysUntilNextOvulation = $nextOvulation->diffInDays($specifiedDate);
                    $daysUntilNextFollicular = $nextFollicularStart->diffInDays($specifiedDate);
                    $daysUntilNextFertile = $nextFertileStart->diffInDays($specifiedDate);

                    $nextLutealStart = Carbon::parse($period->masa_subur_akhir);
                    $daysUntilNextLuteal = $nextLutealStart->diffInDays($specifiedDate);
                }

                if ($specifiedDate > $lutealStart && $specifiedDate->lte($lutealEnd)) {
                    $event = 'Luteal';
                    $is_actual = ($currentIsActual ? '1' : '0');
                    $eventId = $period->id;
                    $pregnancy_chances = "Low";
                    $firstDayOfMenstruation = Carbon::parse($period->haid_awal);
                    $dayOfCycle = $firstDayOfMenstruation->diffInDays($specifiedDate) + 1;

                    // Check if it's the last record
                    if ($key == $recordsCount - 1) {
                        $nextMenstruationStart = Carbon::parse($period->haid_berikutnya_awal);
                        $nextMenstruationEnd = Carbon::parse($period->haid_berikutnya_akhir);
                        $nextFollicularStart = Carbon::parse($period->haid_berikutnya_akhir);
                        $nextFollicularEnd = Carbon::parse($period->masa_subur_berikutnya_awal);
                        $nextFertileStart = Carbon::parse($period->masa_subur_berikutnya_awal);
                        $nextFertileEnd = Carbon::parse($period->masa_subur_berikutnya_akhir);
                        $nextOvulation = Carbon::parse($period->ovulasi_berikutnya);
                        $nextLutealStart = Carbon::parse($period->masa_subur_berikutnya_akhir);
                        $nextLutealEnd = null;
                    } else {
                        $nextMenstruationStart = Carbon::parse($nextRecord->haid_awal);
                        $nextMenstruationEnd = Carbon::parse($nextRecord->haid_akhir);
                        $nextFollicularStart = Carbon::parse($nextRecord->haid_akhir);
                        $nextFollicularEnd = Carbon::parse($nextRecord->masa_subur_awal);
                        $nextFertileStart = Carbon::parse($nextRecord->masa_subur_awal);
                        $nextFertileEnd = Carbon::parse($nextRecord->masa_subur_akhir);
                        $nextOvulation = Carbon::parse($nextRecord->ovulasi);
                        $nextLutealStart = Carbon::parse($nextRecord->masa_subur_akhir);
                        $nextLutealEnd = Carbon::parse($nextRecord->haid_berikutnya_awal);
                    }
                    $daysUntilNextMenstruation = $nextMenstruationStart->diffInDays($specifiedDate);
                    $daysUntilNextOvulation = $nextOvulation->diffInDays($specifiedDate);
                    $daysUntilNextFollicular = $nextFollicularStart->diffInDays($specifiedDate);
                    $daysUntilNextFertile = $nextFertileStart->diffInDays($specifiedDate);
                    $daysUntilNextLuteal = $nextLutealStart->diffInDays($specifiedDate);
                }
            }
            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => [
                    "specifiedDate" => $specifiedDate->toDateString(),
                    "event" => $event ?? null,
                    "is_actual" => $is_actual ?? null,
                    "event_id" => $eventId,
                    "cycle_day" => $dayOfCycle,
                    "pregnancy_chances" => $pregnancy_chances,
                    "nextMenstruationStart" => $nextMenstruationStart ? $nextMenstruationStart->toDateString() : null,
                    "nextMenstruationEnd" => $nextMenstruationEnd ? $nextMenstruationEnd->toDateString() : null,
                    "daysUntilNextMenstruation" => $daysUntilNextMenstruation ?? null,
                    "nextOvulation" => $nextOvulation ? $nextOvulation->toDateString() : null,
                    "daysUntilNextOvulation" => $daysUntilNextOvulation ?? null,
                    "nextFollicularStart" => $nextFollicularStart ? $nextFollicularStart->toDateString() : null,
                    "nextFollicularEnd" => $nextFollicularEnd ? $nextFollicularEnd->toDateString() : null,
                    "daysUntilNextFollicular" => $daysUntilNextFollicular ?? null,
                    "nextFertileStart" => $nextFertileStart ? $nextFertileStart->toDateString() : null,
                    "nextFertileEnd" => $nextFertileEnd ? $nextFertileEnd->toDateString() : null,
                    "daysUntilNextFertile" => $daysUntilNextFertile ?? null,
                    "nextLutealStart" => $nextLutealStart ? $nextLutealStart->toDateString() : null,
                    "nextLutealEnd" => $nextLutealEnd ? $nextLutealEnd->toDateString() : null,
                    "daysUntilNextLuteal" => $daysUntilNextLuteal ?? null
                ]                
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], 400);
        }
    }
}
