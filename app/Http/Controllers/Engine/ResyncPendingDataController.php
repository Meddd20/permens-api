<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Login;
use App\Models\RiwayatKehamilan;
use App\Models\RiwayatMens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResyncPendingDataController extends Controller
{
    public function ResyncPendingData(Request $request) {
        try {
            $data = json_decode($request->getContent(), true);
            // Log::info('ResyncPendingDataController - ResyncPendingData - data: ' . json_encode($data));
            $user = Login::where('token', $request->header('userToken'))->first();
            $user_id = $user->id;
            $userToken = $request->header('userToken');

            foreach ($data['data'] as $table => $operations) {
                if (isset($operations['create'])) {
                    foreach ($operations['create'] as $record) {
                        $this->createRecord($table, $record, $userToken);
                    }
                }

                if (isset($operations['update'])) {
                    foreach ($operations['update'] as $record) {
                        $this->updateRecord($table, $record, $userToken);
                    }
                }

                if (isset($operations['delete'])) {
                    foreach ($operations['delete'] as $record) {
                        $this->deleteRecord($table, $record, $userToken);
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

    private function createRecord($table, $record, $userToken)
    {
        $periodController = new PeriodController();
        $logController = new LogsController();
        $pregnancyController = new PregnancyController();
        $logPregnancyController = new LogKehamilanController();

        switch ($table) {
            case 'period':
                $request = Request::create('/api/period/store-period', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $periodController->storePeriod($request);
                break;
            case 'daily_log':
                $request = Request::create('/api/daily-log/update-log', 'PATCH', $record);
                $request->headers->set('userToken', $userToken);
                $logController->storeLog($request);
                break;
            case 'reminder':
                $request = Request::create('/api/reminder/store-reminder', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $logController->storeReminder($request);
                break;
            case 'pregnancy':
                $request = Request::create('/api/pregnancy/begin', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $pregnancyController->updatePregnancy($request);
                break;
            case 'weight_gain':
                $request = Request::create('/api/pregnancy/weekly-weight-gain', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $pregnancyController->weeklyWeightGain($request);
                break;
            // case 'pregnancy_log': //masih error
            //     $request = Request::create('/api/pregnancy/add-pregnancy-log', 'POST', $record);
            //     $request->headers->set('userToken', $userToken);
            //     $logPregnancyController->addPregnancyLog($request);
            //     break;
            case 'contraction_timer':
                $request = Request::create('/api/pregnancy/add-contraction-timer', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $logPregnancyController->addContractionTimer($request);
                break;
            case 'blood_pressure':
                $request = Request::create('/api/pregnancy/add-blood-pressure', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $logPregnancyController->addBloodPressure($request);
                break;
            case 'baby_kicks':
                $request = Request::create('/api/pregnancy/add-kicks-counter', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $logPregnancyController->addKickCounterData($request);
                break;
        }
    }

    private function updateRecord($table, $record, $userToken)
    {
        $authController = new AuthController();
        $periodController = new PeriodController();
        $logController = new LogsController();
        $pregnancyController = new PregnancyController();
        $logPregnancyController = new LogKehamilanController();

        switch ($table) {
            case 'user':
                $request = Request::create('/api/update-profile', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $authController->updateProfile($request);
                break;
            case 'period':
                $request = Request::create('/api/period/update-period', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $periodController->updatePeriod($request);
                break;
            case 'reminder':
                $request = Request::create('/api/reminder/edit-reminder', 'PATCH', $record);
                $request->headers->set('userToken', $userToken);
                $logController->updateReminder($request, $record['id']);
                break;
            case 'pregnancy':
                $request = Request::create('/api/pregnancy/begin', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $pregnancyController->updatePregnancy($request);
                break;
            case 'blood_pressure':
                $request = Request::create('/api/pregnancy/edit-blood-pressure/{$record->id}', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $logPregnancyController->editBloodPressure($request, $record['id']);
                break;
        }
    }

    private function deleteRecord($table, $record, $userToken)
    {
        $logController = new LogsController();
        $pregnancyController = new PregnancyController();
        $logPregnancyController = new LogKehamilanController();

        switch ($table) {
            case 'daily_log':
                $logController = new LogsController();
                $request = Request::create('/api/daily-log/delete-log', 'DELETE', $record);
                $request->headers->set('userToken', $userToken);
                $logController->deleteLog($request);
                break;
            case 'reminder':
                $request = Request::create('/api/reminder/store-reminder', 'DELETE', $record);
                $request->headers->set('userToken', $userToken);
                $logController->storeReminder($request);
                break;
            case 'pregnancy':
                $request = Request::create('/api/pregnancy/delete', 'POST', $record);
                $request->headers->set('userToken', $userToken);
                $pregnancyController->deletePregnancy($request);
                break;
            case 'weight_gain':
                $request = Request::create('/api/pregnancy/delete-weekly-weight-gain', 'DELETE', $record);
                $request->headers->set('userToken', $userToken);
                $pregnancyController->deleteWeeklyWeightGain($request);
                break;
            case 'pregnancy_log':
                $request = Request::create('/api/pregnancy/delete-pregnancy-log', 'DELETE', $record);
                $request->headers->set('userToken', $userToken);
                $logPregnancyController->deletePregnancyLog($request);
                break;
            case 'contraction_timer':
                $request = Request::create('/api/pregnancy/delete-contraction-timer/{$record->id}', 'DELETE', $record);
                $request->headers->set('userToken', $userToken);
                $logPregnancyController->deleteContractionTimer($record, $record->id);
                break;
            case 'blood_pressure':
                $request = Request::create('/api/pregnancy/delete-blood-pressure/{$record->id}', 'DELETE', $record);
                $request->headers->set('userToken', $userToken);
                $logPregnancyController->deleteBloodPressure($record, $record->id);
                break;
            case 'baby_kicks':
                $request = Request::create('/api/pregnancy/delete-kicks-counter/{$record->id}', 'DELETE', $record);
                $request->headers->set('userToken', $userToken);
                $logPregnancyController->deleteKicksCounter($record, $record->id);
                break;
        }
    }
}
