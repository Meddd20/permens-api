<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\BeratIdealIbuHamil;
use App\Models\Login;
use App\Models\RiwayatKehamilan;
use App\Models\RiwayatLog;
use App\Models\RiwayatLogKehamilan;
use App\Models\RiwayatMens;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResyncDataController extends Controller
{
    public function resyncData(Request $request) {
        $user = Login::where('token', $request->header('userToken'))->first();
        $userToken = $request->header('userToken');
        $user_id = $user->id;
        Log::info("message: " . $request->getContent());
        $data = json_decode($request->getContent(), true);

        $profile = $data['profile'];
        $daily_log = $data['dailyLog'] ?? null;
        $periods = $data['periodHistory'] ?? [];
        $pregnancy_histories = $data['pregnancyHistory'] ?? [];
        $weight_histories = $data['weightHistory'] ?? [];
        $pregnancy_daily_log = $data['pregnancyDailyLog'] ?? [];

        $daily_logs = null;
        if ($daily_log !== null && isset($daily_log['data_harian'])) {
            $daily_logs = json_decode($daily_log['data_harian'], true);
        }
        $dailyLogCount = is_array($daily_logs) ? count($daily_logs) : 0;

        $reminders = null;
        if ($daily_log !== null && isset($daily_log['pengingat'])) {
            $reminders = json_decode($daily_log['pengingat'], true);
        }

        try{
            DB::beginTransaction();

            $authController = new AuthController();
            $request = Request::create('/api/update-profile', 'POST', $profile);
            $request->headers->set('userToken', $userToken);
            $authController->updateProfile($request);

            if($dailyLogCount > 0) {
                $storeBatchLogResult = $this->storeBatchDailyLog($user_id, $daily_logs);
                if ($storeBatchLogResult['status'] === 'error') {
                    return response()->json([
                        'status' => 'error',
                        'message' => $storeBatchLogResult['message']
                    ], Response::HTTP_BAD_REQUEST);
                }

                $storeBatchReminderResult = $this->storeBatchReminder($user_id, $reminders);
                if ($storeBatchReminderResult['status'] === 'error') {
                    return response()->json([
                        'status' => 'error',
                        'message' => $storeBatchReminderResult['message']
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            if(count($periods) > 0) {
                $storeBatchPeriodResult = $this->storeBatchPeriod($user_id, $periods);
                if ($storeBatchPeriodResult['status'] === 'error') {
                    return response()->json([
                        'status' => 'error',
                        'message' => $storeBatchPeriodResult['message']
                    ], Response::HTTP_BAD_REQUEST);
                } else {
                    RiwayatMens::where('user_id', $user_id)
                                    ->where('is_actual', 0)
                                    ->delete();

                    $latest_period = RiwayatMens::where('user_id', $user_id)
                            ->orderBy('haid_awal', 'DESC')
                            ->first();
                    
                    if ($latest_period) {
                        $periodController = new PeriodController();
                        $periodController->generateStorePredictionPeriod($user_id, $latest_period, 3);
                    } else {
                        throw new \Exception("No periods found for generating predictions.");
                    }
                }
            }

            if(count($pregnancy_histories) > 0) {
                $storeBatchPregnancyResult = $this->storeBatchPregnancy($user_id, $pregnancy_histories);
                if ($storeBatchPregnancyResult['status'] === 'error') {
                    return response()->json([
                        'status' => 'error',
                        'message' => $storeBatchPregnancyResult['message']
                    ], Response::HTTP_BAD_REQUEST);
                }

                $pregnancySyncData = RiwayatKehamilan::where('user_id', $user_id)->get();
                $mappedPregnancyData = [];

                foreach ($pregnancySyncData as $storedPregnancy) {
                    $storedDate = Carbon::parse($storedPregnancy['hari_pertama_haid_terakhir'])->format('Y-m-d');
    
                    $matchingHistory = collect($pregnancy_histories)->firstWhere(function ($history) use ($storedDate) {
                        return Carbon::parse($history['hari_pertama_haid_terakhir'])->format('Y-m-d') === $storedDate;
                    });
                    
                    if ($matchingHistory) {
                        $mappedPregnancyData[] = [
                            'stored_pregnancy' => $storedPregnancy->toArray(),
                            'original_data' => $matchingHistory
                        ];
                    }
                }

                if(count($weight_histories) > 0) {
                    $storeBatchWeightGainResult = $this->storeBatchWeightGain($user_id, $weight_histories, $mappedPregnancyData);
                    if ($storeBatchWeightGainResult['status'] === 'error') {
                        return response()->json([
                            'status' => 'error',
                            'message' => $storeBatchWeightGainResult['message']
                        ], Response::HTTP_BAD_REQUEST);
                    }
                }
    
                if(count($pregnancy_daily_log) > 0) {
                    foreach($pregnancy_daily_log as $pregnancy_log) {
                        $pregnancy_logs = json_decode($pregnancy_log['data_harian'], true);
                        $blood_pressures = json_decode($pregnancy_log['tekanan_darah'], true);
                        $contraction_timer = json_decode($pregnancy_log['timer_kontraksi'], true);
                        $baby_movement = json_decode($pregnancy_log['gerakan_bayi'], true);

                        $matchingPregnancyData = collect($mappedPregnancyData)->firstWhere('original_data.id', $pregnancy_log['riwayat_kehamilan_id']);
                        if ($matchingPregnancyData) {
                            $pregnancy_id = $matchingPregnancyData['stored_pregnancy']['id'];

                            $storeBatchPregnancyLogsResult = $this->storeBatchPregnancyLogs($user_id, $pregnancy_logs, $pregnancy_id);
                            if ($storeBatchPregnancyLogsResult['status'] === 'error') {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => $storeBatchPregnancyLogsResult['message']
                                ], Response::HTTP_BAD_REQUEST);
                            }
        
                            $storeBatchBloodPressureResult = $this->storeBatchBloodPressure($user_id, $blood_pressures, $pregnancy_id);
                            if ($storeBatchBloodPressureResult['status'] === 'error') {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => $storeBatchBloodPressureResult['message']
                                ], Response::HTTP_BAD_REQUEST);
                            }
        
                            $storeBatchContractionTimerResult = $this->storeBatchContractionTimer($user_id, $contraction_timer, $pregnancy_id);
                            if ($storeBatchContractionTimerResult['status'] === 'error') {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => $storeBatchContractionTimerResult['message']
                                ], Response::HTTP_BAD_REQUEST);
                            }
        
                            $storeBatchBabyMovementResult = $this->storeBatchBabyMovement($user_id, $baby_movement, $pregnancy_id);
                            if ($storeBatchBabyMovementResult['status'] === 'error') {
                                return response()->json([
                                    'status' => 'error',
                                    'message' => $storeBatchBabyMovementResult['message']
                                ], Response::HTTP_BAD_REQUEST);
                            }
                        }
                    }
                }
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'All data resynced successfully',
                'data' => [
                    'period_history' => RiwayatMens::where('user_id', $user_id)->get(),
                    'pregnancy_history' => RiwayatKehamilan::where('user_id', $user_id)->get(),
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to resync data" . ' | ' . $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    private function storeBatchDailyLog($user_id, $logs)
    {
        $sex_activity = ["Didn't have sex", "Unprotected sex", "Protected sex"];
        $vaginal_discharge = ["No discharge", "Creamy", "Spotting", "Eggwhite", "Sticky", "Watery", "Unusual"];
        $bleeding_flow = ["Light", "Medium", "Heavy"];
        $symptoms = ["Abdominal cramps", "Acne", "Backache", "Bloating", "Body aches", "Chills", "Constipation", "Cramps", "Cravings", "Diarrhea", "Dizziness", "Fatigue", "Feel good", "Gas", "Headache", "Hot flashes", "Insomnia", "Low back pain", "Nausea", "Nipple changes", "PMS", "Spotting", "Swelling", "Tender breasts"];
        $moods = ["Angry", "Anxious", "Apathetic", "Calm", "Confused", "Cranky", "Depressed", "Emotional", "Energetic", "Excited", "Feeling guilty", "Frisky", "Frustrated", "Happy", "Irritated", "Low energy", "Mood swings", "Obsessive thoughts", "Sad", "Sensitive", "Sleepy", "Tired", "Unfocused", "Very self-critical"];
        $others = ["Travel", "Stress", "Disease or Injury", "Alcohol"];
        $physical_activity = ["Didn't exercise", "Yoga", "Gym", "Aerobics & Dancing", "Swimming", "Team sports", "Running", "Cycling", "Walking"];

        $dataToValidate = ['logs' => $logs];
        $rules = [
            'logs' => 'required|array',
            'logs.*.date' => 'required|date|before:now',
            'logs.*.sex_activity' => ["nullable", Rule::in($sex_activity)],
            'logs.*.bleeding_flow' => ["nullable", Rule::in($bleeding_flow)],
            'logs.*.symptoms' => [
                "required",
                "array",
                function ($attribute, $value, $fail) use ($symptoms) {
                    foreach ($value as $symptom => $status) {
                        if (!in_array($symptom, $symptoms) || !is_bool($status)) {
                            $fail("Invalid or malformed symptom data in $attribute.");
                        }
                    }
                }
            ],
            'logs.*.vaginal_discharge' => ["nullable", Rule::in($vaginal_discharge)],
            'logs.*.moods' => [
                "required",
                "array",
                function ($attribute, $value, $fail) use ($moods) {
                    foreach ($value as $mood => $status) {
                        if (!in_array($mood, $moods) || !is_bool($status)) {
                            $fail("Invalid or malformed mood data in $attribute.");
                        }
                    }
                }
            ],
            'logs.*.others' => [
                "required",
                "array",
                function ($attribute, $value, $fail) use ($others) {
                    foreach ($value as $other => $status) {
                        if (!in_array($other, $others) || !is_bool($status)) {
                            $fail("Invalid or malformed other data in $attribute.");
                        }
                    }
                }
            ],
            'logs.*.physical_activity' => [
                "required",
                "array",
                function ($attribute, $value, $fail) use ($physical_activity) {
                    foreach ($value as $activity => $status) {
                        if (!in_array($activity, $physical_activity) || !is_bool($status)) {
                            $fail("Invalid or malformed activity data in $attribute.");
                        }
                    }
                }
            ],
            'logs.*.temperature' => 'nullable|regex:/^\d+(\.\d{1,2})?$/',
            'logs.*.weight' => 'nullable|regex:/^\d+(\.\d{1,2})?$/',
            'logs.*.notes' => 'nullable|string'
        ];

        $validator = Validator::make($dataToValidate, $rules);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        try {
            DB::beginTransaction();

            $userData = RiwayatLog::where('user_id', $user_id)->first();
            $newDailyLogs = $userData ? $userData->data_harian : [];

            foreach ($logs as $log) {
                $dateToCheck = $log['date'];

                $newData = [
                    "date" => $dateToCheck,
                    "sex_activity" => $log['sex_activity'],
                    "bleeding_flow" => $log['bleeding_flow'],
                    "symptoms" => $log['symptoms'],
                    "vaginal_discharge" => $log['vaginal_discharge'],
                    "moods" => $log['moods'],
                    "others" => $log['others'],
                    "physical_activity" => $log['physical_activity'],
                    "temperature" => $log['temperature'],
                    "weight" => $log['weight'],
                    "notes" => $log['notes']
                ];

                $newDailyLogs[$dateToCheck] = $newData;
            }

            if ($userData) {
                $userData->data_harian = $newDailyLogs;
                $userData->save();
            } else {
                $userData = new RiwayatLog();
                $userData->user_id = $user_id;
                $userData->data_harian = $newDailyLogs;
                $userData->save();
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Batch logs stored successfully',
                'data' => $newDailyLogs
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => "Failed to store batch logs: " . $th->getMessage()
            ];
        }
    }

    private function storeBatchReminder($user_id, $reminders) {
        $dataToValidate = ['reminders' => $reminders];
        $rules = [
            "reminders" => "required|array",
            "reminders.*.id" => "nullable|string",
            "reminders.*.title" => "required|string",
            "reminders.*.description" => "nullable|string",
            "reminders.*.datetime" => "required|date_format:Y-m-d H:i",
        ];
        $validator = Validator::make($dataToValidate, $rules, []);
        $validator->stopOnFirstFailure();

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        try {
            DB::beginTransaction();

            $userData = RiwayatLog::where('user_id', $user_id)->first();
            $newReminders = [];

            foreach ($reminders as $reminder) {
                $newReminder = [
                    "id" => $reminder['id'] != null ? $reminder['id'] : Str::uuid(),
                    "title" => $reminder['title'],
                    "description" => $reminder['description'],
                    "datetime" => $reminder['datetime']
                ];
                $newReminders[] = $newReminder;
            }

            if ($userData) {
                $userData->pengingat = $newReminders;
                $userData->save();
            } else {
                $userData = new RiwayatLog();
                $userData->user_id = $user_id;
                $userData->pengingat = $newReminders;
                $userData->save();
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Reminders batch stored successfully',
                'data' => $newReminders
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => "Failed to store batch reminders: " . $th->getMessage()
            ];
        }
    }

    private function storeBatchPeriod($user_id, $periods) {
        $dataToValidate = ['periods' => $periods];
        $rules = [
            "periods" => "required|array",
            "periods.*.haid_awal" => "required",
            "periods.*.haid_akhir" => "required",
            "periods.*.ovulasi" => "required",
            "periods.*.masa_subur_awal" => "required",
            "periods.*.masa_subur_akhir" => "required",
            "periods.*.hari_terakhir_siklus" => "nullable",
            "periods.*.lama_siklus" => "required|integer",
            "periods.*.durasi_haid" => "required|integer",
            "periods.*.haid_berikutnya_awal" => "required",
            "periods.*.haid_berikutnya_akhir" => "required",
            "periods.*.ovulasi_berikutnya" => "required",
            "periods.*.masa_subur_berikutnya_awal" => "required",
            "periods.*.masa_subur_berikutnya_akhir" => "required",
            "periods.*.hari_terakhir_siklus_berikutnya" => "required",
        ];
    
        $validator = Validator::make($dataToValidate, $rules, []);
        $validator->stopOnFirstFailure();
    
        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }
    
        try {
            $newPeriods = [];
            DB::transaction(function () use ($user_id, $periods, &$newPeriods) {
                foreach ($periods as $period) {
                    $newPeriods[] = [
                        'user_id' => $user_id,
                        'haid_awal' => Carbon::parse($period['haid_awal'])->format('Y-m-d'),
                        'haid_akhir' => Carbon::parse($period['haid_akhir'])->format('Y-m-d'),
                        'ovulasi' => Carbon::parse($period['ovulasi'])->format('Y-m-d'),
                        'masa_subur_awal' => Carbon::parse($period['masa_subur_awal'])->format('Y-m-d'),
                        'masa_subur_akhir' => Carbon::parse($period['masa_subur_akhir'])->format('Y-m-d'),
                        'hari_terakhir_siklus' => $period['hari_terakhir_siklus'] ? Carbon::parse($period['hari_terakhir_siklus'])->format('Y-m-d') : null,
                        'lama_siklus' => $period['lama_siklus'],
                        'durasi_haid' => $period['durasi_haid'],
                        'haid_berikutnya_awal' => Carbon::parse($period['haid_berikutnya_awal'])->format('Y-m-d'),
                        'haid_berikutnya_akhir' => Carbon::parse($period['haid_berikutnya_akhir'])->format('Y-m-d'),
                        'ovulasi_berikutnya' => Carbon::parse($period['ovulasi_berikutnya'])->format('Y-m-d'),
                        'masa_subur_berikutnya_awal' => Carbon::parse($period['masa_subur_berikutnya_awal'])->format('Y-m-d'),
                        'masa_subur_berikutnya_akhir' => Carbon::parse($period['masa_subur_berikutnya_akhir'])->format('Y-m-d'),
                        'hari_terakhir_siklus_berikutnya' => Carbon::parse($period['hari_terakhir_siklus_berikutnya'])->format('Y-m-d'),
                        'is_actual' => "1",
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
    
                RiwayatMens::insert($newPeriods);
            });

            return [
                'status' => 'success',
                'message' => 'Period batch stored successfully',
                'data' => $newPeriods
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => "Failed to store period batch: " . $th->getMessage()
            ];
        }
    }

    private function storeBatchPregnancy($user_id, $pregnancy_histories) {
        $dataToValidate = ['pregnancy_histories' => $pregnancy_histories];
        $rules = [
            "pregnancy_histories" => "required|array",
            "pregnancy_histories.*.status" => "required",
            "pregnancy_histories.*.hari_pertama_haid_terakhir" => "required",
            "pregnancy_histories.*.tanggal_perkiraan_lahir" => "required",
            "pregnancy_histories.*.kehamilan_akhir" => "nullable",
            "pregnancy_histories.*.tinggi_badan" => "nullable|numeric|between:50,250",
            "pregnancy_histories.*.berat_prakehamilan" => "nullable|numeric|between:30,200",
            "pregnancy_histories.*.bmi_prakehamilan" => "nullable|numeric",
            "pregnancy_histories.*.kategori_bmi" => "nullable",
            "pregnancy_histories.*.gender" => "nullable|in:Boy,Girl",
            "pregnancy_histories.*.is_twin" => "nullable|integer|between:0,1",
        ];
        $validator = Validator::make($dataToValidate, $rules, []);
        $validator->stopOnFirstFailure();

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        try {
            $newPregnancy = [];
            DB::transaction(function () use ($pregnancy_histories, $user_id) {

                foreach($pregnancy_histories as $pregnancy) {
                    $newPregnancy[] = [
                        'user_id' => $user_id,
                        'status' => $pregnancy['status'],
                        'hari_pertama_haid_terakhir' => Carbon::parse($pregnancy['hari_pertama_haid_terakhir'])->format('Y-m-d'),
                        'tanggal_perkiraan_lahir' => Carbon::parse($pregnancy['tanggal_perkiraan_lahir'])->format('Y-m-d'),
                        'kehamilan_akhir' => Carbon::parse($pregnancy['kehamilan_akhir'])->format('Y-m-d'),
                        'tinggi_badan' => $pregnancy['tinggi_badan'],
                        'berat_prakehamilan' => $pregnancy['berat_prakehamilan'],
                        'bmi_prakehamilan' => $pregnancy['bmi_prakehamilan'],
                        'kategori_bmi' => $pregnancy['kategori_bmi'],
                        'gender' => $pregnancy['gender'],
                        'is_twin' => $pregnancy['is_twin'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
        
                RiwayatKehamilan::insert($newPregnancy);
            });

            return [
                'status' => 'success',
                'message' => 'Pregnancy batch stored successfully',
                'data' => $newPregnancy
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => "Failed to store pregnancy batch: " . $th->getMessage()
            ];
        }
    }

    private function storeBatchWeightGain($user_id, $weight_histories, $mappedPregnancyData) {
        $dataToValidate = ['weight_histories' => $weight_histories];
        $rules = [
            "weight_histories" => "required|array",
            "weight_histories.*.riwayat_kehamilan_id" => "required",
            "weight_histories.*.berat_badan" => 'required|numeric|between:30,200',
            "weight_histories.*.minggu_kehamilan" => "required|integer|between:0,40",
            "weight_histories.*.tanggal_pencatatan" => "required",
            "weight_histories.*.pertambahan_berat" => "required|numeric",
        ];
        $validator = Validator::make($dataToValidate, $rules, []);
        $validator->stopOnFirstFailure();

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        try {
            $newWeights = [];
            DB::transaction(function () use ($weight_histories, $user_id, $mappedPregnancyData, &$newWeights) {

                foreach($weight_histories as $weight) {
                    $matchingPregnancyData = collect($mappedPregnancyData)->firstWhere('original_data.id', $weight['riwayat_kehamilan_id']);
                    if($matchingPregnancyData) {
                        $pregnancy_id = $matchingPregnancyData['stored_pregnancy']['id'];
    
                        $newWeights[] = [
                            'user_id' => $user_id,
                            'riwayat_kehamilan_id' => $pregnancy_id,
                            'berat_badan' => $weight['berat_badan'],
                            'minggu_kehamilan' => $weight['minggu_kehamilan'],
                            'tanggal_pencatatan' => Carbon::parse($weight['tanggal_pencatatan'])->format('Y-m-d'),
                            'pertambahan_berat' => $weight['pertambahan_berat'],
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                    }
                }

                BeratIdealIbuHamil::insert($newWeights);
            });

            return [
                'status' => 'success',
                'message' => 'Weight Pregnancy batch stored successfully',
                'data' => $newWeights
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => "Failed to store weight pregnancy batch: " . $th->getMessage()
            ];
        }
    }

    private function storeBatchPregnancyLogs($user_id, $pregnancy_logs, $pregnancy_id) {
        $pregnancySymptoms = [
            "Altered Body Image", "Anxiety", "Back Pain", "Breast Pain", "Brownish Marks on Face", "Carpal Tunnel", "Changes in Libido", "Changes in Nipples", "Constipation", "Dizziness", "Dry Mouth", "Fainting", "Feeling Depressed", "Food Cravings", "Forgetfulness", "Greasy Skin/Acne", "Haemorrhoids", "Headache", "Heart Palpitations", "Hip/Pelvic Pain", "Increased Vaginal Discharge", 
            "Incontinence/Leaking Urine", "Itchy Skin", "Leg Cramps", "Nausea", "Painful Vaginal Veins", "Poor Sleep", "Reflux", "Restless Legs", "Shortness of Breath", "Sciatica", "Snoring", "Sore Nipples", "Stretch Marks", "Swollen Hands/Feet", "Taste/Smell Changes", "Thrush", "Tiredness/Fatigue", "Urinary Frequency", "Varicose Veins", "Vivid Dreams", "Vomiting" 
        ];

        $dataToValidate = ['pregnancy_logs' => $pregnancy_logs];
        $rules = [
            "pregnancy_logs" => "required|array",
            "pregnancy_logs.*.date" => "required|date|before:now",
            "pregnancy_logs.*.pregnancy_symptoms" => [
                "required",
                "array",
                function ($attribute, $value, $fail) use ($pregnancySymptoms) {
                    foreach ($value as $symptom => $status) {
                        if (!in_array($symptom, $pregnancySymptoms) || !is_bool($status)) {
                            $fail("Invalid or malformed pregnancy symptom data in $attribute.");
                        }
                    }
                }
            ],
            "pregnancy_logs.*.temperature" => "nullable|regex:/^\d+(\.\d{1,2})?$/",
            "pregnancy_logs.*.notes" => "nullable|string"
        ];
        $validator = Validator::make($dataToValidate, $rules, []);
        $validator->stopOnFirstFailure();

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        try {
            DB::beginTransaction();

            $current_pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $pregnancy_id)->first();
            $newPregnancyLogs = [];

            foreach($pregnancy_logs as $log) {
                $newPregnancyLog = [
                    "date" => $log['date'],
                    "pregnancy_symptoms" => $log['pregnancy_symptoms'],
                    "temperature" => $log['temperature'],
                    "notes" => $log['notes']
                ];
                $newPregnancyLogs[] = $newPregnancyLog;
            }

            if ($current_pregnancy_log) {
                $current_pregnancy_log->data_harian = $newPregnancyLogs;
                $current_pregnancy_log->save();
            } else {
                $current_pregnancy_log = new RiwayatLogKehamilan();
                $current_pregnancy_log->user_id = $user_id;
                $current_pregnancy_log->riwayat_kehamilan_id = $pregnancy_id;
                $current_pregnancy_log->data_harian = $newPregnancyLogs;
                $current_pregnancy_log->save();
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Pregnancy logs batch stored successfully',
                'data' => $newPregnancyLogs
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => "Failed to store batch pregnancy logs: " . $th->getMessage()
            ];
        }
    }

    private function storeBatchBloodPressure($user_id, $blood_pressures, $pregnancy_id) {
        $dataToValidate = ['blood_pressures' => $blood_pressures];
        $rules = [
            "blood_pressures" => "required|array",
            "blood_pressures.*.id" => "required|string",
            "blood_pressures.*.tekanan_sistolik" => "required|numeric|min:0",
            "blood_pressures.*.tekanan_diastolik" => "required|numeric|min:0",
            "blood_pressures.*.detak_jantung" => "required|numeric|min:0",
            "blood_pressures.*.datetime" => "required",
        ];
        $validator = Validator::make($dataToValidate, $rules, []);
        $validator->stopOnFirstFailure();

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        try {
            DB::beginTransaction();

            $current_pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $pregnancy_id)->first();
            $newBloodPressures = [];

            foreach($blood_pressures as $blood_pressure) {
                $newBloodPressure = [
                    "id" => $blood_pressure['id'],
                    "tekanan_sistolik" => $blood_pressure['tekanan_sistolik'],
                    "tekanan_diastolik" => $blood_pressure['tekanan_diastolik'],
                    "detak_jantung" => $blood_pressure['detak_jantung'],
                    "datetime" => Carbon::parse($blood_pressure['datetime'])->format("Y-m-d H:i:s"),
                ];
                $newBloodPressures[] = $newBloodPressure;
            }

            if ($current_pregnancy_log) {
                $current_pregnancy_log->tekanan_darah = $newBloodPressures;
                $current_pregnancy_log->save();
            } else {
                $current_pregnancy_log = new RiwayatLogKehamilan();
                $current_pregnancy_log->user_id = $user_id;
                $current_pregnancy_log->riwayat_kehamilan_id = $pregnancy_id;
                $current_pregnancy_log->tekanan_darah = $newBloodPressures;
                $current_pregnancy_log->save();
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Blood pressures batch stored successfully',
                'data' => $newBloodPressures
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => "Failed to store batch blood pressures: " . $th->getMessage()
            ];
        }
    }

    private function storeBatchContractionTimer($user_id, $contraction_timers, $pregnancy_id) {
        $dataToValidate = ['contraction_timers' => $contraction_timers];
        $rules = [
            "contraction_timers" => "required|array",
            "contraction_timers.*.id" => "required|string",
            "contraction_timers.*.waktu_mulai" => "required",
            "contraction_timers.*.durasi" => "required|numeric|min:0",
            "contraction_timers.*.interval" => "nullable|numeric|min:0",
        ];
        $validator = Validator::make($dataToValidate, $rules, []);
        $validator->stopOnFirstFailure();

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        try {
            DB::beginTransaction();

            $current_pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $pregnancy_id)->first();
            $newContractionTimers = [];

            foreach($contraction_timers as $timer) {
                $newContractionTimer = [
                    "id" => $timer['id'],
                    "waktu_mulai" => Carbon::parse($timer['waktu_mulai'])->format("Y-m-d H:i:s"),
                    "durasi" => $timer['durasi'],
                    "interval" => $timer['interval'],
                ];
                $newContractionTimers[] = $newContractionTimer;
            }

            if ($current_pregnancy_log) {
                $current_pregnancy_log->timer_kontraksi = $newContractionTimers;
                $current_pregnancy_log->save();
            } else {
                $current_pregnancy_log = new RiwayatLogKehamilan();
                $current_pregnancy_log->user_id = $user_id;
                $current_pregnancy_log->riwayat_kehamilan_id = $pregnancy_id;
                $current_pregnancy_log->timer_kontraksi = $newContractionTimers;
                $current_pregnancy_log->save();
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Contraction timer batch stored successfully',
                'data' => $newContractionTimers
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => "Failed to store batch contraction timer: " . $th->getMessage()
            ];
        }
    }

    private function storeBatchBabyMovement($user_id, $baby_movements, $pregnancy_id) {
        $dataToValidate = ['baby_movements' => $baby_movements];
        $rules = [
            "baby_movements" => "required|array",
            "baby_movements.*.id" => "required|string",
            "baby_movements.*.waktu_mulai" => "required",
            "baby_movements.*.waktu_selesai" => "required",
            "baby_movements.*.jumlah_gerakan" => "required|numeric|min:0",
        ];
        $validator = Validator::make($dataToValidate, $rules, []);
        $validator->stopOnFirstFailure();

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        try {
            DB::beginTransaction();

            $current_pregnancy_log = RiwayatLogKehamilan::where('user_id', $user_id)->where('riwayat_kehamilan_id', $pregnancy_id)->first();
            $newBabyMovements = [];

            foreach($baby_movements as $baby_movement) {
                $newBabyMovement = [
                    "id" => $baby_movement['id'],
                    "waktu_mulai" => Carbon::parse($baby_movement['waktu_mulai'])->format("Y-m-d H:i:s"),
                    "waktu_selesai" => Carbon::parse($baby_movement['waktu_selesai'])->format("Y-m-d H:i:s"),
                    "jumlah_gerakan" => $baby_movement['jumlah_gerakan'],
                ];
                $newBabyMovements[] = $newBabyMovement;
            }

            if ($current_pregnancy_log) {
                $current_pregnancy_log->gerakan_bayi = $newBabyMovements;
                $current_pregnancy_log->save();
            } else {
                $current_pregnancy_log = new RiwayatLogKehamilan();
                $current_pregnancy_log->user_id = $user_id;
                $current_pregnancy_log->riwayat_kehamilan_id = $pregnancy_id;
                $current_pregnancy_log->gerakan_bayi = $newBabyMovements;
                $current_pregnancy_log->save();
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Baby movement batch stored successfully',
                'data' => $newBabyMovements
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return [
                "status" => "error",
                "message" => "Failed to store batch baby movement: " . $th->getMessage()
            ];
        }
    }

}
