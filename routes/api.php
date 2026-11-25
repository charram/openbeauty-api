<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\AppointmentController;

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\FirebaseAuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordSetupController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;

use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfilePhoneController;
use App\Http\Controllers\Api\V1\Admin\ClinicManagementController;

/*
|--------------------------------------------------------------------------
| OpenBeauty API
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'OpenBeauty API is running',
        'version' => '1.0.0',
    ]);
});

/*
|--------------------------------------------------------------------------
| Public APIs
|--------------------------------------------------------------------------
*/

Route::apiResource('clinics', ClinicController::class);

Route::prefix('v1')->group(function () {
    Route::get('/clinics', [ClinicController::class, 'index']);
});

Route::apiResource('branches', BranchController::class);
Route::apiResource('services', ServiceController::class);
Route::apiResource('promotions', PromotionController::class);
Route::apiResource('appointments', AppointmentController::class);

/*
|--------------------------------------------------------------------------
| Protected APIs
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

/*
|--------------------------------------------------------------------------
| OpenBeauty Authentication API v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1/auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Authentication Routes
    |--------------------------------------------------------------------------
    */

    // Register with email and password
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    // Login with email and password
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // Request email OTP
    Route::post('/otp/request', [OtpController::class, 'requestOtp'])
        ->middleware('throttle:5,1');

    // Verify email OTP
    Route::post('/otp/verify', [OtpController::class, 'verifyOtp'])
        ->middleware('throttle:10,1');

    /*
    |--------------------------------------------------------------------------
    | Firebase Phone Authentication
    |--------------------------------------------------------------------------
    |
    | Flutter verifies the phone OTP with Firebase.
    | Laravel verifies the Firebase ID Token.
    | Supabase stores the user.
    | Laravel Sanctum issues the API token.
    |
    */

    Route::post('/firebase/verify', [FirebaseAuthController::class, 'verify'])
        ->middleware('throttle:10,1');

    /*
    |--------------------------------------------------------------------------
    | Firebase Social Authentication
    |--------------------------------------------------------------------------
    |
    | Supported providers:
    | - Google
    | - Facebook
    | - Apple
    |
    */

    Route::post('/social/firebase', [SocialAuthController::class, 'login'])
        ->middleware('throttle:10,1');

    /*
    |--------------------------------------------------------------------------
    | Protected Authentication Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        // Current authenticated user
        Route::get('/me', [AuthController::class, 'me']);

        // Logout
        Route::post('/logout', [AuthController::class, 'logout']);

        // Set password after Firebase phone verification
        Route::post('/password/setup', [PasswordSetupController::class, 'setup']);
    });
});

/*
|--------------------------------------------------------------------------
| OpenBeauty Profile API v1
|--------------------------------------------------------------------------
|
| Requires Laravel Sanctum authentication.
|
*/

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->group(function () {

        // Get current user profile
        Route::get('/profile', [ProfileController::class, 'show']);

        // Update current user profile
        Route::put('/profile', [ProfileController::class, 'update']);

        // Upload current user's profile avatar
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])
            ->middleware('throttle:10,1');

        // Link a Firebase-verified phone to the current user
        Route::post('/profile/phone/verify', [ProfilePhoneController::class, 'verify'])
            ->middleware('throttle:5,1');
    });

/*
|--------------------------------------------------------------------------
| Clinic Management API v1
|--------------------------------------------------------------------------
|
| Sanctum authenticates the user.
| ClinicManagementController checks roles and clinic memberships.
|
*/

Route::prefix('v1/admin')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/clinics', [ClinicManagementController::class, 'index']);
    });Route::prefix('v1/admin')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::get('/clinics', [ClinicManagementController::class, 'index']);

        Route::post('/clinics', [ClinicManagementController::class, 'store'])
            ->middleware('throttle:5,1');
    });