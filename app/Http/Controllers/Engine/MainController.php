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
use Illuminate\Support\Facades\Validator;

class MainController extends Controller
{
    public function index(Request $request)
    {
        try {
            # Get User Data (Current Year, User ID, User Age, User Lunar Age)
            $current_year = Carbon::now()->format('Y');
            $user = Login::where('id', $request->header('user_id'))->first();
            $user_id = $user->id;
            $user_age = Carbon::parse($user->tanggal_lahir)->age;
            $user_lunar_age = Carbon::parse($user->tanggal_lahir)->subMonth(9)->age;
    
            # Get Chart Data (Period Duration Chart, Period Cycle Chart, Chart Date)
            $period_history = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->get();
            if (count($period_history) > 0) {
                foreach ($period_history as $data) {
                    $period_duration_chart[] = $data->durasi_haid;
                    $period_cycle_chart[] = $data->lama_siklus;
                    $period_chart_date[] = Carbon::parse($data->haid_awal ?? '')->format('d M').' - '.Carbon::parse($data->haid_akhir)->format('d M');
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
            $latest_period_history = RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'DESC')->first();
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
            $user = Login::where('id', $request->header('user_id'))->first();
            $user_id = $user->id;
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
        if ($latest_period_history->user_id != $request->header("user_id")) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.period_history_not_found'),
            ], 400);
        }

        try {
            # Get User Data (Current Year, User ID, User Age, User Lunar Age)
            $last_year = Carbon::now()->format('Y');
            $current_year = Carbon::parse($latest_period_history->haid_awal)->format('Y');
            $user = Login::where('id', $request->header('user_id'))->first();
            $user_id = $user->id;
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
}
