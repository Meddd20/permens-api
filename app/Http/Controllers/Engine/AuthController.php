<?php

namespace App\Http\Controllers\Engine;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Models\Login;
use App\Models\UToken;
use App\Models\Verifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use function PHPUnit\Framework\isEmpty;

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
                Rule::unique('tb_1001#', 'email')->where(function ($query) {
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
            ], 400);
        }

        # Age Validation
        $age = Carbon::parse($request->birthday)->age;
        if ($age < 18) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.age_validation')
            ], 400);
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
                    'tanggal_lahir' => date("Y-m-d", strtotime($request->birthday)),
                    'pwd' => bcrypt($request->password),
                ]);
        
                $data = $existingData;
            } else {
                $login = Login::create([
                    'nama' => $request->name,
                    'status' => 'Unverified',
                    'tanggal_lahir' => date("Y-m-d", strtotime($request->birthday)),
                    'email' => $request->email,
                    'pwd' => bcrypt($request->password),
                ]);
        
                $data = $login;
            }
        
            DB::commit();
        
            return response()->json([
                "status" => "success",
                "message" => __('response.getting_data'),
                "data" => $data,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                "status" => "failed",
                "message" => "Failed to get data".' | '.$th->getMessage(),
            ], 400);
        }
        

        # Saving into Database
        

        // $verificationData = [
        //     "email" => $request->email,
        //     "type" => "Verifikasi",
        //     "user_role" => "User"
        // ];

        // $verificationRequest = new Request($verificationData);
        // $response = $this->requestVerificationCode($verificationRequest);

        if (!empty($response)) {
            return response()->json([
                "status" => "success",
                "response" => $response,
            ], 200);
        } else {
            return response()->json([
                "status" => "failed",
                "message" => __('response.regis_failed')
            ], 400);
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
                Rule::unique('tb_1001#', 'email')->where(function ($query) {
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
            ], 400);
        }

        # Age Validation
        $age = Carbon::parse($request->birthday)->age;
        if ($age < 18) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.age_validation')
            ], 400);
        }

        # Saving into Database
        DB::beginTransaction();
        $existingData = Login::where('email', $request->email)
            -> where('status', 'Unverified')
            -> first();

        if ($existingData) {
            $existingData->update([
                'nama' => $request->name,
                'status' => 'Unverified',
                'tanggal_lahir' => date("Y-m-d", strtotime($request->birthday)),
                'pwd' => bcrypt($request->password),
            ]);
        } else {
            $login = Login::create([
                'nama' => $request->name,
                'status' => 'Unverified',
                'tanggal_lahir' => date("Y-m-d", strtotime($request->birthday)),
                'email' => $request->email,
                'pwd' => bcrypt($request->password),
            ]);
        }
        DB::commit();

        $verificationData = [
            "email" => $request->email,
            "type" => "Verifikasi",
            "user_role" => "User"
        ];

        $verificationRequest = new Request($verificationData);
        $response = $this->requestVerificationCode($verificationRequest);

        if (!empty($response)) {
            return response()->json([
                "status" => "success",
                "response" => $response,
            ], 200);
        } else {
            return response()->json([
                "status" => "failed",
                "message" => __('response.regis_failed')
            ], 400);
        }
    }

    public function login(Request $request)
    {
        if(Auth::guard()->attempt(['email' => $request->email, 'password' => $request->password, 'status' => 'Verified'])){

            DB::beginTransaction();
                $utoken = UToken::create([
                    "user_id" => auth()->guard()->user()->id,
                    "token" => Str::random(30)
                ]);
            DB::commit();

            $user = auth()->guard()->user();

            return response()->json([
                "status" => "success",
                "message" => __('response.login_success'),
                "data" => [
                    "credential" => $utoken,
                    "user" => $user
                ]
            ], 200);
        } else {
            return response()->json([
                "status" => "failed",
                "message" => __('response.login_failed')." or ".__('response.account_disactive'),
            ], 401);
        }
    }

    public function logout(Request $request)
    {
        $token = $request->header('user_id');

        if (!$token) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.token')
            ], 403);
        }

        $utoken = UToken::where('token', $token)->first();

        if (!$utoken) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.invalid_token')
            ], 403);
        }

        $utoken->delete();

        return response()->json([
            "status" => "success",
            "message" => __('response.logout_success')
        ], 200);
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
            ], 400);
        }

        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');
        $user = Login::where('id', $user_id)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.user_not_found'),
            ], 404);
        }

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
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }

    public function showProfile()
    {
        try {
            $user_id = UToken::where('token', request()->header('user_id'))->value('user_id');
            $user = Login::where('id', $user_id)->first();
    
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.user_not_found'),
                ], 404);
            }
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.get_user_profile'),
                'user' => $user,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage()
            ], 400);
        }
    }

    public function deleteAccount(Request $request)
    {
        $user_id = UToken::where('token', $request->header('user_id'))->value('user_id');
        $user = Login::where('id', $user_id)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => __('response.user_not_found'),
            ], 404);
        }

        try {
            DB::beginTransaction();
            $user->status = "Deleted";
            $user->save(); 
            $user->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => __('response.user_deleted_success'),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage(),
            ], 400);
        }
    }

    public function changePassword(Request $request) {
        # Input Validation
        $rules = [
            'email' => "required|email:dns|max:190|exists:tb_1001#,email",
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
            ], 400);
        }
    
        try {
            DB::beginTransaction();
            $user = Login::where('email', $request->email)->first();
            $user->pwd = bcrypt($request->new_password);
            $user->save();
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => __('response.password_changed_success'),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage(),
            ], 400);
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
            ], 400);
        }

        $user = Login::where('email', $request->email)->first();
        $user_role = $user->role;

        if ($request->type == 'Verifikasi') {
            if ($user_role == 'Admin') {
                $account_verif = Verifikasi::where('email', $request->email)
                    ->where('diverifikasi', null)
                    ->where('role', 'Admin')
                    ->where('jenis', 'Verifikasi')
                    ->first();
            } else {
                $account_verif = Verifikasi::where('email', $request->email)
                    ->where('diverifikasi', null)
                    ->where('role', 'User')
                    ->where('jenis', 'Verifikasi')
                    ->first();
            }
        } else {
            if ($user_role == 'Admin') {
                $account_verif = Verifikasi::where('email', $request->email)
                    ->where('diverifikasi', null)
                    ->where('role', 'Admin')
                    ->where('jenis', 'Lupa Password')
                    ->first();
            } else {
                $account_verif = Verifikasi::where('email', $request->email)
                    ->where('diverifikasi', null)
                    ->where('role', 'User')
                    ->where('jenis', 'Lupa Password')
                    ->first();
            }
        }

        $kadaluarsa = Carbon::now()->timezone(env('APP_TIMEZONE'))->addMinutes(30);
        $kode = mt_rand(100000, 999999);

        if (!empty($account_verif)) {
            $last_send = Carbon::parse($account_verif->updated_at);
            $now = Carbon::now()->timezone(env('APP_TIMEZONE'));
            $diffInSec = $last_send->diffInSeconds($now);

            if ($diffInSec < 60) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('response.request_verif_failed'),
                ], 400);
            }

            $account_verif->kode = $kode;
            $account_verif->email = $request->email;
            $account_verif->role = $user_role;
            $account_verif->jenis = $request->type;

            if ($request->type == 'Verifikasi') {
                $account_verif->jenis = 'Verifikasi';
                $jenis = "verifikasi akun";
            }else{
                $account_verif->jenis = 'Lupa Password';
                $jenis = "mengganti password akun";
            }
            if($request->user_role == 'Admin'){
                $account_verif->role = 'Admin';
                $user = 'admin';
            }else{
                $account_verif->role = 'User';
                $user = 'user';
            }
        } else {
            $account_verif_new = array(
                'kode' => $kode,
                'email' => $request->email,
                'role' => $user_role,
                'jenis' => $request->type
            );
            if($request->type == 'Verifikasi'){
                $account_verif_new['jenis'] = 'Verifikasi';
                $jenis = "verifikasi akun";
            }else{
                $account_verif_new['jenis'] = 'Lupa Password';
                $jenis = "mengganti password akun";
            }
            if($user_role == 'Admin'){
                $account_verif_new['role'] = 'Admin';
                $user = 'Admin';
            }else{
                $account_verif_new['role'] = 'User';
                $user = 'User';
            }
        }

        $content = "Kode verifikasi email untuk " . $jenis . " " . $user . " adalah " . $kode . ". Berlaku sampai ". Carbon::parse($kadaluarsa)->format('H:i / d-m-Y');

        try {
            Mail::raw($content, function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('Kalender Menstruasi dan Kehamilan by Medhiko - Kode Verifikasi Email')
                        ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            });
            if(!empty($account_verif)){
                $account_verif->percobaan++;
                $account_verif->kadaluarsa = $kadaluarsa;
                $account_verif->save();
                $data = $account_verif;
            }else{
                $account_verif_new['percobaan'] = 1;
                $account_verif_new['kadaluarsa'] = $kadaluarsa;
                Verifikasi::create($account_verif_new);
                $data = $account_verif_new;
            }
            
            return response()->json([
                'status' => 'success',
                'message' => __('response.verification_email_success'),
                'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => __('response.verification_email_failed'),
            ], 400);
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
            ], 400);
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
                ], 400);
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => __('response.code_verification_success'),
            ], 200);

        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage(),
            ], 400);
        }
    }
}
