<?php

namespace App\Http\Controllers\Engine;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Models\BeratIdealIbuHamil;
use App\Models\Komentar;
use App\Models\KomentarLike;
use App\Models\Login;
use App\Models\RiwayatKehamilan;
use App\Models\RiwayatLog;
use App\Models\RiwayatLogKehamilan;
use App\Models\RiwayatMens;
use App\Models\Verifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function registerUser(Request $request)
    {
        # Input Validation
        $rules = [
            "name" => "required|regex:/^[a-z ,.'-]+$/i|min:2|max:100",
            "birthday" => "required|date",
            'email' => [
                'required',
                'email:dns',
                Rule::unique('tb_user', 'email')->where(function ($query) {
                    $query->where('status', 'Verified');
                }),
                'max:190',
            ],
            'password' => "required|min:8"
        ];  
        $messages = [];
        $attributes = [
            'name' => __('attribute.name'),
            'birthday' => __('attribute.birthday'),
            'email' => __('attribute.email'),
            'password' => __('attribute.password')
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            DB::beginTransaction();
        
            $existingData = Login::where('email', $request->email)
                ->where('status', 'Unverified')
                ->first();
        
            if ($existingData) {
                $existingData->update([
                    'nama' => $request->name,
                    'status' => 'Unverified',
                    'role' => 'User',
                    'tanggal_lahir' => date("Y-m-d", strtotime($request->birthday)),
                    'password' => bcrypt($request->password),
                ]);
        
                $data = $existingData;
            } else {
                $login = Login::create([
                    'nama' => $request->name,
                    'status' => 'Unverified',
                    'role' => 'User',
                    'tanggal_lahir' => date("Y-m-d", strtotime($request->birthday)),
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                ]);
        
                $data = $login;
            }
        
            DB::commit();
        
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => $data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function registerAdmin(Request $request)
    {
        # Input Validation
        $rules = [
            "name" => "required|regex:/^[a-z ,.'-]+$/i|min:2|max:100",
            "birthday" => "required|date",
            'email' => [
                'required',
                'email:dns',
                Rule::unique('tb_user', 'email')->where(function ($query) {
                    $query->where('status', 'Verified');
                }),
                'max:190',
            ],
            'password' => "required|min:8"
        ];
        $messages = [];
        $attributes = [
            'name' => __('attribute.name'),
            'birthday' => __('attribute.birthday'),
            'email' => __('attribute.email'),
            'password' => __('attribute.password')
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            DB::beginTransaction();

            $existingData = Login::where('email', $request->email)
                -> where('status', 'Unverified')
                -> first();

            if ($existingData) {
                $existingData->update([
                    'nama' => $request->name,
                    'status' => 'Unverified',
                    'role' => 'Admin',
                    'tanggal_lahir' => date("Y-m-d", strtotime($request->birthday)),
                    'password' => bcrypt($request->password),
                ]);

                $data = $existingData;
            } else {
                $login = Login::create([
                    'nama' => $request->name,
                    'status' => 'Unverified',
                    'role' => 'Admin',
                    'tanggal_lahir' => date("Y-m-d", strtotime($request->birthday)),
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                ]);

                $data = $login;
            }

            DB::commit();

            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => $data,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function login(Request $request)
    {
        try {
            if(Auth::guard()->attempt(['email' => $request->email, 'password' => $request->password, 'status' => 'Verified'])){
                DB::beginTransaction();

                $user = auth()->guard()->user();
                $token = Str::random(30);

                $user->token = $token;
                $user->save();
                    
                DB::commit();

                return response()->json([
                    "status" => "success",
                    "message" => __('response.login_success'),
                    "data" => $user
                ], Response::HTTP_OK);
            } else {
                return response()->json([
                    "status" => "failed",
                    "message" => __('response.login_failed')." or ".__('response.account_disactive'),
                ], Response::HTTP_UNAUTHORIZED);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => __('response.login_failed'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logout(Request $request)
    {
        $token = $request->header('user_id');

        if (!$token) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.token_not_found')
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = Login::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.token_unauth')
            ], Response::HTTP_FORBIDDEN);
        }

        $user->token = null;
        $user->fcm_token = null;
        $user->save();

        return response()->json([
            "status" => "success",
            "message" => __('response.logout_success')
        ], Response::HTTP_OK);
    }

    public function checkToken(Request $request) 
    {
        $token = $request->header('user_id');

        if (!$token) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.token_unauth')
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = Login::where('token', $token)->first();

        if (!$user) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.token_unauth')
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            "status" => "success",
            "message" => "Token valid"
        ], Response::HTTP_OK);
    }

    public function updateProfile(Request $request)
    {
        $rules = [
            'name' => 'required|regex:/^[a-z ,.\'-]+$/i|min:2|max:100',
            'birthday' => 'required|date',
        ];
        $messages = [];
        $attributes = [
            'name' => __('attribute.name'),
            'birthday' => __('attribute.birthday'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = Login::where('token', $request->header('user_id'))->first();

        try {
            DB::beginTransaction();
    
            $user->update([
                'nama' => $request->input('name'),
                'tanggal_lahir' => $request->input('birthday'),
            ]);
            
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.profile_update_success'),
                'user' => $user,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function showProfile(Request $request)
    {
        try {
            $user = Login::where('token', $request->header('user_id'))->first();
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.get_user_profile'),
                'user' => $user,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteAccount(Request $request)
    {
        $user = Login::where('token', $request->header('user_id'))->first();

        try {
            DB::beginTransaction();

            $user->status = "Deleted";
            $user->save(); 
            $user->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.user_deleted_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function truncateUserData(Request $request)
    {
        $user = Login::where('token', $request->header('user_id'))->first();

        try {
            DB::beginTransaction();

            RiwayatMens::where('user_id', $user->id)->delete();
            BeratIdealIbuHamil::where('user_id', $user->id)->delete();
            RiwayatLogKehamilan::where('user_id', $user->id)->delete();
            RiwayatKehamilan::where('user_id', $user->id)->delete();
            RiwayatLog::where('user_id', $user->id)->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.user_deleted_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function changePassword(Request $request) {
        # Input Validation
        $rules = [
            'email' => "required|email:dns|max:190|exists:tb_user,email",
            'new_password' => "required|min:8|same:new_password_confirmation",
            'new_password_confirmation' => "required|min:8|same:new_password",
        ];
        $messages = [];
        $attributes = [
            'email' => __('attribute.email'),
            'new_password' => __('attribute.new_password'),
            'new_password_confirmation' => __('attribute.new_password_confirmation'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }
    
        try {
            DB::beginTransaction();

            $user = Login::where('email', $request->email)->first();
            $user->password = bcrypt($request->new_password);
            $user->save();

            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.password_changed_success'),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function requestVerificationCode(Request $request) {
        $rules = [
            'email' => 'required|email:dns|max:190',
            'type' => 'required|in:Verifikasi,Lupa Password',
        ];
        $messages = [];
        $attributes = [
            'email' => __('attribute.email'),
            'type' => __('attribute.type'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = Login::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.user_not_found'),
            ], Response::HTTP_NOT_FOUND);
        }

        $user_role = $user->role;
        $verificationType = $request->type;
        $role = $user_role == 'Admin' ? 'Admin' : 'User';
        $verificationTypeName = $verificationType == 'Verifikasi' ? 'Verifikasi' : 'Lupa Password';

        $account_verif = Verifikasi::where('email', $request->email)
            ->where('diverifikasi', null)
            ->where('role', $role)
            ->where('jenis', $verificationTypeName)
            ->first();

        $kadaluarsa = Carbon::now()->timezone(env('APP_TIMEZONE'))->addMinutes(30);
        $kode = mt_rand(100000, 999999);

        if ($account_verif) {
            $last_send = Carbon::parse($account_verif->updated_at);
            $now = Carbon::now()->timezone(env('APP_TIMEZONE'));
            $diffInSec = $last_send->diffInSeconds($now);

            if ($diffInSec < 60) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.request_verif_failed'),
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }

            $account_verif->kode = $kode;
            $account_verif->kadaluarsa = $kadaluarsa;
            $account_verif->percobaan++;
            $account_verif->save();
            $data = $account_verif;
        } else {
            $account_verif_new = [
                'kode' => $kode,
                'email' => $request->email,
                'role' => $role,
                'jenis' => $verificationTypeName,
                'percobaan' => 1,
                'kadaluarsa' => $kadaluarsa,
            ];
            Verifikasi::create($account_verif_new);
            $data = $account_verif_new;
        }

        $jenis = $verificationType == 'Verifikasi' ? "verifikasi akun" : "mengganti password akun";
        $userType = $role == 'Admin' ? 'admin' : 'user';
        $content = "Kode verifikasi email untuk " . $jenis . " " . $userType . " adalah " . $kode . ". Berlaku sampai " . Carbon::parse($kadaluarsa)->format('H:i / d-m-Y');
        
        try {
            Mail::raw($content, function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('Kalender Menstruasi dan Kehamilan by Medhiko - Kode Verifikasi Email');
            });

            return response()->json([
                'status' => 'success',
                'message' => __('response.verification_email_success'),
                'data' => $data
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.verification_email_failed'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verifyVerificationCode(Request $request) {
        $rules = [
            'email' => 'required|email:dns|max:190',
            'verif_code' => "required|min:6|max:6",
            'type' => 'required|in:Verifikasi,Lupa Password',
            'user_role' => 'required|in:User, Admin'
        ];
        $messages = [];
        $attributes = [
            'email' => __('attribute.email'),
            'verif_code' => __('attribute.verif_code'),
            'type' => __('attribute.type'),
            'user_role' => __('attribute.user_role'),
        ];
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            DB::beginTransaction();
            $now = Carbon::now()->timezone(env('APP_TIMEZONE'));
            $account_verif = Verifikasi::where('email', $request->email)
                ->where('role', $request->user_role)
                ->where('jenis', $request->type)
                ->where('kode', $request->verif_code)
                ->where('diverifikasi', null)
                ->where('kadaluarsa', '>=', $now)
                ->first();

            $status_akun = Login::where('email', $request->email)->first();

            if (!empty($account_verif)) {
                $account_verif->diverifikasi = $now;
                $account_verif->save();

                $status_akun->status = 'Verified';
                $status_akun->save();
            } else {
                return response()->json([
                    'status' => "error",
                    'message' => __('response.code_verification_failed'),
                ], Response::HTTP_BAD_REQUEST);
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => __('response.code_verification_success'),
            ], Response::HTTP_OK);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
