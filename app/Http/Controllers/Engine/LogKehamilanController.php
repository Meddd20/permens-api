<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Login;
use App\Models\RiwayatKehamilan;
use App\Models\RiwayatLogKehamilan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class LogKehamilanController extends Controller
{
    public function addPregnancyLog(Request $request) {
        $pregnancySymptoms = [
            "Altered Body Image", "Anxiety", "Back Pain", "Breast Pain", "Brownish Marks on Face", "Carpal Tunnel", "Changes in Libido", "Changes in Nipples", "Constipation", "Dizziness", "Dry Mouth", "Fainting", "Feeling Depressed", "Food Cravings", "Forgetfulness", "Greasy Skin/Acne", "Haemorrhoids", "Headache", "Heart Palpitations", "Hip/Pelvic Pain", "Increased Vaginal Discharge", 
            "Incontinence/Leaking Urine", "Itchy Skin", "Leg Cramps", "Nausea", "Painful Vaginal Veins", "Poor Sleep", "Reflux", "Restless Legs", "Shortness of Breath", "Sciatica", "Snoring", "Sore Nipples", "Stretch Marks", "Swollen Hands/Feet", "Taste/Smell Changes", "Thrush", "Tiredness/Fatigue", "Urinary Frequency", "Varicose Veins", "Vivid Dreams", "Vomiting" 
        ];

        $rules = [
            "date" => "required|date|before:now",
            "pregnancy_symptoms" => [
                "required",
                "json",
                function ($attribute, $value, $fail) use ($pregnancySymptoms) {
                    $decodedValue = json_decode($value, true);

                    if ($decodedValue === null) {
                        $fail("The $attribute format is invalid. JSON decoding failed.");
                        return;
                    }
        
                    if (!is_array($decodedValue) || count($decodedValue) !== count($pregnancySymptoms)) {
                        $fail("The $attribute format is invalid.");
                    }
        
                    foreach ($decodedValue as $symptom => $status) {
                        if (!in_array($symptom, $pregnancySymptoms)) {
                            $fail("Invalid symptom '$symptom' in $attribute.");
                        }
        
                        if (!is_bool($status)) {
                            $fail("Each symptom status in $attribute must be a boolean.");
                        }
                    }
                }
            ],
            "temperature" => "nullable|regex:/^\d+(\.\d{1,2})?$/",
            "notes" => "nullable|string"
        ];
        $messages = [];
        $attributes = [
            "date" => __('attribute.date'),
            "symptoms" => __('attribute.pregnancy_symptoms'),
            "temperature" => __('attribute.temperature'),
            "notes" => __('attribute.notes'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $date = $request->date;

        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }

        $current_pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();

        $pregnancy_log = [
            "date" => $date,
            "pregnancy_symptoms" => json_decode($request->pregnancy_symptoms, true),
            "temperature" => $request->temperature,
            "notes" => $request->notes
        ];

        try {
            DB::beginTransaction();

            if ($current_pregnancy_log) {
                $pregnancy_logs = $current_pregnancy_log->data_harian;
                $updated = false;

                foreach ($pregnancy_logs as &$log) {
                    if ($log['date'] == $date) {
                        $log = $pregnancy_log;
                        $updated = true;
                        break;
                    }
                }

                if (!$updated) {
                    $pregnancy_logs[] = $pregnancy_log;
                }

                $current_pregnancy_log->data_harian = $pregnancy_logs;
                $current_pregnancy_log->save();
            } else {
                $current_pregnancy_log = new RiwayatLogKehamilan();
                $current_pregnancy_log->user_id = $user_id;
                $current_pregnancy_log->riwayat_kehamilan_id = $current_pregnancy->id;
                $current_pregnancy_log->data_harian = [$pregnancy_log];
                $current_pregnancy_log->save();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pregnancy Log Saved Successfully',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to save pregnancy log".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }                
    }

    public function deletePregnancyLog(Request $request) {
        $rules = [
            "date" => "required|date|before:now",
        ];
        $messages = [];
        $attributes = [
            "date" => __('attribute.date'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $date = $request->date;

        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }

        $current_pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();
        if ($current_pregnancy_log == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.pregnancy_log_not_found')
            ], Response::HTTP_BAD_REQUEST);
        }

        $pregnancy_log_data = $current_pregnancy_log->data_harian ?? [];

        $delete_pregnancy_log_data = collect($pregnancy_log_data)->firstWhere('date', $date);
        if (!$delete_pregnancy_log_data) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.pregnancy_log_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();

            $current_pregnancy_log->data_harian = collect($pregnancy_log_data)
                ->reject(fn($pregnancyLog) => $pregnancyLog['date'] === $date)
                ->values()
                ->toArray();

            $current_pregnancy_log->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Pregnancy log deleted successfully',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to delete pregnancy log".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }                
    }

    public function pregnancyLogsByDate(Request $request) {
        $pregnancySymptoms = [
            "Altered Body Image", "Anxiety", "Back Pain", "Breast Pain", "Brownish Marks on Face", "Carpal Tunnel", "Changes in Libido", "Changes in Nipples", "Constipation", "Dizziness", "Dry Mouth", "Fainting", "Feeling Depressed", "Food Cravings", "Forgetfulness", "Greasy Skin/Acne", "Haemorrhoids", "Headache", "Heart Palpitations", "Hip/Pelvic Pain", "Increased Vaginal Discharge", 
            "Incontinence/Leaking Urine", "Itchy Skin", "Leg Cramps", "Nausea", "Painful Vaginal Veins", "Poor Sleep", "Reflux", "Restless Legs", "Shortness of Breath", "Sciatica", "Snoring", "Sore Nipples", "Stretch Marks", "Swollen Hands/Feet", "Taste/Smell Changes", "Thrush", "Tiredness/Fatigue", "Urinary Frequency", "Varicose Veins", "Vivid Dreams", "Vomiting" 
        ];

        $request->validate([
            'date' => 'required|date|before_or_equal:' . now()
        ]);

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;

        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if (!$current_pregnancy) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }

        $current_pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();
        if (!$current_pregnancy_log) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.pregnancy_log_not_found')
            ], Response::HTTP_BAD_REQUEST);
        }

        $preganancy_log = collect($current_pregnancy_log->data_harian)->where('date', $request->date)->first();
        $pregnancy_log_data = $preganancy_log ?? [
            "date" => $request->date,
            "pregnancy_symptoms" => array_fill_keys($pregnancySymptoms, false),
            "temperature" => null,
            "notes" => null
        ];
    
        return response()->json([
            'status' => 'success',
            'message' => 'Pregnancy log fetched successfully',
            'data' => $pregnancy_log_data,
        ], Response::HTTP_OK);
    }

    public function pregnancyLogsByTags(Request $request) {
        $rules = [
            "tags" => "required|in:pregnancy_symptoms",
        ];
        $messages = [];
        $attributes = [
            "tags" => __('attribute.tags'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;

        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if (!$current_pregnancy) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }

        $current_pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();
        if (!$current_pregnancy_log) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.pregnancy_log_not_found')
            ], Response::HTTP_BAD_REQUEST);
        }

        $tags = $request->tags;
        $tagsValues = [];
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        $threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));
        $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
        $oneYearAgo = date('Y-m-d', strtotime('-1 year'));

        try {
            foreach ($current_pregnancy_log->data_harian as $date => $data) {
                if (isset($data[$tags]) && $data[$tags] !== null) {
                    if (in_array($tags, ["pregnancy_symptoms"])) {
                        if (is_array($data[$tags])) {
                            $trueValues = array_keys(array_filter($data[$tags], function ($value) {
                                return $value === true;
                            }));
                            
                            if (!empty($trueValues)) {
                                $tagsValues[$date] = $trueValues;
                            }
                        } elseif ($data[$tags] === true) {
                            $tagsValues[$date] = [$tags];
                        }
                    }
                }
            }
            uksort($tagsValues, function ($a, $b) {
                return strtotime($b) - strtotime($a);
            });
    
            $percentageOccurrences30Days = $this->findOccurrences($tagsValues, $thirtyDaysAgo);
            $percentageOccurrences3Months = $this->findOccurrences($tagsValues, $threeMonthsAgo);
            $percentageOccurrences6Months = $this->findOccurrences($tagsValues, $sixMonthsAgo);
            $percentageOccurrences1Year = $this->findOccurrences($tagsValues, $oneYearAgo);
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.log_retrieved_success'),
                'data' => [
                    "tags" => $tags,
                    "logs" => $tagsValues,
                    "percentage_30days" => $percentageOccurrences30Days,
                    "percentage_3months" => $percentageOccurrences3Months,
                    "percentage_6months" => $percentageOccurrences6Months,
                    "percentage_1year" => $percentageOccurrences1Year,
                ],
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to retrieve log by tags.' . $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    function findOccurrences($tagsValues, $startDate) {
        $selectedValues = [];
    
        foreach ($tagsValues as $date => $data) {
            if (strtotime($date) >= strtotime($startDate)) {
                $selectedValues = array_merge($selectedValues, is_array($data) ? array_values($data) : [$data]);
            }
        }
    
        $occurrences = array_count_values($selectedValues);
    
        return $occurrences;
    }

    public function addBloodPressure(Request $request) {
        $rules = [
            "id" => "nullable|string",
            "tekanan_sistolik" => "required|numeric|min:0",
            "tekanan_diastolik" => "required|numeric|min:0",
            "detak_jantung" => "required|numeric|min:0",
            "datetime" => "required|date_format:Y-m-d H:i:s",
        ];
        $messages = [];
        $attributes = [
            "tekanan_sistolik" => __('attribute.tekanan_sistolik'),
            "tekanan_diastolik" => __('attribute.tekanan_diastolik'),
            "detak_jantung" => __('attribute.detak_jantung'),
            "datetime" => __('attribute.datetime'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $preMadeReminderId = $request->id;
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }
        $pregnancy_age = Carbon::parse($request->datetime)->diffInWeeks($current_pregnancy->hari_pertama_haid_terakhir) + 1;
        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();

        $blood_pressure = [
            "id" => $preMadeReminderId ?? Str::uuid(),
            "tekanan_sistolik" => $request->tekanan_sistolik,
            "tekanan_diastolik" => $request->tekanan_diastolik,
            "detak_jantung" => $request->detak_jantung,
            "minggu_kehamilan" => $pregnancy_age,
            "datetime" => $request->datetime,
        ];

        try {
            DB::beginTransaction();

            if ($pregnancy_log) {
                $blood_pressure_data = $pregnancy_log->tekanan_darah ?? [];
                $pregnancy_log->tekanan_darah = array_merge($blood_pressure_data, [$blood_pressure]);
                $pregnancy_log->save();
            } else {
                $pregnancy_log = new RiwayatLogKehamilan();
                $pregnancy_log->riwayat_kehamilan_id = $current_pregnancy->id;
                $pregnancy_log->user_id = $user_id;
                $pregnancy_log->tekanan_darah = [$blood_pressure];
                $pregnancy_log->save();
            }

            DB::commit();
        
            return response()->json([
                'status' => 'success',
                'message' => 'Blood Pressure store successfully',
                'data' => $blood_pressure,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to store blood pressure".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function editBloodPressure(Request $request, $id) {
        $rules = [
            "tekanan_sistolik" => "required|numeric|min:0",
            "tekanan_diastolik" => "required|numeric|min:0",
            "detak_jantung" => "required|numeric|min:0",
            "datetime" => "required|date_format:Y-m-d H:i:s",
        ];
        $messages = [];
        $attributes = [
            "tekanan_sistolik" => __('attribute.tekanan_sistolik'),
            "tekanan_diastolik" => __('attribute.tekanan_diastolik'),
            "detak_jantung" => __('attribute.detak_jantung'),
            "datetime" => __('attribute.datetime'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }
        $pregnancy_age = Carbon::parse($request->datetime)->diffInWeeks($current_pregnancy->hari_pertama_haid_terakhir) + 1;
        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();
        if (!$pregnancy_log) {
            return response()->json([
                'status' => 'error',
                'message' => 'User data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $update_blood_pressure = collect($pregnancy_log->tekanan_darah)->where('id', $id)->first();
        if (!$update_blood_pressure) {
            return response()->json([
                'status' => 'error',
                'message' => 'Blood Pressure data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();

            $update_blood_pressure['tekanan_sistolik'] = $request->tekanan_sistolik;
            $update_blood_pressure['tekanan_diastolik'] = $request->tekanan_diastolik;
            $update_blood_pressure['detak_jantung'] = $request->detak_jantung;
            $update_blood_pressure['minggu_kehamilan'] = $pregnancy_age;
            $update_blood_pressure['datetime'] = $request->datetime;

            $updated_blood_pressure_data = collect($pregnancy_log->tekanan_darah)->map(function ($bloodPressure) use ($update_blood_pressure) {
                return $bloodPressure['id'] == $update_blood_pressure['id'] ? $update_blood_pressure : $bloodPressure;
            })->toArray();

            $pregnancy_log->tekanan_darah = $updated_blood_pressure_data;
            $pregnancy_log->save();

            DB::commit();
        
            return response()->json([
                'status' => 'success',
                'message' => 'Blood Pressure updated successfully',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to store blood pressure".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteBloodPressure(Request $request, $id) {
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }

        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();
        if (!$pregnancy_log) {
            return response()->json([
                'status' => 'error',
                'message' => 'User data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $blood_pressure_data = $pregnancy_log->tekanan_darah ?? [];

        $delete_blood_pressure = collect($blood_pressure_data)->firstWhere('id', $id);
        if (!$delete_blood_pressure) {
            return response()->json([
                'status' => 'error',
                'message' => 'Blood Pressure data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();

            $pregnancy_log->tekanan_darah = collect($blood_pressure_data)
                ->reject(fn($bloodPressure) => $bloodPressure['id'] === $id)
                ->values()
                ->toArray();

            $pregnancy_log->save();

            DB::commit();
        
            return response()->json([
                'status' => 'success',
                'message' => 'Blood Pressure deleted successfully',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to delete blood pressure".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllBloodPressure(Request $request) {
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->first();
        if (!$pregnancy_log) {
            return response()->json([
                'status' => 'error',
                'message' => 'User data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $blood_pressure_data = $pregnancy_log->tekanan_darah ?? [];
        $sorted_blood_pressure_data = collect($blood_pressure_data)
            ->sortBy('datetime')
            ->values()
            ->toArray();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Reminder fetched successfully',
            'data' => $sorted_blood_pressure_data,
        ], Response::HTTP_OK);
    }
    
    public function addContractionTimer(Request $request) {
        $rules = [
            "id" => "nullable|string",
            "waktu_mulai" => "required|date_format:Y-m-d H:i:s",
            "durasi" => "required|numeric|min:1",
        ];
        $messages = [];
        $attributes = [
            "waktu_mulai" => __('attribute.waktu_mulai'),
            "durasi" => __('attribute.durasi'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $preMadeReminderId = $request->id;
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }
        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();
        $waktuMulai = $request->waktu_mulai;

        try {
            DB::beginTransaction();

            if ($pregnancy_log) {
                $intervalBerakhir = Carbon::parse($waktuMulai)->subHour();
                $previousContraction = collect($pregnancy_log->timer_kontraksi)
                    ->filter(function ($contractions) use ($intervalBerakhir, $waktuMulai) {
                        $contractionStartTime = new Carbon($contractions['waktu_mulai']);
                        return $contractionStartTime->between($intervalBerakhir, $waktuMulai);
                    })
                    ->sortByDesc(function ($contractions) use ($waktuMulai) {
                        $contractionStartTime = new Carbon($contractions['waktu_mulai']);
                        return $contractionStartTime->diffInSeconds($waktuMulai);
                    })
                    ->first();
                
                if ($previousContraction) {
                    $interval = Carbon::parse($previousContraction['waktu_mulai'])->diffInSeconds($waktuMulai);
                } else {
                    $interval = null;
                }

                $contraction = [
                    "id" => $preMadeReminderId ?? Str::uuid(),
                    "waktu_mulai" => $waktuMulai,
                    "durasi" => $request->durasi,
                    "interval" => $interval
                ];
                
                $contraction_data = $pregnancy_log->timer_kontraksi ?? [];
                $pregnancy_log->timer_kontraksi = array_merge($contraction_data, [$contraction]);
                $pregnancy_log->save();
            } else {
                $pregnancy_log = new RiwayatLogKehamilan();
                $pregnancy_log->riwayat_kehamilan_id = $current_pregnancy->id;
                $pregnancy_log->user_id = $user_id;
                
                $contraction = [
                    "id" => $preMadeReminderId ?? Str::uuid(),
                    "waktu_mulai" => $waktuMulai,
                    "durasi" => $request->durasi,
                    "interval" => null
                ];

                $pregnancy_log->timer_kontraksi = [$contraction];
                $pregnancy_log->save();
            }

            DB::commit();
        
            return response()->json([
                'status' => 'success',
                'message' => 'Contraction Data store successfully',
                'data' => $contraction,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to store contraction data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteContractionTimer(Request $request, $id) {
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }
        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();
        if (!$pregnancy_log) {
            return response()->json([
                'status' => 'error',
                'message' => 'User data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $contraction_data = collect($pregnancy_log->timer_kontraksi ?? [])->sortBy('waktu_mulai')->values();

        $delete_contraction = collect($contraction_data)->firstWhere('id', $id);
        if (!$delete_contraction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Contraction data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $key = $contraction_data->search($delete_contraction);
        $previous_index = $key > 0 ? $key - 1 : null;
        $next_index = $key < count($contraction_data) - 1 ? $key + 1 : null;

        try {
            DB::beginTransaction();

            if ($next_index !== null && $previous_index !== null) {
                $previous_time = Carbon::parse($contraction_data[$previous_index]->waktu_mulai);
                $next_time = Carbon::parse($contraction_data[$next_index]->waktu_mulai);
    
                $time_diff = $previous_time->diffInSeconds($next_time);
    
                if ($time_diff <= 3600) {
                    $interval = $previous_time->diffInSeconds($next_time);
                    $contraction_data[$next_index]->interval = $interval;
                    $contraction_data[$next_index]->save();
                }

                if ($contraction_data[$key]->interval === null && $next_index !== null) {
                    $contraction_data[$next_index]->interval = null;
                    $contraction_data[$next_index]->save();
                }
            }

            $pregnancy_log->timer_kontraksi = collect($contraction_data)
                ->reject(fn($contractions) => $contractions['id'] === $id)
                ->values()
                ->toArray();

            $pregnancy_log->save();

            DB::commit();
        
            return response()->json([
                'status' => 'success',
                'message' => 'Contraction deleted successfully',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to delete contraction data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllContractionTimer(Request $request) {
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->first();
        if (!$pregnancy_log) {
            return response()->json([
                'status' => 'error',
                'message' => 'User data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $contraction_timer_data = $pregnancy_log->timer_kontraksi ?? [];
        $sorted_contraction_timer_data = collect($contraction_timer_data)
            ->sortByDesc('waktu_mulai')
            ->values()
            ->toArray();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Contraction Timer fetched successfully',
            'data' => $sorted_contraction_timer_data,
        ], Response::HTTP_OK);
    }

    public function addKicksCounter(Request $request) {
        $rules = [
            "id" => "nullable|string",
            "datetime" => "required|date_format:Y-m-d H:i:s",
        ];
        $messages = [];
        $attributes = [
            "datetime" => __('attribute.datetime'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        $preMadeReminderId = $request->id;
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }

        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)
                ->where('riwayat_kehamilan_id', $current_pregnancy->id)
                ->first();

        $kick_counter = $pregnancy_log->gerakan_bayi ?? [];
        if (!empty($kick_counter)) {
            usort($kick_counter, function($a, $b) {
                return strtotime($b['waktu_mulai']) - strtotime($a['waktu_mulai']);
            });
        }
        
        $datetime = $request->datetime;

        try {
            DB::beginTransaction();

            if ($pregnancy_log !== null) {
                if (!empty($kick_counter)){
                    $last_kick_data = $kick_counter[0];
                    $last_kick_time = new Carbon($last_kick_data['waktu_mulai']);
                    $time_diff = $last_kick_time->diffInSeconds($datetime);

                    if ($time_diff <= 3600) {
                        $kickCountData = $last_kick_data;
                        $kickCountData['waktu_selesai'] = $datetime;
                        $kickCountData['jumlah_gerakan'] += 1;

                        foreach ($kick_counter as &$kick) {
                            if ($kick['id'] === $last_kick_data['id']) {
                                $kick = $kickCountData;
                                break;
                            }
                        }
                    } else {
                        $kickCountData = [
                            "id" => $preMadeReminderId ?? Str::uuid(),
                            "waktu_mulai" => $datetime,
                            "waktu_selesai" => $datetime,
                            "jumlah_gerakan" => 1,
                        ];
                        $kick_counter[] = $kickCountData;
                    }

                    $pregnancy_log->gerakan_bayi = $kick_counter;
                    $pregnancy_log->save();
                } else {
                    $kickCountData = [
                        "id" => $preMadeReminderId ?? Str::uuid(),
                        "waktu_mulai" => $datetime,
                        "waktu_selesai" => $datetime,
                        "jumlah_gerakan" => 1,
                    ];

                    $pregnancy_log->gerakan_bayi = [$kickCountData];
                    $pregnancy_log->save();
                }
            } else {
                $pregnancy_log = new RiwayatLogKehamilan();
                $pregnancy_log->riwayat_kehamilan_id = $current_pregnancy->id;
                $pregnancy_log->user_id = $user_id;

                $kickCountData = [
                    "id" => $preMadeReminderId ?? Str::uuid(),
                    "waktu_mulai" => $datetime,
                    "waktu_selesai" => $datetime,
                    "jumlah_gerakan" => 1,
                ];

                $pregnancy_log->gerakan_bayi = [$kickCountData];
                $pregnancy_log->save();
            }

            DB::commit();
        
            return response()->json([
                'status' => 'success',
                'message' => 'Kicks Counter stored successfully',
                'data' => $kickCountData,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to store kicks counter".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function addKickCounterData(Request $request) {
        $rules = [
            "id" => "required|string",
            "waktu_mulai" => "required|date_format:Y-m-d H:i:s",
            "waktu_selesai" => "required|date_format:Y-m-d H:i:s",
            "jumlah_gerakan" => "required|integer|min:1",
        ];
        $messages = [];
        $attributes = [
            "waktu_mulai" => __('attribute.datetime_start'),
            "waktu_selesai" => __('attribute.datetime_end'),
            "jumlah_gerakan" => __('attribute.kick_count'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }
    
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }
    
        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)
            ->where('riwayat_kehamilan_id', $current_pregnancy->id)
            ->first();
    
        try {
            DB::beginTransaction();
    
            $kickData = [
                "id" => $request->id,
                "waktu_mulai" => $request->waktu_mulai,
                "waktu_selesai" => $request->waktu_selesai,
                "jumlah_gerakan" => $request->jumlah_gerakan,
            ];
    
            if ($pregnancy_log !== null) {
                $kick_counter = $pregnancy_log->gerakan_bayi ?? [];
                $kick_counter[] = $kickData;
                $pregnancy_log->gerakan_bayi = $kick_counter;
                $pregnancy_log->save();
            } else {
                $pregnancy_log = new RiwayatLogKehamilan();
                $pregnancy_log->riwayat_kehamilan_id = $current_pregnancy->id;
                $pregnancy_log->user_id = $user_id;
                $pregnancy_log->gerakan_bayi = [$kickData];
                $pregnancy_log->save();
            }
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Kick data stored successfully',
                'data' => $kickData,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to store kick data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    

    public function deleteKicksCounter(Request $request, $id) {
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $current_pregnancy = RiwayatKehamilan::where('user_id', $user_id)->where('status', 'Hamil')->first();
        if ($current_pregnancy == null) {
            return response()->json([
                'status' => 'error',
                'message' => __('message.not_pregnant')
            ], Response::HTTP_BAD_REQUEST);
        }
        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $current_pregnancy->id)->first();
        if (!$pregnancy_log) {
            return response()->json([
                'status' => 'error',
                'message' => 'User data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $kicks_count_data = $pregnancy_log->gerakan_bayi ?? [];

        $delete_kick_count = collect($kicks_count_data)->firstWhere('id', $id);
        if (!$delete_kick_count) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kick Count data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::beginTransaction();

            $pregnancy_log->gerakan_bayi = collect($kicks_count_data)
                ->reject(fn($kickCount) => $kickCount['id'] === $id)
                ->values()
                ->toArray();
            
            $pregnancy_log->save();

            DB::commit();
        
            return response()->json([
                'status' => 'success',
                'message' => 'Kicks Counter deleted successfully',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to delete kicks counter".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllKicksCounter(Request $request) {
        $user = Login::where('token', $request->header('user_id'))->first();
        $user_id = $user->id;
        $pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->first();
        if (!$pregnancy_log) {
            return response()->json([
                'status' => 'error',
                'message' => 'User data not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $kicks_count_data = $pregnancy_log->gerakan_bayi ?? [];
        $sorted_kicks_count_data = collect($kicks_count_data)
            ->sortByDesc('waktu_mulai')
            ->values()
            ->toArray();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Contraction Timer fetched successfully',
            'data' => $sorted_kicks_count_data,
        ], Response::HTTP_OK);
    }
}
