<?php

namespace App\Http\Controllers\Engine;

use Illuminate\Http\Request;
use App\Models\RiwayatKehamilan;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Login;
use Illuminate\Support\Facades\Validator;

class PregnancyController extends Controller
{
    public function pregnancyBegin(Request $request)
    {
        # Input Validation
        $rules = [
            "period_end" => "required|date",
            "pregnant_begin" => "required|date",
            "description" => "nullable|regex:/^[a-z0-9 ,.'-]+$/i|min:1"
        ];
        $messages = [];
        $attributes = [
            'period_end' => __('attribute.period_end'),
            'pregnant_begin' => __('attribute.pregnant_begin'),
            'description' => __('attribute.description'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        # Return Response
        try {
            DB::beginTransaction();
                $pregnant_history = RiwayatKehamilan::create([
                    "user_id" => $request->header('user_id'),
                    "status" => 'Hamil',
                    "haid_terakhir" => $request->period_end,
                    "kehamilan_awal" => $request->pregnant_begin,
                    "kehamilan_akhir" => NULL,
                    "keterangan" => $request->description
                ]);
                Login::where('id', $request->header('user_id'))->update([
                    "is_pregnant" => '1'
                ]);
            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success'),
                "data" => [
                    "period_history" => $pregnant_history
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

    public function pregnancyEnd(Request $request)
    {
        # Input Validation
        $rules = [
            "pregnancy_status" => "required",
            "pregnant_end" => "required|date",
            "description" => "nullable|regex:/^[a-z0-9 ,.'-]+$/i|min:1"
        ];
        $messages = [];
        $attributes = [
            'pregnancy_status' => __('response.pregnancy_status'),
            'pregnant_end' => __('response.pregnant_end'),
            'description' => __('response.description'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        # Return Response
        try {
            $pregnancy_id = RiwayatKehamilan::where('user_id', $request->header('user_id'))->first()->id;

            DB::beginTransaction();
                $pregnant_history = RiwayatKehamilan::where('id', $pregnancy_id)->update([
                    "status" => $request->pregnancy_status,
                    "kehamilan_akhir" => $request->pregnant_end,
                    "keterangan" => $request->description
                ]);

                Login::where('id', $request->header('user_id'))->update([
                    "is_pregnant" => '0'
                ]);
            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.saving_success'),
                "data" => [
                    "period_history" => $pregnant_history
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
