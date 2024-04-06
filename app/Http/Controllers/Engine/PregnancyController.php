<?php

namespace App\Http\Controllers\Engine;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\RiwayatKehamilan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Login;
use App\Models\UToken;
use Illuminate\Support\Facades\Validator;

class PregnancyController extends Controller
{
    public function pregnancyBegin(Request $request)
    {
        # Input Validation
        $validated = $request->validate([
            'hari_pertama_haid_terakhir' => 'required|date_format:Y-m-d|before_or_equal:today'
        ]);

        if ($request->header('user_id') == null) {
            $email_regis = $request->input('email_regis');
            $user = Login::where('email', $email_regis)->first();
            $user_id = $user->id;
        } else {
            $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');
            $user = Login::where('id', $user_id)->first();
        }

        $estimated_due_dates = Carbon::parse(Carbon::parse($request->hari_pertama_haid_terakhir)->subMonth(3))->addYear(1)->addDays(7);

        # Return Response
        try {
            DB::beginTransaction();
                $pregnancy_history = RiwayatKehamilan::create([
                    "id" => Str::uuid(),
                    "user_id" => $user_id,
                    "status" => 'Hamil',
                    "hari_pertama_haid_terakhir" => $request->hari_pertama_haid_terakhir,
                    "tanggal_perkiraan_lahir" => $estimated_due_dates,
                    "kehamilan_akhir" => NULL,
                ]);
                Login::where('id', $user_id)->update([
                    "is_pregnant" => '1'
                ]);
            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success'),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.saving_failed').' | '.$th->getMessage()
            ], 400);
        }
    }

    public function editHPHT(Request $request)
    {
        # Input Validation
        $validated = $request->validate([
            'pregnancy_id' => 'required',
            'hari_pertama_haid_terakhir' => 'required|date_format:Y-m-d|before_or_equal:today'
        ]);

        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');

        $estimated_due_dates = Carbon::parse(Carbon::parse($request->hari_pertama_haid_terakhir)->subMonth(3))->addYear(1)->addDays(7);

        # Return Response
        try {
            $pregnancy = RiwayatKehamilan::where('user_id', $user_id)
                            ->where('id', $request->pregnancy_id)
                            ->first();

            if (!$pregnancy) {
                return response()->json([
                    'status' => 'failed',
                    'message' => __('response.pregnancy_not_found')
                ], 404);
            }

            DB::beginTransaction();

                $pregnancy->update([
                    "hari_pertama_haid_terakhir" => $request->hari_pertama_haid_terakhir,
                    "tanggal_perkiraan_lahir" => $estimated_due_dates,
                ]);

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.pregnancy_updated_success'),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.pregnancy_updated_failed') . ' | ' . $th->getMessage()
            ], 400);
        }
    }

    public function pregnancyEnd(Request $request)
    {
        # Input Validation
        $validated = $request->validate([
            'pregnancy_id' => 'required',
            'pregnancy_status' => 'required|in:Keguguran,Aborsi,Melahirkan',
            'pregnancy_end' => 'required|date_format:Y-m-d|before_or_equal:today'
        ]);

        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');

        # Return Response
        try {
            $pregnancy = RiwayatKehamilan::where('user_id', $user_id)
                            ->where('id', $request->pregnancy_id)
                            ->first();

            if (!$pregnancy) {
                return response()->json([
                    'status' => 'failed',
                    'message' => __('response.pregnancy_not_found')
                ], 404);
            }

            DB::beginTransaction();

                $pregnancy->update([
                    "status" => $request->pregnancy_status,
                    "kehamilan_akhir" => $request->pregnancy_end,
                ]);

                Login::where('id', $user_id)->update([
                    "is_pregnant" => '0'
                ]);

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success')
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.saving_failed') . ' | ' . $th->getMessage()
            ], 400);
        }
    }

    public function deletePregnancy(Request $request)
    {
        # Input Validation
        $validated = $request->validate([
            'pregnancy_id' => 'required',
        ]);

        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');

        # Return Response
        try {
            $pregnancy = RiwayatKehamilan::where('user_id', $user_id)
                            ->where('id', $request->pregnancy_id)
                            ->first();

            if (!$pregnancy) {
                return response()->json([
                    'status' => 'failed',
                    'message' => __('response.pregnancy_not_found')
                ], 404);
            }

            DB::beginTransaction();

                $pregnancy->delete();

                Login::where('id', $user_id)->update([
                    "is_pregnant" => '0'
                ]);

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.pregnancy_deleted_success'),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();

            return response()->json([
                "status" => "failed",
                "message" => __('response.pregnancy_deleted_failed') . ' | ' . $th->getMessage()
            ], 400);
        }
    }
}
