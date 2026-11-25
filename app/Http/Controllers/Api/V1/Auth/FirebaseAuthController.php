<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class FirebaseAuthController extends Controller
{
    /**
     * POST /api/v1/auth/firebase/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $startedAt = microtime(true);

        Log::info('OTP VERIFY: Request received');

        $validator = Validator::make($request->all(), [
            'id_token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'กรุณาส่ง Firebase ID Token',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $credentials = config('services.firebase.credentials');

            if (!$credentials || !is_file($credentials)) {
                Log::error('OTP VERIFY: Firebase credentials missing');

                return response()->json([
                    'success' => false,
                    'message' => 'ระบบยืนยันตัวตนยังไม่พร้อมใช้งาน',
                ], 503);
            }

            Log::info('OTP VERIFY: Initializing Firebase');

            $auth = (new Factory())
                ->withServiceAccount($credentials)
                ->createAuth();

            Log::info('OTP VERIFY: Verifying Firebase token');

            $verifiedToken = $auth->verifyIdToken(
                $request->input('id_token')
            );

            Log::info('OTP VERIFY: Firebase token verified', [
                'elapsed_seconds' => round(microtime(true) - $startedAt, 2),
            ]);

            $claims = $verifiedToken->claims()->all();

            $firebaseUid = $claims['sub'] ?? null;

            if (!$firebaseUid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase Token ไม่ถูกต้อง',
                ], 401);
            }

            $signInProvider = $claims['firebase']['sign_in_provider'] ?? null;

            if ($signInProvider !== 'phone') {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณายืนยันตัวตนด้วย OTP เบอร์โทรศัพท์',
                ], 403);
            }

            $phone = $claims['phone_number'] ?? null;

            if (
                !is_string($phone) ||
                !preg_match('/^\+[1-9]\d{7,14}$/', $phone)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบเบอร์โทรศัพท์ที่ยืนยันแล้ว',
                ], 403);
            }

            Log::info('OTP VERIFY: Starting database transaction', [
                'firebase_uid' => $firebaseUid,
                'elapsed_seconds' => round(microtime(true) - $startedAt, 2),
            ]);

            $user = DB::transaction(function () use ($phone) {
                $user = User::where('phone', $phone)->first();

                if (!$user) {
                    $user = User::create([
                        'name' => 'Open Beauty User',
                        'phone' => $phone,
                        'phone_verified_at' => now(),
                    ]);
                } elseif (!$user->phone_verified_at) {
                    $user->phone_verified_at = now();
                    $user->save();
                }

                return $user;
            });

            Log::info('OTP VERIFY: Database transaction completed', [
                'user_id' => $user->id,
                'elapsed_seconds' => round(microtime(true) - $startedAt, 2),
            ]);

            $token = $user
                ->createToken('open-beauty-mobile')
                ->plainTextToken;

            Log::info('OTP VERIFY: Sanctum token created', [
                'user_id' => $user->id,
                'elapsed_seconds' => round(microtime(true) - $startedAt, 2),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'ยืนยันเบอร์โทรศัพท์สำเร็จ',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'phone_verified_at' => $user->phone_verified_at,
                        'role' => $user->role,
                    ],
                ],
            ]);

        } catch (FailedToVerifyToken $e) {
            Log::warning('OTP VERIFY: Invalid Firebase token', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Firebase Token ไม่ถูกต้องหรือหมดอายุ',
            ], 401);

        } catch (\Throwable $e) {
            Log::error('OTP VERIFY: Authentication failed', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'elapsed_seconds' => round(microtime(true) - $startedAt, 2),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการยืนยันตัวตน',
            ], 500);
        }
    }
}