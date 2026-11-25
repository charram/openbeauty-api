<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class PasswordSetupController extends Controller
{
    /**
     * POST /api/v1/auth/password/setup
     *
     * Set password once after Firebase phone verification.
     */
    public function setup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8),
            ],
            'firebase_id_token' => [
                'required',
                'string',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ข้อมูลไม่ถูกต้อง',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!$user || !$user->phone_verified_at || !$user->phone) {
            return response()->json([
                'success' => false,
                'message' => 'กรุณายืนยันเบอร์โทรศัพท์ก่อน',
            ], 403);
        }

        if ($user->password) {
            return response()->json([
                'success' => false,
                'message' => 'บัญชีนี้มีรหัสผ่านอยู่แล้ว',
            ], 409);
        }

        try {
            $credentials = config('services.firebase.credentials');

            if (!$credentials || !is_file($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ระบบยืนยันตัวตนยังไม่พร้อมใช้งาน',
                ], 503);
            }

            $auth = (new Factory())
                ->withServiceAccount($credentials)
                ->createAuth();

            $verifiedToken = $auth->verifyIdToken(
                $request->input('firebase_id_token')
            );

            $claims = $verifiedToken->claims()->all();
            $firebasePhone = $claims['phone_number'] ?? null;
            $provider = $claims['firebase']['sign_in_provider'] ?? null;
            $authTimeClaim = $claims['auth_time'] ?? null;

            $authTime = match (true) {
                $authTimeClaim instanceof \DateTimeInterface
                    => $authTimeClaim->getTimestamp(),

                is_int($authTimeClaim)
                    => $authTimeClaim,

                is_string($authTimeClaim) && ctype_digit($authTimeClaim)
                    => (int) $authTimeClaim,

                default => null,
            };

            if (
                $provider !== 'phone' ||
                !is_string($firebasePhone) ||
                !hash_equals((string) $user->phone, $firebasePhone)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'ข้อมูลการยืนยันเบอร์โทรศัพท์ไม่ตรงกับบัญชี',
                ], 403);
            }

            // Require recent Firebase authentication (within 5 minutes).
            if (
                !is_numeric($authTime) ||
                (int) $authTime > time() + 60 ||
                time() - (int) $authTime > 300
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณายืนยัน OTP ใหม่ก่อนตั้งรหัสผ่าน',
                ], 403);
            }

            // Atomic update prevents overwriting an existing password.
            $updated = DB::table('users')
                ->where('id', $user->id)
                ->whereNull('password')
                ->whereNotNull('phone_verified_at')
                ->update([
                    'password' => \Illuminate\Support\Facades\Hash::make(
                        $request->input('password')
                    ),
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'บัญชีนี้ตั้งรหัสผ่านแล้ว หรือไม่สามารถดำเนินการได้',
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'ตั้งรหัสผ่านสำเร็จ',
            ]);

        } catch (FailedToVerifyToken $e) {
            return response()->json([
                'success' => false,
                'message' => 'Firebase Token ไม่ถูกต้องหรือหมดอายุ',
            ], 401);

        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถตั้งรหัสผ่านได้ในขณะนี้',
            ], 500);
        }
    }
}
