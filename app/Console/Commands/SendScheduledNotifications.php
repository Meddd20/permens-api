<?php

namespace App\Console\Commands;

use App\Http\Controllers\Engine\NotificationController;
use App\Models\Login;
use App\Models\RiwayatKehamilan;
use App\Models\RiwayatMens;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-scheduled-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = Login::whereNotNull('fcm_token')->get();
        $notificationController = new NotificationController();
        $today = Carbon::today();

        foreach ($users as $user) {
            if ($user->is_pregnant == 0) {
                $latestPeriod = RiwayatMens::where('user_id', $user->id)
                    ->where('is_actual', '1')
                    ->orderBy('haid_awal', 'DESC')
                    ->first();

                $startPeriod = Carbon::parse($latestPeriod->haid_awal);
                $endPeriod = Carbon::parse($latestPeriod->haid_akhir);
                $fertileStart = Carbon::parse($latestPeriod->haid_akhir);
                $fertileEnd = Carbon::parse($latestPeriod->haid_akhir);
                $ovulation = Carbon::parse($latestPeriod->haid_akhir);
                $nextPeriod = Carbon::parse($latestPeriod->haid_akhir);

                if ($today->eq($endPeriod)) {
                    $notificationController->sendNotification($user->id, 'Period Ended', 'Has your period ended? Please update your menstrual cycle records.');
                }

                if ($today->eq($fertileStart)) {
                    $notificationController->sendNotification($user->id, 'Fertility Window Start', 'Your fertility window starts today.');
                }

                if ($today->eq($fertileEnd)) {
                    $notificationController->sendNotification($user->id, 'Fertility Window End', 'Your fertility window ends today.');
                }

                if ($today->eq($ovulation)) {
                    $notificationController->sendNotification($user->id, 'Ovulation Day', 'Today is your ovulation day.');
                }

                for ($i = 5; $i >0; $i--) {
                    if ($today->eq($nextPeriod->subtract($i))) {
                        $notificationController->sendNotification($user->id, 'Next Period', "Your next period is coming in $i days.");
                    }
                }

                if ($today->eq($startPeriod->addDays(30))) {
                    $notificationController->sendNotification($user->id, 'Reminder to Record', 'It has been a while since you last updated your menstrual cycle. Please record your latest data.');
                }

                if ($today->eq($startPeriod->addDays(60))) {
                    $notificationController->sendNotification($user->id, 'Check Pregnancy', 'It has been 8 weeks since your last recorded period. Are you pregnant? Please update your records.');
                }

            } else {
                $currentPregnancy = RiwayatKehamilan::where('user_id', $user->id)->where('status', 'Hamil')->first();
                $hariPertamaHaidTerakhir = Carbon::parse($currentPregnancy->hari_pertama_haid_terakhir);
                $usiaKehamilan = Carbon::now()->diffInWeeks($hariPertamaHaidTerakhir);

                for ($minggu = $usiaKehamilan; $minggu <= 40; $minggu++) {
                    $hariPertamaMingguKehamilan = $hariPertamaHaidTerakhir->addDays(($minggu - 1) * 7);
                    if ($today->eq($hariPertamaMingguKehamilan)) {
                        $notificationController->sendNotification($user->id, 'Pregnancy Started', "Week $minggu, Click here to see your highlight, changes, and symptoms you might feel this week.");
                    }

                    if ($today->eq($hariPertamaMingguKehamilan->addDays(2))) {
                        $notificationController->sendNotification($user->id, 'Weight Tracker', 'Fill out your weight this week. It helps track down the weight gain of both the baby and you.');
                    }
                }
                
            }
        }
    }
}
