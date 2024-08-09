<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Login;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\RiwayatLog;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogsController extends Controller
{
    public function storeLog(Request $request) 
    {
        $sex_activity = ["Didn't have sex", "Unprotected sex", "Protected sex"];
        $vaginal_discharge = ["No discharge", "Creamy", "Spotting", "Eggwhite", "Sticky", "Watery", "Unusual"];
        $bleeding_flow = ["Light", "Medium", "Heavy"];
        $symptoms = ["Abdominal cramps", "Acne", "Backache", "Bloating", "Body aches", "Chills", "Constipation", "Cramps", "Cravings", "Diarrhea", "Dizziness", "Fatigue", "Feel good", "Gas", "Headache", "Hot flashes", "Insomnia", "Low back pain", "Nausea", "Nipple changes", "PMS", "Spotting", "Swelling", "Tender breasts"];
        $moods = ["Angry", "Anxious", "Apathetic", "Calm", "Confused", "Cranky", "Depressed", "Emotional", "Energetic", "Excited", "Feeling guilty", "Frisky", "Frustrated", "Happy", "Irritated", "Low energy", "Mood swings", "Obsessive thoughts", "Sad", "Sensitive", "Sleepy", "Tired", "Unfocused", "Very self-critical"];
        $others = ["Travel", "Stress", "Disease or Injury", "Alcohol"];
        $physical_activity = ["Didn't exercise", "Yoga", "Gym", "Aerobics & Dancing", "Swimming", "Team sports", "Running", "Cycling", "Walking"];

        $rules = [
            "date" => "required|date|before:now",
            "sex_activity" => ["nullable", Rule::in($sex_activity)],
            "bleeding_flow" => ["nullable", Rule::in($bleeding_flow)],
            "symptoms" => [
                "required",
                "json",
                function ($attribute, $value, $fail) use ($symptoms) {
                    $decodedValue = json_decode($value, true);

                    if ($decodedValue === null) {
                        $fail("The $attribute format is invalid. JSON decoding failed.");
                        return;
                    }
        
                    if (!is_array($decodedValue) || count($decodedValue) !== count($symptoms)) {
                        $fail("The $attribute format is invalid.");
                    }
        
                    foreach ($decodedValue as $symptom => $status) {
                        if (!in_array($symptom, $symptoms)) {
                            $fail("Invalid symptom '$symptom' in $attribute.");
                        }
        
                        if (!is_bool($status)) {
                            $fail("Each symptom status in $attribute must be a boolean.");
                        }
                    }
                }
            ],
            "vaginal_discharge" => ["nullable", Rule::in($vaginal_discharge)],
            "moods" => [
                "required",
                "json",
                function ($attribute, $value, $fail) use ($moods) {
                    $decodedValue = json_decode($value, true);

                    if ($decodedValue === null) {
                        $fail("The $attribute format is invalid. JSON decoding failed.");
                        return;
                    }
        
                    if (!is_array($decodedValue) || count($decodedValue) !== count($moods)) {
                        $fail("The $attribute format is invalid.");
                    }
        
                    foreach ($decodedValue as $mood => $status) {
                        if (!in_array($mood, $moods)) {
                            $fail("Invalid mood '$mood' in $attribute.");
                        }
        
                        if (!is_bool($status)) {
                            $fail("Each mood status in $attribute must be a boolean.");
                        }
                    }
                }
            ],
            "others" => [
                "required",
                "json",
                function ($attribute, $value, $fail) use ($others) {
                    $decodedValue = json_decode($value, true);

                    if ($decodedValue === null) {
                        $fail("The $attribute format is invalid. JSON decoding failed.");
                        return;
                    }
        
                    if (!is_array($decodedValue) || count($decodedValue) !== count($others)) {
                        $fail("The $attribute format is invalid.");
                    }
        
                    foreach ($decodedValue as $other => $status) {
                        if (!in_array($other, $others)) {
                            $fail("Invalid other '$other' in $attribute.");
                        }
        
                        if (!is_bool($status)) {
                            $fail("Each other status in $attribute must be a boolean.");
                        }
                    }
                }
            ],
            "physical_activity" => [
                "required",
                "json",
                function ($attribute, $value, $fail) use ($physical_activity) {
                    $decodedValue = json_decode($value, true);

                    if ($decodedValue === null) {
                        $fail("The $attribute format is invalid. JSON decoding failed.");
                        return;
                    }
        
                    if (!is_array($decodedValue) || count($decodedValue) !== count($physical_activity)) {
                        $fail("The $attribute format is invalid.");
                    }
        
                    foreach ($decodedValue as $activity => $status) {
                        if (!in_array($activity, $physical_activity)) {
                            $fail("Invalid physical activity '$activity' in $attribute.");
                        }
        
                        if (!is_bool($status)) {
                            $fail("Each physical activity status in $attribute must be a boolean.");
                        }
                    }
                }
            ],
            "temperature" => "nullable|regex:/^\d+(\.\d{1,2})?$/",
            "weight" => "nullable|regex:/^\d+(\.\d{1,2})?$/",      
            "notes" => "nullable|string"
        ];
        $messages = [];
        $attributes = [
            "date" => __('attribute.date'),
            "sex_activity" => __('attribute.sex_activity'),
            "symptoms" => __('attribute.symptoms'),
            "vaginal_discharge" => __('attribute.vaginal_discharge'),
            "moods" => __('attribute.moods'),
            "others" => __('attribute.others'),
            "temperature" => __('attribute.temperature'),
            "weight" => __('attribute.weight'),
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

        try {
            DB::beginTransaction();
        
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $dateToCheck = $request->input('date');
        
            $userData = RiwayatLog::where('user_id', $user_id)->first();
        
            $newData = [
                "date" => $dateToCheck,
                "sex_activity" => $request->input('sex_activity'),
                "bleeding_flow" => $request->input('bleeding_flow'),
                "symptoms" => json_decode($request->input('symptoms'), true),
                "vaginal_discharge" => $request->input('vaginal_discharge'),
                "moods" => json_decode($request->input('moods'), true),
                "others" => json_decode($request->input('others'), true),
                "physical_activity" => json_decode($request->input('physical_activity'), true),
                "temperature" => $request->input('temperature'),
                "weight" => $request->input('weight'),
                "notes" => $request->input('notes')
            ];
            
        
            if ($userData) {
                $dateUserData = $userData->data_harian;

                if (isset($dateUserData[$dateToCheck])) {
                    $message = "updated";
                    // User data exists, update the JSON with the new data
                    $userDataArray = $userData->data_harian;
                    $userDataArray[$dateToCheck] = $newData;
            
                    // Update the user data
                    $userData->data_harian = $userDataArray;
                    $userData->save();
                } else {
                    $message = "created";
                    $userDataArray = $userData->data_harian;
                    $userDataArray[$dateToCheck] = $newData;

                    $userData->data_harian = $userDataArray;
                    $userData->save();
                }

            } else {
                $message = "created";
                $userDataArray = [
                    $dateToCheck => $newData
                ];

                $userData = new RiwayatLog();
                $userData->user_id = $user_id;
                $userData->data_harian = $userDataArray;
                $userData->save();
            }
            DB::commit();

            $updatedData = $userDataArray[$dateToCheck];
        
            return response()->json([
                'status' => 'success',
                'message' => 'Daily log '.$message.' successfully',
                'data' => $updatedData
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to store log".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteLogByDate(Request $request) 
    {
        try {
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $dateToDelete = $request->input('date');
        
            // Cari data pengguna berdasarkan ID pengguna dan tanggal yang akan dihapus
            $userData = RiwayatLog::where('user_id', $user_id)->first();
        
            if ($userData) {
                $userDataArray = $userData->data_harian;
        
                // Periksa apakah data dengan tanggal yang akan dihapus ada dalam array data pengguna
                if (isset($userDataArray[$dateToDelete])) {
                    // Hapus data dengan tanggal yang sesuai
                    unset($userDataArray[$dateToDelete]);
        
                    // Update data pengguna di database
                    $userData->data_harian = $userDataArray;
                    $userData->save();
        
                    return response()->json([
                        'status' => 'success',
                        'message' => __('response.log_deleted_success'),
                    ], Response::HTTP_OK);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('response.log_not_found'),
                    ], Response::HTTP_NOT_FOUND);
                }
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.user_data_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'failed',
                'message' => "Failed to delete log".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function allLogs(Request $request) 
    {
        try {
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $userData = RiwayatLog::where('user_id', $user_id)->first();
        
            if (!$userData) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.user_data_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }
        
            return response()->json([
                'status' => 'success',
                'message' => __('response.log_retrieved_success'),
                'data' => $userData->data_harian
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'failed',
                'message' => "Failed to retrieve log ".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logsByDate(Request $request) 
    {
        $symptoms = ["Abdominal cramps", "Acne", "Backache", "Bloating", "Body aches", "Chills", "Constipation", "Cramps", "Cravings", "Diarrhea", "Dizziness", "Fatigue", "Feel good", "Gas", "Headache", "Hot flashes", "Insomnia", "Low back pain", "Nausea", "Nipple changes", "PMS", "Spotting", "Swelling", "Tender breasts"];
        $moods = ["Angry", "Anxious", "Apathetic", "Calm", "Confused", "Cranky", "Depressed", "Emotional", "Energetic", "Excited", "Feeling guilty", "Frisky", "Frustrated", "Happy", "Irritated", "Low energy", "Mood swings", "Obsessive thoughts", "Sad", "Sensitive", "Sleepy", "Tired", "Unfocused", "Very self-critical"];
        $others = ["Travel", "Stress", "Disease or Injury", "Alcohol"];
        $physical_activity = ["Didn't exercise", "Yoga", "Gym", "Aerobics & Dancing", "Swimming", "Team sports", "Running", "Cycling", "Walking"];

        $request->validate([
            'date' => 'required|date|before_or_equal:' . now()
        ]);

        try {
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $userData = RiwayatLog::where('user_id', $user_id)->first();
        
            if (!$userData) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.user_data_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }
        
            $date = $request->input('date');
            Log::info('Data Harian for ' . $date . ': ' . json_encode($userData->data_harian));
            if (isset($userData->data_harian[$date])) {
                $responseData = $userData->data_harian[$date];
                return response()->json([
                    'status' => 'success',
                    'message' => __('response.log_retrieved_success'),
                    'data' => $responseData
                ]);
            } else {
                $responseData = [
                    'date' => $date,
                    'moods' => array_fill_keys($moods, false),
                    'notes' => null,
                    'others' => array_fill_keys($others, false),
                    'weight' => null,
                    'reminder' => null,
                    'symptoms' => array_fill_keys($symptoms, false),
                    'temperature' => null,
                    'sex_activity' => null,
                    'bleeding_flow' => null,
                    'physical_activity' => array_fill_keys($physical_activity, false),
                    'vaginal_discharge' => null,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => isset($userData->data_harian[$date]) ? __('response.log_retrieved_success') : __('response.log_not_found'),
                'data' => $responseData
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to retrieve log by date.' . $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logsByTags(Request $request) 
    {
        try {
            $tags = $request->query('tags');
            $allowedTags = ["sex_activity", "bleeding_flow", "symptoms", "vaginal_discharge", "moods", "others", "physical_activity", "temperature", "weight", "notes"];

            $validator = Validator::make(['tags' => $tags], [
                'tags' => [
                    'required',
                    Rule::in($allowedTags),
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $userData = RiwayatLog::where('user_id', $user_id)->first();

            if (!$userData) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.user_data_not_found'),
                ], Response::HTTP_NOT_FOUND);
            }

            $tagsValues = [];
            $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
            $threeMonthsAgo = date('Y-m-d', strtotime('-3 months'));
            $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
            $oneYearAgo = date('Y-m-d', strtotime('-1 year'));

            foreach ($userData->data_harian as $date => $data) {
                if (isset($data[$tags]) && $data[$tags] !== null) {
                    if (in_array($tags, ["symptoms", "moods", "others", "physical_activity"])) {
                        // Jika $data[$tags] adalah array, ambil kunci yang bernilai true
                        if (is_array($data[$tags])) {
                            $trueValues = array_keys(array_filter($data[$tags], function ($value) {
                                return $value === true;
                            }));
                            
                            // Tambahkan ke $tagsValues hanya jika ada nilai yang true
                            if (!empty($trueValues)) {
                                $tagsValues[$date] = $trueValues;
                            }
                        } elseif ($data[$tags] === true) {
                            // Jika $data[$tags] adalah nilai tunggal true
                            $tagsValues[$date] = [$tags];
                        }
                    } else {
                        // Tambahkan ke $tagsValues hanya jika nilai tidak null
                        $tagsValues[$date] = $data[$tags];
                    }
                }
            }

            uksort($tagsValues, function ($a, $b) {
                return strtotime($b) - strtotime($a);
            });

            if (!in_array($tags, ["sex_activity", "bleeding_flow", "symptoms", "vaginal_discharge", "moods", "others", "physical_activity"])) {
                $percentageOccurrences30Days = [];
                $percentageOccurrences3Months = [];
                $percentageOccurrences6Months = [];
                $percentageOccurrences1Year = [];
            } else {
                $percentageOccurrences30Days = $this->findOccurrences($tagsValues, $thirtyDaysAgo);
                $percentageOccurrences3Months = $this->findOccurrences($tagsValues, $threeMonthsAgo);
                $percentageOccurrences6Months = $this->findOccurrences($tagsValues, $sixMonthsAgo);
                $percentageOccurrences1Year = $this->findOccurrences($tagsValues, $oneYearAgo);
            } 

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

    function calculatePercentage($tagsValues, $startDate) {
        $allValues = [];
    
        foreach ($tagsValues as $date => $data) {
            if (strtotime($date) >= strtotime($startDate)) {
                $allValues = array_merge($allValues, is_array($data) ? array_values($data) : [$data]);
            }
        }
    
        $occurrences = array_count_values($allValues);
        $totalOccurrences = count($allValues);
        $percentageOccurrences = [];
    
        foreach ($occurrences as $value => $count) {
            $percentage = ($count / $totalOccurrences) * 100;
            $percentageOccurrences[$value] = $percentage;
        }
    
        return $percentageOccurrences;
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
    
    public function storeReminder(Request $request) {
        $rules = [
            "id" => "nullable|string",
            "title" => "required|string",
            "description" => "nullable|string",
            "datetime" => "required|date_format:Y-m-d H:i|after_or_equal:" . now(),
        ];
        $messages = [];
        $attributes = [
            "reminder" => __('attribute.reminder'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            DB::beginTransaction();
        
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $userData = RiwayatLog::where('user_id', $user_id)->first();

            $preMadeReminderId = $request->id;
        
            // Generate unique IDs for each reminder
            $newReminders = [
                "id" => $preMadeReminderId == null ? Str::uuid() : $preMadeReminderId,
                "title" => $request->title,
                "description" => $request->description,
                "datetime" => $request->datetime
            ];
        
            if ($userData) {
                // User data exists, update the existing data with new reminders
                $userDataReminders = $userData->pengingat ?? [];
                $userData->pengingat = array_merge($userDataReminders, [$newReminders]);
                $userData->save();
            } else {
                // User data doesn't exist, create a new record with the new reminders
                $userData = new RiwayatLog();
                $userData->user_id = $user_id;
                $userData->pengingat = [$newReminders];
                $userData->save();
            }
        
            DB::commit();
        
            return response()->json([
                'status' => 'success',
                'message' => 'Reminder store successfully',
                'data' => $newReminders,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to store reminder".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateReminder(Request $request, $id) {
        $rules = [
            "title" => "required|string",
            "description" => "nullable|string",
            "datetime" => "required|date_format:Y-m-d H:i|after_or_equal:" . now(),
        ];
        $messages = [];
        $attributes = [
            "reminder" => __('attribute.reminder'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        $validator->stopOnFirstFailure();
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], Response::HTTP_BAD_REQUEST);
        }
    
        try {
            DB::beginTransaction();
        
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $userData = RiwayatLog::where('user_id', $user_id)->first();
            
            if (!$userData) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User data not found',
                ], Response::HTTP_NOT_FOUND);
            }
    
            // Cari pengingat berdasarkan ID
            $reminderToUpdate = collect($userData->pengingat)->where('id', $id)->first();
    
            if (!$reminderToUpdate) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Reminder not found',
                ], Response::HTTP_NOT_FOUND);
            }
    
            // Update data pengingat
            $reminderToUpdate['title'] = $request->title;
            $reminderToUpdate['description'] = $request->description;
            $reminderToUpdate['datetime'] = $request->datetime;
    
            // Simpan perubahan kembali ke dalam array pengingat
            $updatedReminders = collect($userData->pengingat)->map(function ($reminder) use ($reminderToUpdate) {
                return $reminder['id'] == $reminderToUpdate['id'] ? $reminderToUpdate : $reminder;
            })->toArray();

            // Simpan array pengingat yang telah diperbarui
            $userData->pengingat = $updatedReminders;

            // Simpan perubahan ke dalam database
            $userData->save();
        
            DB::commit();
        
            return response()->json([
                'status' => 'success',
                'message' => 'Reminder updated successfully',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to update reminder".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteReminder(Request $request, $id) {
        try {
            DB::beginTransaction();
    
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $userData = RiwayatLog::where('user_id', $user_id)->first();
    
            if ($userData) {
                $userDataReminders = $userData->pengingat ?? [];
    
                // Cari pengingat berdasarkan ID
                $reminderIndex = array_search($id, array_column($userDataReminders, 'id'));
    
                if ($reminderIndex !== false) {
                    // Hapus pengingat jika ditemukan
                    unset($userDataReminders[$reminderIndex]);
    
                    // Update data pengingat
                    $userData->pengingat = array_values($userDataReminders);
                    $userData->save();
    
                    DB::commit();
    
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Reminder deleted successfully',
                    ], Response::HTTP_OK);
                } else {
                    // Jika ID tidak ditemukan
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Reminder not found with the provided ID'
                    ], Response::HTTP_NOT_FOUND);
                }
            } else {
                // Jika data pengguna tidak ditemukan
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'User data not found'
                ], Response::HTTP_NOT_FOUND);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to delete reminder".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getAllReminder(Request $request) {
        try {
            DB::beginTransaction();
    
            $user = Login::where('token', $request->header('user_id'))->first();
            $user_id = $user->id;
            $userData = RiwayatLog::where('user_id', $user_id)->first();
    
            if ($userData) {
                $userDataReminders = $userData->pengingat ?? [];
                $today = Carbon::now();
    
                $futureReminders = array_filter($userDataReminders, function ($reminder) use ($today) {
                    $reminderDatetime = Carbon::parse($reminder['datetime']);
                    return $reminderDatetime->isAfter($today);
                });

                usort($futureReminders, function($a, $b) {
                    $datetimeA = Carbon::parse($a['datetime']);
                    $datetimeB = Carbon::parse($b['datetime']);
                
                    return $datetimeA <=> $datetimeB;
                });
    
                $userData->pengingat = array_values($futureReminders);
                $userData->save();
    
                $futureReminders = array_values($futureReminders);
    
                DB::commit();
    
                return response()->json([
                    'status' => 'success',
                    'message' => 'Reminder fetched successfully',
                    'data' => $futureReminders,
                ], Response::HTTP_OK);
            } else {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'User data not found'
                ], Response::HTTP_NOT_FOUND);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to fetch reminder".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
