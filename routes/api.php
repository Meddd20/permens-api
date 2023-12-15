<?php

use App\Http\Controllers\Engine\AuthController;
use App\Http\Controllers\Engine\CommentController;
use App\Http\Controllers\Engine\LogsController;
use App\Http\Controllers\Engine\MainController;
use App\Http\Controllers\Engine\PeriodController;
use App\Http\Controllers\Engine\PregnancyController;
use App\Http\Controllers\Engine\QuickCalController;
use App\Http\Controllers\Engine\NewsController;
use App\Http\Controllers\Engine\ProfileController;
use Illuminate\Http\Request;
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


Route::get('/connection', function () {
    return 'Connection Successfully';
});

Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/logout', [AuthController::class, 'logout']);
Route::post('auth/register/User', [AuthController::class, 'registerUser']);
Route::post('auth/register/Admin', [AuthController::class, 'registerAdmin']);
Route::post('auth/changepassword', [AuthController::class, 'changePassword']);
Route::post('auth/requestverification', [AuthController::class, 'requestVerificationCode'])
->name('requestVerificationCode');
// ->middleware(['throttle:5,60']);
Route::post('auth/verifyverification', [AuthController::class, 'verifyVerificationCode']);

Route::post('calc/instans', [QuickCalController::class, 'calc']);

Route::middleware("validate_user")->group(function() {
    Route::post('period/index', [MainController::class, 'index']);
    Route::post('period/index/filter', [MainController::class, 'filter']);
    Route::post('period/insight', [MainController::class, 'insight']);

    Route::post('period/store-period', [PeriodController::class, 'storePeriod']);
    Route::patch('period/update-period', [PeriodController::class, 'updatePeriod']);
    Route::post('period/store-prediction', [PeriodController::class, 'storePrediction']);

    Route::post('period/pregnancy-begin', [PregnancyController::class, 'pregnancyBegin']);
    Route::post('period/pregnancy-end', [PregnancyController::class, 'pregnancyEnd']);

    Route::patch('daily-log/update-log', [LogsController::class, 'storeLog']);
    Route::delete('daily-log/delete-log', [LogsController::class, 'deleteLogByDate']);
    Route::get('daily-log/read-all-log', [LogsController::class, 'alllogs']);
    Route::get('daily-log/read-log-by-date/{date}', [LogsController::class, 'logsbydate']);
    Route::get('daily-log/read-log-by-tag/{tags}', [LogsController::class, 'logsByTags']);

    Route::get('articles/show-articles/{id}', [NewsController::class, 'showNews']);
    Route::get('articles/show-all-articles', [NewsController::class, 'showAllNews']);

    Route::get('show-profile', [AuthController::class, 'showProfile']);
    Route::patch('update-profile', [AuthController::class, 'updateProfile']);
    Route::delete('delete-account', [AuthController::class, 'deleteAccount']);

    Route::get('comments/{article_id}', [CommentController::class, 'showCommentsArticle']);
    Route::post('comments/create-comments', [CommentController::class, 'createComment']);
    Route::patch('comments/update-comments/{id}', [CommentController::class, 'updateComment']);
    Route::delete('comments/delete-comments/{id}', [CommentController::class, 'deleteComment']);
    Route::patch('comments/{id}/like', [CommentController::class, 'upvotes']);
    Route::patch('comments/{id}/dislike', [CommentController::class, 'downvotes']);
    Route::patch('comments/{id}/toggle-pinned', [CommentController::class, 'togglePinned']);
    Route::patch('comments/{id}/toggle-hidden', [CommentController::class, 'toggleHidden']);
}); 

Route::middleware('validate_admin')->group(function () {
    Route::post('articles/create-articles', [NewsController::class, 'createNews']);
    Route::patch('articles/update-articles/{id}', [NewsController::class, 'updateNews']);
    Route::delete('articles/delete-articles/{id}', [NewsController::class, 'deleteNews']);
});