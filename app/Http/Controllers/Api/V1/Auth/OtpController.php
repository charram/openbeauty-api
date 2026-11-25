<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class OtpController extends Controller
{
    private const PURPOSE = 'email_verification';
    private const EXPIRY_MINUTES = 5;
    private const MAX_ATTEMPTS = 5;

    /**
     * POST /api/v1/auth/otp/request
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = $validated['email'];

        $key = 'otp-request:' . hash(
            'sha256',
            mb_strtolower($email) . '|' . $request->ip()
        );

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many OTP requests. Please try again later.',
            ], 429);
        }

        RateLimiter::hit($key, 900);

        $user = User::where('email', $email)->first();

        // ไม่เปิดเผยว่ามีอีเมลนี้ในระบบหรือไม่
        if (!$user || $user->email_verified_at !== null) {
            return response()->json([
                'success' => true,
                'message' => 'If verification is required, an OTP has been generated.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $code) {
            // ยกเลิก OTP เดิมที่ยังไม่ได้ใช้
            DB::table('otp_codes')
                ->where('user_id', $user->id)
                ->where('purpose', self::PURPOSE)
                ->whereNull('used_at')
                ->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('otp_codes')->insert([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'purpose' => self::PURPOSE,
                'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
                'attempts' => 0,
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // สำหรับ Development เท่านั้น
        // ห้ามบันทึก OTP ลง Log ใน Production
        if (app()->environment('local')) {
            Log::info('OpenBeauty DEV OTP', [
                'user_id' => $user->id,
                'code' => $code,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'If verification is required, an OTP has been generated.',
        ]);
    }

    /**
     * POST /api/v1/auth/otp/verify
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        $key = 'otp-verify:' . hash(
            'sha256',
            mb_strtolower($validated['email']) . '|' . $request->ip()
        );

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many verification attempts.',
            ], 429);
        }

        $result = DB::transaction(function () use ($validated) {
            $user = User::where('email', $validated['email'])
                ->lockForUpdate()
                ->first();

            if (!$user || $user->email_verified_at !== null) {
                return false;
            }

            $otp = DB::table('otp_codes')
                ->where('user_id', $user->id)
                ->where('purpose', self::PURPOSE)
                ->whereNull('used_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$otp) {
                return false;
            }

            if (now()->greaterThan($otp->expires_at)) {
                return false;
            }

            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                return false;
            }

            if (!Hash::check($validated['code'], $otp->code_hash)) {
                DB::table('otp_codes')
                    ->where('id', $otp->id)
                    ->increment('attempts');

                return false;
            }

            DB::table('otp_codes')
                ->where('id', $otp->id)
                ->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            return true;
        });

        if (!$result) {
            RateLimiter::hit($key, 900);

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        RateLimiter::clear($key);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }
}