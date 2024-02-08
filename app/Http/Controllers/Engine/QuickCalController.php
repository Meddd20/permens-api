<?php

namespace App\Http\Controllers\Engine;

use Carbon\Carbon;
use App\Models\MasterGender;
use Illuminate\Http\Request;
use App\Models\MasterKehamilan;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class QuickCalController extends Controller
{
    public function calc(Request $request)
    {
        # Input Validation
        $rules = [
            'day_after_period' => "required|date",
            'period_cycle' => "required|regex:/^[0-9]+$/i|max:2",
            'period_duration' => "required|regex:/^[0-9]+$/i|max:2",
            'birthday' => "required|date"
        ];
        $messages = [];
        $attributes = [
            'day_after_period' => __('attribute.day_after_period'),
            'period_cycle' => __('attribute.period_cycle'),
            'period_duration' => __('attribute.period_duration'),
            'birthday' => __('attribute.birthday'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()
            ], 400);
        }

        # Get User Age
        $age = Carbon::parse($request->birthday)->age;
        $lunar_age = Carbon::parse($request->birthday)->addMonth(9)->age;

        # Age Validation
        if ($age < 18) {
            return response()->json([
                'status' => 'failed',
                'message' => __('response.age_validation')
            ], 400);
        }

        try {
            # Get Data
            $today = Carbon::now();
            $hpht = date("Y-m-d", strtotime($request->day_after_period));
            $period_end = Carbon::parse($hpht)->subDays(1)->toDateString();
            $siklus_tengah_haid = $request->period_cycle / 2;
    
            $thn_sebelumnya = Carbon::parse($hpht)->addYears(1)->toDateString();
            $tambah_minggu = Carbon::parse($thn_sebelumnya)->addDays(7)->toDateString();
            $pregnancy_start = Carbon::parse($hpht)->addDays(14)->toDateString();
            
            $bulan_berhubungan = (Carbon::parse($period_end)->addDays($siklus_tengah_haid))->format('m');
            
            # Get Pregnancy Information
            $pregnancy_weeks = $today->diffInWeeks($pregnancy_start);
            if ($pregnancy_weeks < 8) {
                $berat_janin = 0;
                $pertambahan_berat_ibu = 0;
                $tinggi_badan_janin = 0;
            } else {
                $master_kehamilan = MasterKehamilan::where('minggu_kehamilan', $pregnancy_weeks)->first();
                $berat_janin = $master_kehamilan->berat_janin;
                $tinggi_badan_janin = $master_kehamilan->tinggi_badan_janin;
            }
    
            # Masa Subur Awal
            $masa_subur_awal = Carbon::parse($period_end)->addDays($siklus_tengah_haid - 2)->toDateString();

            $next_period_end = Carbon::parse($period_end)->addDays($request->period_cycle + $request->period_duration)->toDateString();

            # Masa Subur Awal Berikutnya
            $masa_subur_berikutnya_awal = Carbon::parse($next_period_end)->addDays($siklus_tengah_haid - 2)->toDateString();
    
            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.instans_calc'),
                "data" => [
                    "haid_pertama_awal" => Carbon::parse($period_end)->subDays($request->period_duration)->toDateString(),
                    "haid_pertama_akhir" => $period_end,
                    "masa_subur_awal" => Carbon::parse($period_end)->addDays($siklus_tengah_haid - 2)->toDateString(),
                    "masa_subur_akhir" => Carbon::parse($period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    "mid_period_awal" => Carbon::parse($period_end)->addDays(1)->toDateString(),
                    "mid_period_akhir" => Carbon::parse($masa_subur_awal)->subDays(1)->toDateString(),
                    "tiga_hari_sebelum_masa_subur" => Carbon::parse($masa_subur_awal)->subDays(3)->toDateString(),
                    "ovulasi" => Carbon::parse($period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    "haid_kedua_awal" => Carbon::parse($period_end)->addDays($request->period_cycle)->toDateString(),
                    "haid_kedua_akhir" => $next_period_end,
                    "masa_subur_berikutnya_awal" => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid - 2)->toDateString(),
                    "masa_subur_berikutnya_akhir" => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    "ovulasi_berikutnya" => Carbon::parse($next_period_end)->addDays($siklus_tengah_haid)->toDateString(),
                    "mid_period_berikutnya_awal" => Carbon::parse($next_period_end)->addDays(1)->toDateString(),
                    "mid_period_berikutnya_akhir" => Carbon::parse($masa_subur_berikutnya_awal)->subDays(1)->toDateString(),
                    "tiga_hari_sebelum_masa_ovulasi_berikutnya" => Carbon::parse($masa_subur_berikutnya_awal)->subDays(3)->toDateString(),
                    "usia_ibu" => $age,
                    "usia_lunar_ibu" => $lunar_age,
                    "usia_kehamilan" => $pregnancy_weeks,
                    "kehamilan_awal" => $pregnancy_start,
                    "kehamilan_akhir" => Carbon::parse($tambah_minggu)->subMonths(3)->toDateString(),
                    "bb_rata_rata_janin_gr" => $berat_janin,
                    "pertambahan_bb_rata_rata_ibu_kg" => $pertambahan_berat_ibu,
                    "tb_rata_rata_janin_cm" => $tinggi_badan_janin,
                    "gender" => MasterGender::where('usia', $lunar_age)->where('bulan', $bulan_berhubungan)->first(),
                    "master_gender" => MasterGender::where('usia', $lunar_age)->get()
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'failed',
                'message' => "Failed to get data.".$th->getMessage()
            ], 400);
        }
    }
}
