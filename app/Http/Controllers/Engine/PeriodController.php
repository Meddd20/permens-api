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
            "first_period" => "required|date",
            "last_period" => "required|date",
            "is_actual" => "required|regex:/^[01]+$/i",
        ];
        $messages = [];
        $attributes = [
            'first_period' => __('attribute.first_period'),
            'last_period' => __('attribute.last_period'),
            'is_actual' => __('attribute.is_actual'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        # Period Validation When Period History is Empty
        $period_history = RiwayatMens::where('user_id', $request->header('user_id'))->get();
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
            $last_period_end = $period_history->sortByDesc('haid_awal')->first()->haid_akhir;
            $period_gaps = Carbon::parse($last_period_end)->diffInDays(Carbon::parse($request->first_period));
            if ($period_gaps < 20) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.period_too_fast'),
                ], 400);
            }
        }

        # Get User Age from User Birthday
        $user = Login::where('id', $request->header('user_id'))->first();

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
        
        # Start & End Period from Input
        $period_start = date("Y-m-d", strtotime($request->first_period));
        $period_end = date("Y-m-d", strtotime($request->last_period));
        
        # Period Duration from Input
        $tmp_period_duration = Carbon::parse($period_end)->diffInDays(Carbon::parse($period_start));

        # Check Period History when Empty
        if (count($period_history) > 0) {
            $period_duration = ($period_history->sum('durasi_haid')+$tmp_period_duration) / ($period_history->count()+1);

            # Check Period Before when Empty
            $period_before = RiwayatMens::where('user_id', $request->header('user_id'))->where('haid_akhir', '<', $request->first_period)->orderBy('haid_akhir', 'DESC')->first();
            if ($period_before == NULL) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.add_period_failed'),
                ], 400);
            } else {
                $period_cycle = Carbon::parse($period_before->haid_akhir)->diffInDays(Carbon::parse($period_start));
                $avg_period_cycle = ($period_history->sum('lama_siklus')+$period_cycle) / ($period_history->count()+1);
            }
            
            $siklus_tengah_haid = $avg_period_cycle / 2;
        } else {
            $period_duration = $tmp_period_duration;
            $period_cycle = $request->period_cycle;
            $avg_period_cycle = $request->period_cycle;
            $siklus_tengah_haid = $request->period_cycle / 2;
        }

        # Ovulasi
        $ovulasi = Carbon::parse($period_start)->addDays($period_cycle - 14)->toDateString();

        # Masa Subur Awal
        $masa_subur_awal = Carbon::parse($ovulasi)->subDays(5)->toDateString();

        # Masa Subur Akhir
        $masa_subur_akhir = Carbon::parse($ovulasi)->addDays(2)->toDateString();

        # Next Period End
        $next_period_end = Carbon::parse($period_end)->addDays($avg_period_cycle + $period_duration)->toDateString();

        # Masa Subur Awal Berikutnya
        $masa_subur_berikutnya_awal = Carbon::parse($next_period_end)->addDays($siklus_tengah_haid - 2)->toDateString();

        # Return Response
        try {
            DB::beginTransaction();
                $period_history = RiwayatMens::create([
                    'user_id' => $request->header('user_id'),
                    'usia' => $age,
                    'usia_lunar' => $lunar_age,
                    'haid_awal' => $period_start,
                    'haid_akhir' => $period_end,
                    'ovulasi' => $ovulasi,
                    'masa_subur_awal' => $masa_subur_awal,
                    'masa_subur_akhir' => $masa_subur_akhir,
                    'mid_period_awal' => Carbon::parse($period_end)->addDays(1)->toDateString(),
                    'mid_period_akhir' => Carbon::parse($masa_subur_awal)->subDays(1)->toDateString(),
                    'tiga_hari_sebelum_masa_subur' => Carbon::parse($masa_subur_awal)->subDays(3)->toDateString(),
                    'lama_siklus' => $period_cycle,
                    'durasi_haid' => $period_duration,
                    'haid_berikutnya_awal' => Carbon::parse($period_end)->addDays($avg_period_cycle)->toDateString(),
                    'haid_berikutnya_akhir' => $next_period_end,
                    'ovulasi_berikutnya' => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    'masa_subur_berikutnya_awal' => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid - 2)->toDateString(),
                    'masa_subur_berikutnya_akhir' => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    'mid_period_berikutnya_awal' => Carbon::parse($next_period_end)->addDays(1)->toDateString(),
                    'mid_period_berikutnya_akhir' => Carbon::parse($masa_subur_berikutnya_awal)->subDays(1)->toDateString(),
                    'tiga_hari_sebelum_masa_ovulasi_berikutnya' => Carbon::parse($masa_subur_berikutnya_awal)->subDays(3)->toDateString(),
                    'is_actual' => $request->is_actual
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
                "message" => $th->getMessage()
            ], 400);
        }
    }

    public function updatePeriod(Request $request)
    {
        # Input Validation
        $rules = [
            "period_id" => "required",
            "first_period" => "required|date",
            "last_period" => "required|date",
            "is_actual" => "required|regex:/^[01]+$/i",
        ];
        $messages = [];
        $attributes = [
            'period_id' => __('attribute.period_id'),
            'first_period' => __('attribute.first_period'),
            'last_period' => __('attribute.last_period'),
            'is_actual' => __('attribute.is_actual'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        # Period Validation When Period History is Empty
        $period_history = RiwayatMens::where('user_id', $request->header('user_id'))->get();
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
            $last_period_end = RiwayatMens::where('user_id', $request->header('user_id'))->orderBy('haid_awal', 'DESC')->get()[1];
            $period_gaps = Carbon::parse($last_period_end['haid_akhir'])->diffInDays(Carbon::parse($request->first_period));
            if ($period_gaps < 20) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.period_too_fast'),
                ], 400);
            }
        }

        # Get User Age from User Birthday
        $user = Login::where('id', $request->header('user_id'))->first();
        $age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->age;
        $lunar_age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->addMonth(9)->age;
        
        # Start & End Period from Input
        $period_end = date("Y-m-d", strtotime($request->last_period));
        $period_start = date("Y-m-d", strtotime($request->first_period));
        
        # Period Duration from Input
        $tmp_period_duration = Carbon::parse($period_end)->diffInDays(Carbon::parse($period_start));

        # Check Period History when Empty
        if (count($period_history) > 0) {
            $period_duration = ($period_history->sum('durasi_haid')+$tmp_period_duration) / ($period_history->count()+1);

            # Check Period Before when Empty
            $period_before = RiwayatMens::where('user_id', $request->header('user_id'))->where('haid_akhir', '<', $request->first_period)->orderBy('haid_akhir', 'DESC')->first();
            if ($period_before == NULL) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.add_period_failed'),
                ], 400);
            } else {
                $period_cycle = Carbon::parse($period_before->haid_akhir)->diffInDays(Carbon::parse($period_start));
                $avg_period_cycle = ($period_history->sum('lama_siklus')+$period_cycle) / ($period_history->count()+1);
            }
            
            $siklus_tengah_haid = $avg_period_cycle / 2;
        } else {
            $period_duration = $tmp_period_duration;
            $period_cycle = $request->period_cycle;
            $avg_period_cycle = $request->period_cycle;
            $siklus_tengah_haid = $request->period_cycle / 2;
        }

        # Masa Subur Awal
        $masa_subur_awal = Carbon::parse($period_end)->addDays($siklus_tengah_haid - 2)->toDateString();

        # Next Period End
        $next_period_end = Carbon::parse($period_end)->addDays($avg_period_cycle + $period_duration)->toDateString();

        # Masa Subur Awal Berikutnya
        $masa_subur_berikutnya_awal = Carbon::parse($next_period_end)->addDays($siklus_tengah_haid - 2)->toDateString();

        # Return Response
        try {
            DB::beginTransaction();
                $period_history = RiwayatMens::where('id', $request->period_id)->update([
                    'user_id' => $request->header('user_id'),
                    'usia' => $age,
                    'usia_lunar' => $lunar_age,
                    'haid_awal' => date("Y-m-d", strtotime($request->first_period)),
                    'haid_akhir' => $period_end,
                    'ovulasi' => Carbon::parse($period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    'masa_subur_awal' => $masa_subur_awal,
                    'masa_subur_akhir' => Carbon::parse($period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    'mid_period_awal' => Carbon::parse($period_end)->addDays(1)->toDateString(),
                    'mid_period_akhir' => Carbon::parse($masa_subur_awal)->subDays(1)->toDateString(),
                    'tiga_hari_sebelum_masa_subur' => Carbon::parse($masa_subur_awal)->subDays(3)->toDateString(),
                    'lama_siklus' => $period_cycle,
                    'durasi_haid' => $period_duration,
                    'haid_berikutnya_awal' => Carbon::parse($period_end)->addDays($avg_period_cycle)->toDateString(),
                    'haid_berikutnya_akhir' => $next_period_end,
                    'ovulasi_berikutnya' => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    'masa_subur_berikutnya_awal' => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid - 2)->toDateString(),
                    'masa_subur_berikutnya_akhir' => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    'mid_period_berikutnya_awal' => Carbon::parse($next_period_end)->addDays(1)->toDateString(),
                    'mid_period_berikutnya_akhir' => Carbon::parse($masa_subur_berikutnya_awal)->subDays(1)->toDateString(),
                    'tiga_hari_sebelum_masa_ovulasi_berikutnya' => Carbon::parse($masa_subur_berikutnya_awal)->subDays(3)->toDateString(),
                    'is_actual' => $request->is_actual
                ]);
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

        # Get User Age from User Birthday
        $user = Login::where('id', $request->header('user_id'))->first();
        $age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->age;
        $lunar_age = Carbon::parse(date("Y-m-d", strtotime($user->tanggal_lahir)))->addMonth(9)->age;

        # Latest Period
        $period = RiwayatMens::where('id', $request->latest_period_id)->first();

        # Period Cycle & Average Period Cycle
        $period_history = RiwayatMens::where('user_id', $request->header('user_id'))->get();
        $period_cycle = Carbon::parse($period->haid_akhir)->diffInDays(Carbon::parse($period->haid_berikutnya_awal));
        $avg_period_cycle = ($period_history->sum('lama_siklus')+$period_cycle) / ($period_history->count()+1);

        # Period Duration & Next Period End
        $period_duration = Carbon::parse($period->haid_berikutnya_awal)->diffInDays(Carbon::parse($period->haid_berikutnya_akhir));
        $next_period_end = Carbon::parse($period->haid_berikutnya_akhir)->addDays($avg_period_cycle+$period_duration)->toDateString();

        # Siklus Tengah Haid
        $siklus_tengah_haid = $avg_period_cycle / 2;

        # Masa Subur Awal Berikutnya
        $masa_subur_berikutnya_awal = Carbon::parse($next_period_end)->addDays($siklus_tengah_haid - 2)->toDateString();

        # Return Response
        try {
            DB::beginTransaction();
                $period_history = RiwayatMens::create([
                    'user_id' => $request->header('user_id'),
                    'usia' => $age,
                    'usia_lunar' => $lunar_age,
                    'haid_awal' => date("Y-m-d", strtotime($period->haid_berikutnya_awal)),
                    'haid_akhir' => $period->haid_berikutnya_akhir,
                    'ovulasi' => $period->ovulasi_berikutnya,
                    'masa_subur_awal' => $period->masa_subur_berikutnya_awal,
                    'masa_subur_akhir' => $period->masa_subur_berikutnya_akhir,
                    'mid_period_awal' => $period->mid_period_berikutnya_awal,
                    'mid_period_akhir' => $period->mid_period_berikutnya_akhir,
                    'tiga_hari_sebelum_masa_subur' => $period->tiga_hari_sebelum_masa_ovulasi_berikutnya,
                    'lama_siklus' => $period_cycle,
                    'durasi_haid' => $period_duration,
                    'haid_berikutnya_awal' => Carbon::parse($period->haid_berikutnya_akhir)->addDays($avg_period_cycle)->toDateString(),
                    'haid_berikutnya_akhir' => $next_period_end,
                    'ovulasi_berikutnya' => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    'masa_subur_berikutnya_awal' => $masa_subur_berikutnya_awal,
                    'masa_subur_berikutnya_akhir' => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    'mid_period_berikutnya_awal' => Carbon::parse($next_period_end)->addDays(1)->toDateString(),
                    'mid_period_berikutnya_akhir' => Carbon::parse($masa_subur_berikutnya_awal)->subDays(1)->toDateString(),
                    'tiga_hari_sebelum_masa_ovulasi_berikutnya' => Carbon::parse($masa_subur_berikutnya_awal)->subDays(3)->toDateString(),
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
