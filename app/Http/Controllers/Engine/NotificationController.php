<?php

namespace App\Http\Controllers\Engine;

use App\Http\Controllers\Controller;
use App\Models\Login;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationController extends Controller
{
    public function send(Request $request)
    {
        $firebase = (new Factory)
            ->withServiceAccount(base_path('menstrual-calendar-n-pregnancy-firebase-adminsdk-alyfw-f0d61f6729.json'));

        $messaging = $firebase->createMessaging();
        $user_id = $request->user_id;
        $title = $request->title;
        $body = $request->body;
        
        $user = Login::find($user_id);

        if (!$user) {
            return response()->json([
                "message" => "User not found"
            ], 404);
        }

        if (!$user->fcm_token) {
            return response()->json([
                "message" => "User does not have an FCM token"
            ], 400);
        }

        $message = CloudMessage::fromArray([
            'token' => $user->fcm_token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ]);

        try {
            $messaging->send($message);
            Log::info('Notification sent successfully', ['user_id' => $user_id]);
            return response()->json([
                "message" => "Notification sent successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "message" => "Failed to send notification",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function sendNotification($user_id, $title, $body)
    {
        $firebase = (new Factory)
            ->withServiceAccount(base_path('menstrual-calendar-n-pregnancy-firebase-adminsdk-alyfw-f0d61f6729.json'));

        $messaging = $firebase->createMessaging();
        
        $user = Login::find($user_id);

        if (!$user) {
            return response()->json([
                "message" => "User not found"
            ], 404);
        }

        if (!$user->fcm_token) {
            return response()->json([
                "message" => "User does not have an FCM token"
            ], 400);
        }

        $message = CloudMessage::fromArray([
            'token' => $user->fcm_token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ]);

        try {
            $messaging->send($message);
            Log::info('Notification sent successfully', ['user_id' => $user_id]);
            return response()->json([
                "message" => "Notification sent successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "message" => "Failed to send notification",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        if ($request->fcm_token == null) {
            return response()->json([
                "message" => "fcm_token is null, data tidak disimpan"
            ]);
        }
        
        $user_id = $request->user_id;
        $user = Login::where('id', $user_id)->first();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        Log::info('User after save: ', $user->toArray());

        return response()->json([
            "message" => "OK"
        ]);
    }
}
