<?php

use App\Http\Controllers\Engine\AuthController;
use App\Http\Controllers\Engine\CommentController;
use App\Http\Controllers\Engine\LogsController;
use App\Http\Controllers\Engine\MainController;
use App\Http\Controllers\Engine\PeriodController;
use App\Http\Controllers\Engine\PregnancyController;
use App\Http\Controllers\Engine\QuickCalController;
use App\Http\Controllers\Engine\NewsController;
use App\Http\Controllers\MasterFoodController;
use App\Http\Controllers\MasterKehamilanController;
use App\Http\Controllers\MasterVaccineController;
use App\Http\Controllers\MasterVitaminController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware("api_key")->group(function() {
    Route::get('/connection', function () {
        return 'Connection Successfully';
    });
    
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/register/user', [AuthController::class, 'registerUser']);
    Route::post('auth/register/admin', [AuthController::class, 'registerAdmin']);
    Route::post('auth/changepassword', [AuthController::class, 'changePassword']);
    Route::post('auth/requestverification', [AuthController::class, 'requestVerificationCode'])
    ->name('requestVerificationCode');
    // ->middleware(['throttle:5,60']);
    Route::post('auth/verifyverification', [AuthController::class, 'verifyVerificationCode']);
    
    Route::post('calc/instans', [QuickCalController::class, 'calc']);
    Route::post('period/store-period', [PeriodController::class, 'storePeriod']);
    Route::post('pregnancy/begin', [PregnancyController::class, 'pregnancyBegin']);
    Route::get('articles/show-articles/{id}', [NewsController::class, 'showNews']);
    Route::get('articles/show-all-articles', [NewsController::class, 'showAllNews']);

    Route::get('master/get-food', [MasterFoodController::class, 'getAllFood']);
    Route::get('master/get-pregnancy', [MasterKehamilanController::class, 'getAllDataKehamilan']);
    Route::get('master/get-vaccines', [MasterVaccineController::class, 'getAllVaccineData']);
    Route::get('master/get-vitamins', [MasterVitaminController::class, 'getAllVitaminData']);
    
    Route::middleware("validate_user")->group(function() {
        Route::post('/send-notification', [NotificationController::class, 'send']);
        Route::post('/notifications/token', [NotificationController::class, 'store']);

        Route::get('period/index', [MainController::class, 'index']);
        Route::post('period/index/filter', [MainController::class, 'filter']);
        Route::post('period/date-event', [MainController::class, 'currentDateEvent']);
        // Route::post('period/insight', [MainController::class, 'insight']);
        
        Route::patch('period/update-period', [PeriodController::class, 'updatePeriod']);
        Route::post('period/store-prediction', [PeriodController::class, 'storePrediction']);
    
        Route::get('pregnancy/index', [MainController::class, 'pregnancyIndex']);
        Route::post('pregnancy/end', [PregnancyController::class, 'pregnancyEnd']);
        Route::post('pregnancy/delete', [PregnancyController::class, 'deletePregnancy']);
    
        Route::post('pregnancy/init-weight-gain', [PregnancyController::class, 'initWeightPregnancyTracking']);
        Route::post('pregnancy/weekly-weight-gain', [PregnancyController::class, 'weeklyWeightGain']);
        Route::delete('pregnancy/delete-weekly-weight-gain', [PregnancyController::class, 'deleteWeeklyWeightGain']);
        Route::get('pregnancy/pregnancy-weight-gain', [PregnancyController::class, 'PregnancyWeightGainIndex']);
    
        Route::patch('daily-log/update-log', [LogsController::class, 'storeLog']);
        Route::delete('daily-log/delete-log', [LogsController::class, 'deleteLogByDate']);
        Route::get('daily-log/read-all-log', [LogsController::class, 'alllogs']);
        Route::get('daily-log/read-log-by-date', [LogsController::class, 'logsbydate']);
        Route::get('daily-log/read-log-by-tag', [LogsController::class, 'logsByTags']);
    
        Route::get('reminder/get-all-reminder', [LogsController::class, 'getAllReminder']);
        Route::post('reminder/store-reminder', [LogsController::class, 'storeReminder']);
        Route::patch('reminder/edit-reminder/{id}', [LogsController::class, 'updateReminder']);
        Route::delete('reminder/delete-reminder/{id}', [LogsController::class, 'deleteReminder']);
    
        Route::get('auth/sync-data', [MainController::class, 'syncData']);
        Route::get('auth/get-profile', [AuthController::class, 'showProfile']);
        Route::post('auth/check-token', [AuthController::class, 'checkToken']);
        Route::patch('auth/update-profile', [AuthController::class, 'updateProfile']);
        Route::delete('auth/delete-data', [AuthController::class, 'truncateUserData']);
        Route::delete('auth/delete-account', [AuthController::class, 'deleteAccount']);
    
        Route::get('comments/{article_id}', [CommentController::class, 'showCommentsArticle']);
        Route::post('comments/create-comments', [CommentController::class, 'createComment']);
        Route::patch('comments/update-comments/{id}', [CommentController::class, 'updateComment']);
        Route::delete('comments/delete-comments/{id}', [CommentController::class, 'deleteComment']);
        Route::post('comments/like-comments', [CommentController::class, 'likeComment']);
    }); 
    
    Route::middleware('validate_admin')->group(function () {
        Route::post('articles/create-articles', [NewsController::class, 'createNews']);
        Route::post('articles/update-articles/{id}', [NewsController::class, 'updateNews']);
        Route::delete('articles/delete-articles/{id}', [NewsController::class, 'deleteNews']);
        
        Route::post('master/create-pregnancy', [MasterKehamilanController::class, 'createDataKehamilan']);
        Route::post('master/update-pregnancy/{id}', [MasterKehamilanController::class, 'updateDataKehamilan']);
        Route::delete('master/delete-pregnancy/{id}', [MasterKehamilanController::class, 'deleteDataKehamilan']);

        Route::post('master/create-food', [MasterFoodController::class, 'createFood']);
        Route::post('master/update-food/{id}', [MasterFoodController::class, 'updateFood']);
        Route::delete('master/delete-food/{id}', [MasterFoodController::class, 'deleteFood']);

        Route::post('master/create-vaccines', [MasterVaccineController::class, 'createVaccineData']);
        Route::post('master/update-vaccines/{id}', [MasterVaccineController::class, 'updateVaccineData']);
        Route::delete('master/delete-vaccines/{id}', [MasterVaccineController::class, 'deleteVaccineData']);
        
        Route::post('master/create-vitamins', [MasterVitaminController::class, 'createVitaminData']);
        Route::post('master/update-vitamins/{id}', [MasterVitaminController::class, 'updateVitaminData']);
        Route::delete('master/delete-vitamins/{id}', [MasterVitaminController::class, 'deleteVitaminData']);
    });
});