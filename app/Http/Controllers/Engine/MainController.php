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
use App\Models\BeratIdealIbuHamil;
use App\Models\MasterDataVersion;
use App\Models\MasterNewMoon;
use App\Models\RiwayatLog;
use App\Models\RiwayatLogKehamilan;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class MainController extends Controller
{
    public function index(Request $request)
    {
        try {
            # Get User Data (Current Year, User ID, User Age, User Lunar Age)
            $current_year = Carbon::now()->format('Y');
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $age = Carbon::parse($user->tanggal_lahir)->age;
            $lunar_age = $this->calculateLunarAge($age);
    
            # Get Period History (All Period History, Actual Period History, Predction Period History)
            $period_history = RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'DESC')->get();
            $actual_period_history = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->orderBy('haid_awal', 'DESC')->get();
            $prediction_period_history = RiwayatMens::where('user_id', $user_id)->where('is_actual', '0')->orderBy('haid_awal', 'ASC')->get();

            # Get Period Data (Shortest Period, Longest Period, Shortest Cycle, Longest Cycle, Average Period Duration, Averatge Period Cycle)
            $shortest_period = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->orderBy('durasi_haid', 'ASC')->value('durasi_haid');
            $longest_period = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->orderBy('durasi_haid', 'DESC')->value('durasi_haid');
            $shortest_cycle = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->whereNotNull('lama_siklus')->orderBy('lama_siklus', 'ASC')->value('lama_siklus');
            $longest_cycle = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->orderBy('lama_siklus', 'DESC')->value('lama_siklus');
            $avg_period_duration = ceil(RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->orderBy('haid_awal', 'DESC')->avg('durasi_haid'));
            $avg_period_cycle = ceil(RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->orderBy('haid_awal', 'DESC')->avg('lama_siklus'));

            $latest_period_history = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->orderBy('haid_awal', 'DESC')->first();
            if ($latest_period_history && $latest_period_history['hari_terakhir_siklus'] === null) {
                $haid_awal = Carbon::parse($latest_period_history['haid_awal']);
                $today = Carbon::now();
                $lama_siklus = $haid_awal->diffInDays($today);
                $latest_period_history['lama_siklus'] = $lama_siklus;
            }

            if (count($actual_period_history) > 0) {
                foreach ($actual_period_history as $data) {
                    $start_date = Carbon::parse($data->haid_awal)->format('Y-m-d');
                    $end_date = Carbon::parse($data->haid_akhir)->format('Y-m-d');
                    $today = Carbon::now();

                    if (count($actual_period_history) == 1) {
                        $predicted_end_date = Carbon::parse($start_date)->addDays($data->lama_siklus ?? $avg_period_cycle);
                        
                        if ($predicted_end_date->gt($today)) {
                            $period_cycle = ($data->lama_siklus) ? $data->lama_siklus : Carbon::parse($start_date)->diffInDays(Carbon::now());
                        } else {
                            $period_cycle = Carbon::parse($start_date)->diffInDays($today);
                        }
                    } else {
                        $predicted_end_date = Carbon::parse($start_date)->addDays($data->lama_siklus ?? $avg_period_cycle); 
                        if ($predicted_end_date->lt($today)) {
                            $period_cycle = ($data->lama_siklus) ? $data->lama_siklus : Carbon::parse($start_date)->diffInDays(Carbon::now());
                        } else {
                            $period_cycle = $avg_period_cycle;
                        }
                        // $period_cycle = ($data->lama_siklus) ? $data->lama_siklus : Carbon::parse($start_date)->diffInDays(Carbon::now());
                    }
                    
                    if ($start_date == Carbon::now()->format('Y-m-d')) {
                        $period_cycle = $avg_period_cycle;
                    }

                    $period_chart[] = [
                        "id" => $data->id,
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                        "period_cycle" => $period_cycle,
                        "period_duration" => $data->durasi_haid,
                    ];
                }
                usort($period_chart, function ($a, $b) {
                    return strtotime($a['start_date']) - strtotime($b['start_date']);
                });
            } else {
                $period_chart[] = NULL;
            }

            $shettlesGenderPrediction = collect();

            foreach ($period_history as $period) {
                $shettlesGenderPrediction->push([
                    "boyStartDate" => Carbon::parse($period->ovulasi)->toDateString(),
                    "boyEndDate" => Carbon::parse($period->ovulasi)->addDays(3)->toDateString(),
                    "girlStartDate" => Carbon::parse($period->haid_akhir)->addDays(1)->toDateString(),
                    "girlEndDate" => Carbon::parse($period->ovulasi)->subDays(2)->toDateString()
                ]);
            }

            $shettlesGenderPrediction = $shettlesGenderPrediction->sortBy('boyStartDate')->values()->all();

            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => [
                    "initial_year" => Carbon::parse(RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'ASC')->first()->haid_awal ?? '')->format('Y'),
                    "latest_year" => Carbon::parse(RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'DESC')->first()->haid_awal ?? '')->format('Y'),
                    "current_year" => $current_year,
                    "age" => $age,
                    "lunar_age" => $lunar_age,
                    "shortest_period" => $shortest_period,
                    "longest_period" => $longest_period,
                    "shortest_cycle" => $shortest_cycle,
                    "longest_cycle" => $longest_cycle,
                    "avg_period_duration" => $avg_period_duration,
                    "avg_period_cycle" => $avg_period_cycle,
                    "period_chart" => $period_chart,
                    "latest_period_history" => $latest_period_history,
                    "period_history" => $period_history,
                    "actual_period" => $actual_period_history,
                    "prediction_period" => $prediction_period_history,
                    "shettlesGenderPrediction" => $shettlesGenderPrediction
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function pregnancyIndex(Request $request) {
        try {
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $lang = $request->header('lang');

            $pregnancy_history = RiwayatKehamilan::where('user_id', $user_id)->get();
            $currently_pregnant = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->get();
            $pregnancy_count = $currently_pregnant->count();

            if ($pregnancy_count < 1) {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.pregnant_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }

            $pregnant_info[] = [];

            foreach ($currently_pregnant as $pregnancy) {
                $week_data = [];
            
                $hari_pertama_haid_terakhir = $pregnancy->hari_pertama_haid_terakhir;
                $usia_kehamilan_sekarang = Carbon::now()->diffInWeeks($hari_pertama_haid_terakhir) + 1;

                for ($minggu = 1; $minggu <= 40; $minggu++) {
                    $data_kehamilan_mingguan = MasterKehamilan::where('minggu_kehamilan', $minggu)->first();
                    $minggu_sisa = 40 - $minggu;

                    $tanggal_awal_minggu = Carbon::parse($hari_pertama_haid_terakhir)->addDays(($minggu - 1) * 7)->format('Y-m-d');
                    $tanggal_akhir_minggu = Carbon::parse($hari_pertama_haid_terakhir)->addDays($minggu * 7 - 1)->format('Y-m-d');

                    if ($minggu <= 12) {
                        $trimester = 1;
                    } elseif ($minggu <= 27) {
                        $trimester = 2;
                    } else {
                        $trimester = 3;
                    }

                    if ($lang == "id") {
                        $week_data[] = [
                            "minggu_kehamilan" => $minggu,
                            "trimester" => $trimester,
                            "minggu_sisa" => $minggu_sisa,
                            "minggu_label" => ($minggu < $usia_kehamilan_sekarang) ? ($usia_kehamilan_sekarang - $minggu) . " minggu lalu" : (($minggu > $usia_kehamilan_sekarang) ? ($minggu - $usia_kehamilan_sekarang) . " minggu ke depan" : "Minggu ini"),
                            "tanggal_awal_minggu" => $tanggal_awal_minggu,
                            "tanggal_akhir_minggu" => $tanggal_akhir_minggu,
                            "berat_janin" => $data_kehamilan_mingguan->berat_janin,
                            "tinggi_badan_janin" => $data_kehamilan_mingguan->tinggi_badan_janin,
                            "poin_utama" => $data_kehamilan_mingguan->poin_utama_id,
                            "ukuran_bayi" => $data_kehamilan_mingguan->ukuran_bayi_id,
                            "perkembangan_bayi" => $data_kehamilan_mingguan->perkembangan_bayi_id,
                            "perubahan_tubuh" => $data_kehamilan_mingguan->perubahan_tubuh_id,
                            "gejala_umum" => $data_kehamilan_mingguan->gejala_umum_id,
                            "tips_mingguan" => $data_kehamilan_mingguan->tips_mingguan_id,
                            "bayi_img_path" => $data_kehamilan_mingguan->bayi_img_path,
                            "ukuran_bayi_img_path" => $data_kehamilan_mingguan->ukuran_bayi_img_path,
                        ];
                    } else {
                        $week_data[] = [
                            "minggu_kehamilan" => $minggu,
                            "trimester" => $trimester,
                            "minggu_sisa" => $minggu_sisa,
                            "minggu_label" => ($minggu < $usia_kehamilan_sekarang) ? ($usia_kehamilan_sekarang - $minggu) . " weeks ago" : (($minggu > $usia_kehamilan_sekarang) ? ($minggu - $usia_kehamilan_sekarang) . " weeks ahead" : "Current week"),
                            "tanggal_awal_minggu" => $tanggal_awal_minggu,
                            "tanggal_akhir_minggu" => $tanggal_akhir_minggu,
                            "berat_janin" => $data_kehamilan_mingguan->berat_janin,
                            "tinggi_badan_janin" => $data_kehamilan_mingguan->tinggi_badan_janin,
                            "poin_utama" => $data_kehamilan_mingguan->poin_utama_en,
                            "ukuran_bayi" => $data_kehamilan_mingguan->ukuran_bayi_en,
                            "perkembangan_bayi" => $data_kehamilan_mingguan->perkembangan_bayi_en,
                            "perubahan_tubuh" => $data_kehamilan_mingguan->perubahan_tubuh_en,
                            "gejala_umum" => $data_kehamilan_mingguan->gejala_umum_en,
                            "tips_mingguan" => $data_kehamilan_mingguan->tips_mingguan_en,
                            "bayi_img_path" => $data_kehamilan_mingguan->bayi_img_path,
                            "ukuran_bayi_img_path" => $data_kehamilan_mingguan->ukuran_bayi_img_path,
                        ];
                    }
                }

                $pregnancy_info[] = [
                    "pregnancy_id" => $pregnancy->id,
                    "status" => $pregnancy->status,
                    "hari_pertama_haid_terakhir" => $hari_pertama_haid_terakhir,
                    "tanggal_perkiraan_lahir" => $pregnancy->tanggal_perkiraan_lahir,
                    "usia_kehamilan" => $usia_kehamilan_sekarang,
                    "weekly_data" => $week_data,
                ];
            }

            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => [
                    "pregnancy_history" => $pregnancy_history,
                    "currently_pregnant" => $pregnancy_info,
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function filter(Request $request)
    {
        # Input Validation
        $validated = $request->validate([
            'year' => 'required|date_format:Y|before_or_equal:' . now()->year
        ]);

        try {
            # Get User Data (Current Year, User ID, User Age, User Lunar Age)
            $current_year = $request->year;
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $age = Carbon::parse($user->tanggal_lahir)->age;
            $lunar_age = $this->calculateLunarAge($age);
    
            # Get Period History (All Period History, Actual Period History, Predction Period History)
            $period_history = RiwayatMens::where('user_id', $user_id)->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->get();
            $actual_period_history = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->get();
            $prediction_period_history = RiwayatMens::where('user_id', $user_id)->where('is_actual', '0')->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->get();

            if (count($actual_period_history) > 0) {
                foreach ($actual_period_history as $data) {
                    $start_date = Carbon::parse($data->haid_awal)->format('Y-m-d');
                    $end_date = Carbon::parse($data->haid_akhir)->format('Y-m-d');
                    
                    if (count($actual_period_history) == 1) {
                        $predicted_end_date = Carbon::parse($start_date)->addDays($data->lama_siklus ?? 0);
                        $today = Carbon::now();

                        if ($predicted_end_date->gte($today)) {
                            $period_cycle = ($data->lama_siklus) ? $data->lama_siklus : Carbon::parse($start_date)->diffInDays(Carbon::now());
                        } else {
                            $period_cycle = Carbon::parse($start_date)->diffInDays($today);
                        }
                    } else {
                        $period_cycle = ($data->lama_siklus) ? $data->lama_siklus : Carbon::parse($start_date)->diffInDays(Carbon::now());
                    }

                    $period_chart[] = [
                        "id" => $data->id,
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                        "period_cycle" => $period_cycle,
                        "period_duration" => $data->durasi_haid,
                    ];
                }
            } else {
                $period_chart[] = NULL;
            }

            # Get Period Data (Shortest Period, Longest Period, Shortest Cycle, Longest Cycle, Average Period Duration, Averatge Period Cycle)
            $shortest_period = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->whereYear('haid_awal', $current_year)->orderBy('lama_siklus', 'ASC')->value('lama_siklus');
            $longest_period = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->whereYear('haid_awal', $current_year)->orderBy('lama_siklus', 'DESC')->value('lama_siklus');
            $shortest_cycle = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->whereYear('haid_awal', $current_year)->orderBy('durasi_haid', 'ASC')->value('lama_siklus');
            $longest_cycle = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->whereYear('haid_awal', $current_year)->orderBy('durasi_haid', 'DESC')->value('lama_siklus');
            $avg_period_duration = ceil(RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->avg('durasi_haid'));
            $avg_period_cycle = ceil(RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->avg('lama_siklus'));
            
            $latest_period_history = RiwayatMens::where('user_id', $user_id)->where('is_actual', '1')->whereYear('haid_awal', $current_year)->orderBy('haid_awal', 'DESC')->first();

            if ($latest_period_history && $latest_period_history['hari_terakhir_siklus'] === null) {
                $haid_awal = Carbon::parse($latest_period_history['haid_awal']);
                $today = Carbon::now();
                $lama_siklus = $haid_awal->diffInDays($today);
                $latest_period_history['lama_siklus'] = $lama_siklus;
            }
    
            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => [
                    "initial_year" => Carbon::parse(RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'ASC')->first()->haid_awal ?? '')->format('Y'),
                    "latest_year" => Carbon::parse(RiwayatMens::where('user_id', $user_id)->orderBy('haid_awal', 'DESC')->first()->haid_awal ?? '')->format('Y'),
                    "current_year" => $current_year,
                    "age" => $age,
                    "lunar_age" => $lunar_age,
                    "shortest_period" => $shortest_period,
                    "longest_period" => $longest_period,
                    "shortest_cycle" => $shortest_cycle,
                    "longest_cycle" => $longest_cycle,
                    "avg_period_duration" => $avg_period_duration,
                    "avg_period_cycle" => $avg_period_cycle,
                    "period_chart" => $period_chart,
                    "latest_period_history" => $latest_period_history,
                    "period_history" => $period_history,
                    "actual_period" => $actual_period_history,
                    "prediction_period" => $prediction_period_history,
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                "status" => "failed",
                "message" => __('response.'),
            ], Response::HTTP_NOT_FOUND);
        }

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $periodHistory = RiwayatMens::where('user_id', $user_id)
            ->orderBy('haid_awal', 'asc')
            ->get();
        $recordsCount = count($periodHistory);

        // Determine events on the specified date
        $specifiedDate = Carbon::parse($request->input('date_selected'));

        if ($specifiedDate->greaterThan('2030-12-31') || $specifiedDate->lessThan('1979-01-01')) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.range_date'),
            ], Response::HTTP_BAD_REQUEST);
        }

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
        $lutealEnd = null;
        $dayOfCycle = null;
        $currentIsActual = null;
        $shettlesGenderPrediction = null;

        try {
            foreach ($periodHistory as $key => $period) {
                $currentIsActual = $period->is_actual;
            
                // Check if there is a next record
                if ($key < $recordsCount - 1) {
                    $nextRecord = $periodHistory[$key + 1];
                    $nextIsActual = $nextRecord->is_actual;
            
                    // Now you can check $currentIsActual and $nextIsActual
                    if ($currentIsActual == 1 && $nextIsActual == 1) {
                        $lutealEnd = Carbon::parse($period->hari_terakhir_siklus);
                    } elseif ($currentIsActual == 0 && $nextIsActual == 0) {
                        $lutealEnd = Carbon::parse($period->hari_terakhir_siklus);
                    } elseif ($currentIsActual == 1 && $nextIsActual == 0) {
                        $lutealEnd = Carbon::parse($period->haid_berikutnya_awal)->subDays(1);
                    }
                } else {
                    // Handling the last record
                    if ($currentIsActual == 0) {
                        $lutealEnd = Carbon::parse($period->hari_terakhir_siklus);
                    }
                }

                $periodStart = Carbon::parse($period->haid_awal);
                $periodEnd = Carbon::parse($period->haid_akhir);
                $ovulation = Carbon::parse($period->ovulasi);
                $fertileStart = Carbon::parse($period->masa_subur_awal);
                $fertileEnd = Carbon::parse($period->masa_subur_akhir);
                $follicularStart = Carbon::parse($periodEnd)->addDays(1);
                $follicularEnd = Carbon::parse($fertileStart)->subDays(1);
                $lutealStart = Carbon::parse($fertileEnd)->addDays(1);
                
                if ($currentIsActual == '1') {
                    $firstDayOfMenstruation = Carbon::parse($period->haid_awal);
                } else {
                    $last_actual_period = RiwayatMens::where('user_id', $user_id)
                                ->where('is_actual', '1')
                                ->where('haid_awal', '<=', $period->haid_awal)
                                ->where('id', '!=', $period->id)
                                ->orderBy('haid_awal', 'DESC')
                                ->first();
                    $firstDayOfMenstruation = Carbon::parse($last_actual_period->haid_awal);
                }
                $dayOfCycle = Carbon::parse($firstDayOfMenstruation)->diffInDays($specifiedDate) + 1;

                if ($specifiedDate->between($periodStart, $lutealEnd)) {
                    $shettlesGenderPrediction = [
                        "boyStartDate" => Carbon::parse($ovulation)->toDateString(),
                        "boyEndDate" => Carbon::parse($ovulation)->addDays(3)->toDateString(),
                        "girlStartDate" => Carbon::parse($periodEnd)->addDays(1)->toDateString(),
                        "girlEndDate" => Carbon::parse($ovulation)->subDays(2)->toDateString()
                    ];
                }

                if ($specifiedDate->between($periodStart, $periodEnd)) {
                    $event = 'Menstruation Phase';
                    $pregnancy_chances = "Low";
                    $currentIsActual = $period->is_actual;
                    $eventId = $period->id;
                    
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

                    $nextFollicularStart = Carbon::parse($period->haid_akhir)->addDays(1);
                    $nextFollicularEnd = Carbon::parse($period->masa_subur_awal)->subDays(1);
                    $daysUntilNextFollicular = $nextFollicularStart->diffInDays($specifiedDate);

                    $nextFertileStart = Carbon::parse($period->masa_subur_awal);
                    $nextFertileEnd = Carbon::parse($period->masa_subur_akhir);
                    $daysUntilNextFertile = $nextFertileStart->diffInDays($specifiedDate);

                    $nextLutealStart = Carbon::parse($period->masa_subur_akhir)->addDays(1);
                    $nextLutealEnd = Carbon::parse($nextRecord->haid_awal)->subDays(1);
                    $daysUntilNextLuteal = $nextLutealStart->diffInDays($specifiedDate);
                    break;
                }

                if ($specifiedDate->between($fertileStart, $fertileEnd)) {

                    if ($specifiedDate->equalTo($ovulation)) {
                        $event = 'Ovulation';
                    } else {
                        $event = 'Fertile Phase';
                    }
                    $pregnancy_chances = "High";
                    $currentIsActual = $period->is_actual;
                    $eventId = $period->id;

                    // Check if it's the last record
                    if ($key == $recordsCount - 1) {
                        $nextMenstruationStart = Carbon::parse($period->haid_berikutnya_awal);
                        $nextMenstruationEnd = Carbon::parse($period->haid_berikutnya_akhir);
                        $nextFollicularStart = Carbon::parse($period->haid_berikutnya_akhir)->addDays(1);
                        $nextFollicularEnd = Carbon::parse($period->masa_subur_berikutnya_awal)->subDays(1);
                        $nextFertileStart = Carbon::parse($period->masa_subur_berikutnya_awal);
                        $nextFertileEnd = Carbon::parse($period->masa_subur_berikutnya_akhir);
                        $nextLutealEnd = Carbon::parse($period->haid_berikutnya_awal)->subDays(1);

                        if ($specifiedDate < $ovulation) {
                            $nextOvulation = Carbon::parse($period->ovulasi);
                        } else {
                            $nextOvulation = Carbon::parse($period->ovulasi_berikutnya);
                        }
                    } else {
                        $nextMenstruationStart = Carbon::parse($nextRecord->haid_awal);
                        $nextMenstruationEnd = Carbon::parse($nextRecord->haid_akhir);
                        $nextFollicularStart = Carbon::parse($nextRecord->haid_akhir)->addDays(1);
                        $nextFollicularEnd = Carbon::parse($nextRecord->masa_subur_awal)->subDays(1);
                        $nextFertileStart = Carbon::parse($nextRecord->masa_subur_awal);
                        $nextFertileEnd = Carbon::parse($nextRecord->masa_subur_akhir);
                        $nextLutealEnd = Carbon::parse($period->haid_terakhir_siklus);

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

                    $nextLutealStart = Carbon::parse($period->masa_subur_akhir)->addDays(1);
                    $daysUntilNextLuteal = $nextLutealStart->diffInDays($specifiedDate);
                    break;
                }

                if ($specifiedDate->between($follicularStart, $follicularEnd)) {
                    $event = 'Follicular Phase';
                    $pregnancy_chances = "Low";
                    $currentIsActual = $period->is_actual;
                    $eventId = $period->id;
                    
                    // Check if it's the last record
                    if ($key == $recordsCount - 1) {
                        $nextMenstruationStart = Carbon::parse($period->haid_berikutnya_awal);
                        $nextMenstruationEnd = Carbon::parse($period->haid_berikutnya_akhir);
                        $nextFollicularStart = Carbon::parse($period->haid_berikutnya_akhir)->addDays(1);
                        $nextFollicularEnd = Carbon::parse($period->masa_subur_berikutnya_awal)->subDays(1);
                    } else {
                        $nextMenstruationStart = Carbon::parse($nextRecord->haid_awal);
                        $nextMenstruationEnd = Carbon::parse($nextRecord->haid_akhir);
                        $nextFollicularStart = Carbon::parse($nextRecord->haid_akhir)->addDays(1);
                        $nextFollicularEnd = Carbon::parse($nextRecord->masa_subur_awal)->subDays(1);
                    }
                    $daysUntilNextMenstruation = $nextMenstruationStart->diffInDays($specifiedDate);
                    $daysUntilNextFollicular = $nextFollicularStart->diffInDays($specifiedDate);

                    $nextOvulation = Carbon::parse($period->ovulasi);
                    $daysUntilNextOvulation = $nextOvulation->diffInDays($specifiedDate);

                    $nextFertileStart = Carbon::parse($period->masa_subur_awal);
                    $nextFertileEnd = Carbon::parse($period->masa_subur_akhir);
                    $daysUntilNextFertile = $nextFertileStart->diffInDays($specifiedDate);

                    $nextLutealStart = Carbon::parse($period->masa_subur_akhir);
                    $nextLutealEnd = Carbon::parse($nextRecord->haid_awal);
                    $daysUntilNextLuteal = $nextLutealStart->diffInDays($specifiedDate);
                    break;
                }

                if ($specifiedDate->between($lutealStart, $lutealEnd)) {
                    $event = 'Luteal Phase';
                    $pregnancy_chances = "Low";
                    $currentIsActual = $period->is_actual;
                    $eventId = $period->id;

                    // Check if it's the last record
                    if ($key == $recordsCount - 1) {
                        $nextMenstruationStart = Carbon::parse($period->haid_berikutnya_awal);
                        $nextMenstruationEnd = Carbon::parse($period->haid_berikutnya_akhir);
                        $nextFollicularStart = Carbon::parse($period->haid_berikutnya_akhir)->addDays(1);
                        $nextFollicularEnd = Carbon::parse($period->masa_subur_berikutnya_awal)->subDays(1);
                        $nextFertileStart = Carbon::parse($period->masa_subur_berikutnya_awal);
                        $nextFertileEnd = Carbon::parse($period->masa_subur_berikutnya_akhir);
                        $nextOvulation = Carbon::parse($period->ovulasi_berikutnya);
                        $nextLutealStart = Carbon::parse($period->masa_subur_berikutnya_akhir)->addDays(1);
                        $nextLutealEnd = Carbon::parse($period->hari_terakhir_siklus_berikutnya);
                    } else {
                        $nextMenstruationStart = Carbon::parse($nextRecord->haid_awal);
                        $nextMenstruationEnd = Carbon::parse($nextRecord->haid_akhir);
                        $nextFollicularStart = Carbon::parse($nextRecord->haid_akhir)->addDays(1);
                        $nextFollicularEnd = Carbon::parse($nextRecord->masa_subur_awal)->subDays(1);
                        $nextFertileStart = Carbon::parse($nextRecord->masa_subur_awal);
                        $nextFertileEnd = Carbon::parse($nextRecord->masa_subur_akhir);
                        $nextOvulation = Carbon::parse($nextRecord->ovulasi);
                        $nextLutealStart = Carbon::parse($nextRecord->masa_subur_akhir)->addDays(1);
                        $nextLutealEnd = Carbon::parse($nextRecord->haid_berikutnya_awal)->subDays(1);
                    }
                    $daysUntilNextMenstruation = $nextMenstruationStart->diffInDays($specifiedDate);
                    $daysUntilNextOvulation = $nextOvulation->diffInDays($specifiedDate);
                    $daysUntilNextFollicular = $nextFollicularStart->diffInDays($specifiedDate);
                    $daysUntilNextFertile = $nextFertileStart->diffInDays($specifiedDate);
                    $daysUntilNextLuteal = $nextLutealStart->diffInDays($specifiedDate);
                    break;
                }                
            }

            $motherDateOfBirth = Carbon::parse($user->tanggal_lahir);
            $motherAge = $motherDateOfBirth->age;
            $motherLunarAge = $this->calculateLunarAge($motherAge, $specifiedDate);
            $lunarSpecifiedDate = $this->calculateLunarDate($specifiedDate->toDateString());
            $chineseGenderPrediction = null;

            if ($motherAge > 18) {
                $chineseGenderPrediction = [
                    "age" => $motherAge,
                    "lunarAge" => $motherLunarAge,
                    "dateOfBirth" => $motherDateOfBirth->toDateString(),
                    "lunarDateOfBirth" => $this->calculateLunarDate($motherDateOfBirth->toDateString()),
                    "specifiedDate" => $specifiedDate->toDateString(),
                    "lunarSpecifiedDate" => $lunarSpecifiedDate,
                    "genderPrediction" => $this->chineseCalendarGenderPrediction($lunarSpecifiedDate, $motherLunarAge),
                ] ;
            } 

            # Return Response
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => [
                    "specifiedDate" => $specifiedDate->toDateString(),
                    "event" => $event ?? null,
                    "is_actual" => $currentIsActual,
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
                    "daysUntilNextLuteal" => $daysUntilNextLuteal ?? null,
                    "chineseGenderPrediction" => $chineseGenderPrediction,
                    "shettlesGenderPrediction" => $shettlesGenderPrediction
                ]                
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function syncData(Request $request) {
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $period_history = RiwayatMens::where("user_id", $user_id)->orderBy('haid_awal', 'ASC')->get();
        $pregnancy_history = RiwayatKehamilan::where("user_id", $user_id)->where('status', 'Hamil')->first();
        $log_history = RiwayatLog::where("user_id", $user_id)->first();
        $pregnancy_log_history = RiwayatLogKehamilan::where("user_id", $user_id)->where("riwayat_kehamilan_id", $pregnancy_history->id)->first();
        $weight_gain_history = BeratIdealIbuHamil::where("user_id", $user_id)->where("riwayat_kehamilan_id", $pregnancy_history->id)->orderBy('minggu_kehamilan', 'ASC')->orderBy('tanggal_pencatatan', 'ASC')->get();
        $master_data_version = MasterDataVersion::all();

        $data = [
            "user" => $user,
            "period_history" => $period_history,
            "pregnancy_history" => $pregnancy_history,
            "log_history" => $log_history,
            "pregnancy_log_history" => $pregnancy_log_history,
            "weight_gain_history" => $weight_gain_history,
            "master_data_version" => $master_data_version,
        ];

        return response()->json([
            "status" => "success",
            "message" => __('response.getting_data'),
            "data" => $data
        ], Response::HTTP_OK);
    }

    private function calculateLunarAge($age, $calculateOn = null) {
        $chinese_new_year = [
            '1930-01-29', '1931-02-17', '1932-02-06', '1933-01-26', '1934-02-14',
            '1935-02-04', '1936-01-24', '1937-02-11', '1938-01-31', '1939-02-19',
            '1940-02-08', '1941-01-27', '1942-02-15', '1943-02-04', '1944-01-25',
            '1945-02-13', '1946-02-01', '1947-01-22', '1948-02-10', '1949-01-29',
            '1950-02-17', '1951-02-06', '1952-01-27', '1953-02-14', '1954-02-03',
            '1955-01-24', '1956-02-12', '1957-01-31', '1958-02-18', '1959-02-08',
            '1960-01-28', '1961-02-15', '1962-02-05', '1963-01-25', '1964-02-13',
            '1965-02-02', '1966-01-21', '1967-02-09', '1968-01-30', '1969-02-17',
            '1970-02-06', '1971-01-27', '1972-02-15', '1973-02-03', '1974-01-23',
            '1975-02-11', '1976-01-31', '1977-02-18', '1978-02-07', '1979-01-28',
            '1980-02-16', '1981-02-05', '1982-01-25', '1983-02-12', '1984-02-02',
            '1985-02-20', '1986-02-09', '1987-01-29', '1988-02-17', '1989-02-06',
            '1990-01-27', '1991-02-14', '1992-02-04', '1993-01-22', '1994-02-10',
            '1995-01-31', '1996-02-19', '1997-02-07', '1998-01-28', '1999-02-16',
            '2000-02-05', '2001-01-24', '2002-02-12', '2003-02-01', '2004-01-22',
            '2005-02-09', '2006-01-29', '2007-02-18', '2008-02-07', '2009-01-26',
            '2010-02-14', '2011-02-03', '2012-01-23', '2013-02-10', '2014-01-31',
            '2015-02-19', '2016-02-08', '2017-01-28', '2018-02-16', '2019-02-05',
            '2020-01-25', '2021-02-12', '2022-02-01', '2023-01-22', '2024-02-10',
            '2025-01-29', '2026-02-17', '2027-02-06', '2028-01-26', '2029-02-13',
            '2030-02-03'
        ];
    
        $current_date = Carbon::now();
        $current_year = $current_date->year;
        $matching_date = null;

        foreach ($chinese_new_year as $cnyDate) {
            $cnyYear = Carbon::parse($cnyDate)->year;

            if ($cnyYear == $current_year) {
                $matching_date = Carbon::parse($cnyDate);
                break;
            }
        }

        $lunar_age = $age;

        if ($calculateOn === null) {
            $lunar_age += $current_date->gt($matching_date) ? 2 : 1;
        } else {
            $lunar_age += ($current_date->gt($matching_date) && $calculateOn->gt($matching_date)) ? 2 : 1;
        }

        return $lunar_age;
    }

    private function calculateLunarDate($date) {
        $previousNewMoonData = MasterNewMoon::where('new_moon', '<', $date)
                                         ->orderBy('new_moon', 'desc')
                                         ->first();
        $previousNewMoon = $previousNewMoonData->new_moon;
        $lunarYear = Carbon::parse($previousNewMoon)->year;
        $lunarMonth = $previousNewMoonData->lunar_month;
        $lunarDays = Carbon::parse($previousNewMoon)->diffInDays($date) + 1;
        $lunarDate = Carbon::createFromDate($lunarYear, $lunarMonth, 0)->addDays($lunarDays)->format('Y-m-d');
        return $lunarDate;
    }

    private function chineseCalendarGenderPrediction($date, $lunarAge) {
        $lunarMonth = Carbon::parse($date)->month;
        $predictedBabyGender = MasterGender::where('usia', $lunarAge)
                                ->where('bulan', $lunarMonth)
                                ->value('gender');
        return $predictedBabyGender;
    }
}
