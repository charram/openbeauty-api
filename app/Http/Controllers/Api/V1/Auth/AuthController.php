<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/register
     *
     * สมัครสมาชิกด้วย Email และ Password
     * ไม่อนุญาตให้ผู้สมัครกำหนด Role เอง
     */
public function register(Request $request): JsonResponse
{
    $validated = $request->validate([
        // รองรับ Flutter เดิมที่ส่ง name
        'name' => [
            'nullable',
            'required_without_all:first_name,last_name',
            'string',
            'max:255',
        ],

        // รองรับ Flutter ใหม่ที่ส่งชื่อและนามสกุลแยกกัน
        'first_name' => [
            'nullable',
            'required_with:last_name',
            'string',
            'max:100',
        ],

        'last_name' => [
            'nullable',
            'required_with:first_name',
            'string',
            'max:100',
        ],

        'email' => [
            'required',
            'email',
            'max:255',
            'unique:users,email',
        ],

        'password' => [
            'required',
            'string',
            'min:8',
            'confirmed',
        ],
    ]);

    $firstName = trim($validated['first_name'] ?? '');
    $lastName = trim($validated['last_name'] ?? '');

    if ($firstName !== '' && $lastName !== '') {
        $fullName = trim($firstName . ' ' . $lastName);
    } else {
        // รองรับบัญชีที่ส่งชื่อเต็มมาแบบเดิม
        $fullName = trim($validated['name'] ?? '');

        $firstName = null;
        $lastName = null;
    }

    $user = User::create([
        'name' => $fullName,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $validated['email'],
        'password' => $validated['password'],
    ]);

    // ไม่ออก Token จนกว่าจะยืนยันบัญชี
    return response()->json([
        'success' => true,
        'message' => 'Registration successful. Verification required.',
        'data' => [
            'user_id' => $user->id,
            'email' => $user->email,
            'verification_required' => true,
        ],
    ], 201);
}

    /**
     * POST /api/v1/auth/login
     *
     * Login ด้วย Email + Password
     * รองรับบัญชีที่ยืนยัน Phone OTP หรือ Email แล้ว
     */
    public function login(Request $request): JsonResponse
    {
        // 1. Validate ข้อมูล
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        // 2. Rate Limiting ป้องกันการเดารหัสผ่าน
        $key = 'login:' . hash(
            'sha256',
            Str::lower($validated['email']) . '|' . $request->ip()
        );

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many login attempts. Please try again later.',
            ], 429);
        }

        // 3. ค้นหา Email โดยไม่สนตัวพิมพ์ใหญ่-เล็ก
        $user = User::whereRaw(
            'LOWER(email) = LOWER(?)',
            [$validated['email']]
        )->first();

        // 4. ตรวจสอบ Password
        if (
            !$user ||
            !$user->password ||
            !Hash::check($validated['password'], $user->password)
        ) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // 5. ตรวจสอบสถานะยืนยันบัญชี
        //
        // ต้องมั่นใจว่า phone_verified_at ถูกกำหนด
        // หลัง Backend ตรวจ Firebase OTP สำเร็จจริงเท่านั้น
        $emailVerified = !is_null($user->email_verified_at);
        $phoneVerified = !is_null($user->phone_verified_at);

        if (!$emailVerified && !$phoneVerified) {
            return response()->json([
                'success' => false,
                'message' => 'Account verification required.',
                'verification_required' => true,
            ], 403);
        }

        // 6. ล้างจำนวนครั้งที่ Login ผิด
        RateLimiter::clear($key);

        // 7. สร้าง Laravel Sanctum Token
        $token = $user->createToken(
            'openbeauty-mobile'
        )->plainTextToken;

        // 8. ส่งข้อมูลกลับ Flutter
        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /**
     * GET /api/v1/auth/me
     *
     * ดึงข้อมูลผู้ใช้ที่ Login อยู่
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user(),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * ลบ Sanctum Token ปัจจุบัน
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }
}